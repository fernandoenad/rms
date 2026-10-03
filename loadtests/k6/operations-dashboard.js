import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const SESSION_COOKIE = __ENV.ADMIN_SESSION_COOKIE || '';
const GROUP_ID = Number(__ENV.GROUP_ID || 0);
const SKILL_TEST_ID = Number(__ENV.SKILL_TEST_ID || 0);
const TARGET_VUS = Number(__ENV.TARGET_VUS || 5);

const dashboardLatency = new Trend('assessment_dashboard_ms', true);

export const options = {
  scenarios: {
    monitoring: {
      executor: 'constant-vus',
      vus: TARGET_VUS,
      duration: __ENV.DURATION || '5m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
    assessment_dashboard_ms: ['p(95)<2500', 'p(99)<5000'],
  },
};

function getPage(path, endpoint) {
  const started = Date.now();
  const response = http.get(`${BASE_URL}${path}`, {
    headers: {
      Cookie: SESSION_COOKIE,
      Accept: 'text/html',
    },
    redirects: 3,
    tags: { endpoint },
  });
  dashboardLatency.add(Date.now() - started);

  check(response, {
    [`${endpoint} returns 200`]: (r) => r.status === 200,
    [`${endpoint} is not login redirect`]: (r) => !r.url.includes('/login'),
  });
}

export default function () {
  if (!SESSION_COOKIE) {
    fail('ADMIN_SESSION_COOKIE is required. Use a disposable staging admin session.');
  }

  getPage('/admin/assessment-center', 'assessment_center');

  if (GROUP_ID) {
    getPage(`/admin/assessment-groups/${GROUP_ID}/results`, 'written_group_results');
  }

  if (SKILL_TEST_ID) {
    getPage(`/admin/skills/${SKILL_TEST_ID}/results`, 'skills_results');
  }

  sleep(Number(__ENV.REFRESH_SECONDS || 5));
}
