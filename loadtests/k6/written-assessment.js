import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Trend, Rate } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const EXAM_ID = Number(__ENV.EXAM_ID || 0);
const TARGET_VUS = Number(__ENV.TARGET_VUS || 100);
const ANSWERS_PER_VU = Number(__ENV.ANSWERS_PER_VU || 20);
const USER_FILE = __ENV.LOAD_DATA_FILE || './data/users.json';

const users = new SharedArray('written-load-users', () => JSON.parse(open(USER_FILE)));

const answerLatency = new Trend('assessment_written_save_ms', true);
const saveFailures = new Rate('assessment_written_save_failed');

export const options = {
  scenarios: {
    assessment_ramp: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: __ENV.RAMP_1 || '30s', target: Math.max(1, Math.ceil(TARGET_VUS * 0.10)) },
        { duration: __ENV.RAMP_2 || '45s', target: Math.max(1, Math.ceil(TARGET_VUS * 0.25)) },
        { duration: __ENV.RAMP_3 || '60s', target: Math.max(1, Math.ceil(TARGET_VUS * 0.50)) },
        { duration: __ENV.RAMP_4 || '90s', target: TARGET_VUS },
        { duration: __ENV.HOLD || '3m', target: TARGET_VUS },
        { duration: __ENV.RAMP_DOWN || '30s', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
    assessment_written_save_failed: ['rate<0.01'],
    assessment_written_save_ms: ['p(95)<2000', 'p(99)<5000'],
  },
};

function csrfFrom(response) {
  const html = response.html();
  return html.find('input[name="_token"]').first().attr('value')
    || html.find('meta[name="csrf-token"]').first().attr('content');
}

function loginFixture(user) {
  const lookupPage = http.get(`${BASE_URL}/applications`, {
    tags: { endpoint: 'guest_lookup_page' },
  });

  check(lookupPage, {
    'lookup page 200': (r) => r.status === 200,
  });

  const csrf = csrfFrom(lookupPage);
  if (!csrf) fail('Unable to read CSRF token from /applications.');

  const lookup = http.post(
    `${BASE_URL}/applications`,
    { _token: csrf, email: user.email },
    {
      redirects: 5,
      tags: { endpoint: 'guest_lookup' },
    }
  );

  check(lookup, {
    'guest lookup succeeds': (r) => r.status === 200,
  });

  return csrf;
}

function startAttempt(user, csrf) {
  const response = http.post(
    `${BASE_URL}/applications/${user.application_id}/assessment/${EXAM_ID}/start`,
    { _token: csrf },
    {
      redirects: 5,
      tags: { endpoint: 'written_start' },
    }
  );

  check(response, {
    'written start/take page succeeds': (r) => r.status === 200,
  });

  const match = response.url.match(/\/assessments\/attempts\/(\d+)/);
  if (!match) {
    fail(`Could not determine attempt id from final URL: ${response.url}`);
  }

  return {
    attemptId: Number(match[1]),
    response,
  };
}

function extractAnswerTargets(response) {
  const radios = response.html().find('.answer-radio');
  const byItem = {};

  radios.each((_, el) => {
    const itemId = Number(el.attr('data-item'));
    const optionId = Number(el.attr('value'));

    if (!itemId || !optionId) return;
    if (!byItem[itemId]) byItem[itemId] = [];
    byItem[itemId].push(optionId);
  });

  return Object.entries(byItem)
    .filter(([, options]) => options.length > 0)
    .map(([itemId, options]) => ({
      itemId: Number(itemId),
      options,
    }));
}

export default function () {
  if (!EXAM_ID) fail('EXAM_ID is required.');
  if (__VU > users.length) {
    fail(`Fixture has ${users.length} users but VU ${__VU} was requested. Use at least TARGET_VUS unique staging applicants.`);
  }

  const user = users[__VU - 1];
  const csrf = loginFixture(user);
  const { attemptId, response } = startAttempt(user, csrf);
  const targets = extractAnswerTargets(response);

  if (!targets.length) fail('No written answer controls were found on the attempt page.');

  const iterations = Math.min(ANSWERS_PER_VU, Math.max(1, targets.length));

  for (let i = 0; i < iterations; i++) {
    const target = targets[i % targets.length];
    const optionId = target.options[(i + __VU) % target.options.length];

    const started = Date.now();
    const save = http.post(
      `${BASE_URL}/assessments/attempts/${attemptId}/answer`,
      JSON.stringify({
        written_exam_id: target.itemId,
        selected_option_id: optionId,
      }),
      {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        tags: { endpoint: 'written_answer_save' },
      }
    );

    answerLatency.add(Date.now() - started);
    const ok = save.status === 200;
    saveFailures.add(!ok);

    check(save, {
      'answer save 200': (r) => r.status === 200,
    });

    sleep(Number(__ENV.THINK_SECONDS || 0.15));
  }
}
