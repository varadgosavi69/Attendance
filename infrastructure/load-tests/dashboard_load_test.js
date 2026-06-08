// Phase 5 load test — SCALABLE_ARCHITECTURE.md Section 14 ("Success Metrics").
//
// Hits GET /api/v1/reports/dashboard with 100 concurrent users for 60 seconds
// and reports p50 / p95 / p99 latency, so the same script can be run against
// the pre-Phase-5 code and the post-Phase-5 code (replica + indexes +
// partitioning + caching) for a before/after comparison.
//
// Run with:
//   k6 run -e BASE_URL=http://localhost -e LOAD_TEST_EMAIL=admin@college.edu -e LOAD_TEST_PASSWORD=secret infrastructure/load-tests/dashboard_load_test.js
//
// See infrastructure/load-tests/README.md for the full before/after procedure.

import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const EMAIL = __ENV.LOAD_TEST_EMAIL || 'admin@college.edu';
const PASSWORD = __ENV.LOAD_TEST_PASSWORD || 'password';

export const options = {
  scenarios: {
    dashboard_read: {
      executor: 'constant-vus',
      vus: 100,
      duration: '60s',
    },
  },
  // Informational thresholds — they don't gate the run, they just make k6
  // print pass/fail against the targets from Section 14 of the architecture doc.
  thresholds: {
    'http_req_duration{endpoint:dashboard}': ['p(50)<500', 'p(95)<1000', 'p(99)<2000'],
    'http_req_failed{endpoint:dashboard}': ['rate<0.01'],
  },
};

// Logs in once before the load starts and hands the token to every VU —
// avoids drowning the auth endpoint in 100x login requests.
export function setup() {
  const res = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({ email: EMAIL, password: PASSWORD }),
    { headers: { 'Content-Type': 'application/json' } },
  );

  if (res.status !== 200) {
    throw new Error(
      `Login failed (status ${res.status}): ${res.body}\n` +
      'Set LOAD_TEST_EMAIL / LOAD_TEST_PASSWORD to a seeded account before running this script.',
    );
  }

  const token = res.json('data.access_token');

  if (!token) {
    throw new Error(`Login response had no data.access_token: ${res.body}`);
  }

  return { token };
}

export default function (data) {
  const res = http.get(`${BASE_URL}/api/v1/reports/dashboard`, {
    headers: { Authorization: `Bearer ${data.token}` },
    tags: { endpoint: 'dashboard' },
  });

  check(res, {
    'dashboard responded 200': (r) => r.status === 200,
    'dashboard returned success:true': (r) => {
      try {
        return r.json('success') === true;
      } catch {
        return false;
      }
    },
  });

  sleep(1); // ~1 req/sec/VU — keeps load steady rather than hammering back-to-back
}

// Prints (and saves) a compact p50/p95/p99 summary so before/after runs are
// easy to diff side by side.
export function handleSummary(data) {
  const m = data.metrics;
  const dur = (m['http_req_duration{endpoint:dashboard}'] || m.http_req_duration).values;
  const failed = (m['http_req_failed{endpoint:dashboard}'] || m.http_req_failed).values;

  const summary = {
    endpoint: 'GET /api/v1/reports/dashboard',
    vus: options.scenarios.dashboard_read.vus,
    duration: options.scenarios.dashboard_read.duration,
    requests: m.http_reqs.values.count,
    requests_per_sec: m.http_reqs.values.rate,
    failed_rate: failed.rate,
    latency_ms: {
      p50: dur['p(50)'],
      p95: dur['p(95)'],
      p99: dur['p(99)'],
      avg: dur.avg,
      max: dur.max,
    },
  };

  return {
    stdout: `\n=== Dashboard load test summary ===\n${JSON.stringify(summary, null, 2)}\n`,
    'load-test-results.json': JSON.stringify(summary, null, 2),
  };
}
