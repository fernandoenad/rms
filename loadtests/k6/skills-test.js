import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Trend, Rate } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const SKILL_TEST_ID = Number(__ENV.SKILL_TEST_ID || 0);
const TARGET_VUS = Number(__ENV.TARGET_VUS || 100);
const SAVES_PER_VU = Number(__ENV.SAVES_PER_VU || 12);
const USER_FILE = __ENV.LOAD_DATA_FILE || './data/users.json';

const users = new SharedArray('skills-load-users', () => JSON.parse(open(USER_FILE)));

const saveLatency = new Trend('assessment_skill_save_ms', true);
const saveFailures = new Rate('assessment_skill_save_failed');

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
    assessment_skill_save_failed: ['rate<0.01'],
    assessment_skill_save_ms: ['p(95)<2000', 'p(99)<5000'],
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

  const csrf = csrfFrom(lookupPage);
  if (!csrf) fail('Unable to read CSRF token from /applications.');

  const lookup = http.post(
    `${BASE_URL}/applications`,
    { _token: csrf, email: user.email },
    { redirects: 5, tags: { endpoint: 'guest_lookup' } }
  );

  check(lookup, {
    'guest lookup succeeds': (r) => r.status === 200,
  });

  return csrf;
}

function startAttempt(user, csrf) {
  const response = http.post(
    `${BASE_URL}/applications/${user.application_id}/skills/${SKILL_TEST_ID}/start`,
    { _token: csrf },
    {
      redirects: 5,
      tags: { endpoint: 'skills_start' },
    }
  );

  check(response, {
    'skills start/take page succeeds': (r) => r.status === 200,
  });

  const match = response.url.match(/\/skills\/attempts\/(\d+)/);
  if (!match) fail(`Could not determine skills attempt id from final URL: ${response.url}`);

  return Number(match[1]);
}

export default function () {
  if (!SKILL_TEST_ID) fail('SKILL_TEST_ID is required.');
  if (__VU > users.length) {
    fail(`Fixture has ${users.length} users but VU ${__VU} was requested. Use at least TARGET_VUS unique staging applicants.`);
  }

  const user = users[__VU - 1];
  const csrf = loginFixture(user);
  const attemptId = startAttempt(user, csrf);

  let responseText = `Load test response from VU ${__VU}. `;

  for (let i = 0; i < SAVES_PER_VU; i++) {
    responseText += `Autosave iteration ${i + 1}. `;

    const started = Date.now();
    const save = http.post(
      `${BASE_URL}/skills/attempts/${attemptId}/inline`,
      JSON.stringify({ inline_response: responseText }),
      {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        tags: { endpoint: 'skill_inline_save' },
      }
    );

    saveLatency.add(Date.now() - started);
    const ok = save.status === 200;
    saveFailures.add(!ok);

    check(save, {
      'skills inline save 200': (r) => r.status === 200,
    });

    sleep(Number(__ENV.THINK_SECONDS || 0.3));
  }
}
