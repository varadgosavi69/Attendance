# 2-Minute Interview Demo Script

A walkthrough you can run live in front of an interviewer. Each section has a cue, what to show, and what to say.

---

## Before the call (setup)

```bash
# Ensure the stack is running
cd ATTENDANCE/attendance-email-system
docker-compose -f infrastructure/docker-compose.yml up -d

# Confirm health
curl -s http://localhost:8000/api/v1/health | python3 -m json.tool
```

Have open in your browser:
- http://localhost:3000 (React frontend)
- http://localhost:8025 (MailHog)
- A terminal with the project root

---

## Section 1 — Architecture overview (20 sec)

**What to show:** The repo in your IDE or the folder structure.

**What to say:**
> "This is a full-stack attendance management system for a college — it's an 8-phase modernization of a legacy PHP monolith.
> The stack is Laravel 11 API with JWT auth, a React SPA, a Python FastAPI microservice for ML-based detention prediction,
> and a MySQL primary/replica setup. Everything runs in Docker and is deployable to Railway."

---

## Section 2 — Health endpoint (20 sec)

**What to show:** Run in terminal:

```bash
curl -s http://localhost:8000/api/v1/health | python3 -m json.tool
```

**What to say:**
> "I added a health-check endpoint that verifies all four sub-systems at once — database, Redis, the queue connection, and the ML service.
> If any one of them is down, the endpoint returns HTTP 503 so a load balancer or CI smoke test can detect degradation immediately."

**What it looks like (expected output):**
```json
{
  "success": true,
  "data": {
    "status": "ok",
    "checks": {
      "database": { "status": "ok" },
      "redis":    { "status": "ok" },
      "queue":    { "status": "ok", "pending_jobs": 0 },
      "ml":       { "status": "ok", "model_loaded": true }
    }
  }
}
```

---

## Section 3 — JWT authentication (25 sec)

**What to show:** Run in terminal:

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@jdcollege.edu.in","password":"password"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['access_token'])")

echo "Token: ${TOKEN:0:40}..."

curl -s -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/auth/me | python3 -m json.tool
```

**What to say:**
> "Authentication is JWT — stateless, so the API scales horizontally without sticky sessions.
> The access token lives 15 minutes; refresh tokens are 7-day and stored in a Redis denylist,
> so logout actually works — not just client-side token deletion."

---

## Section 4 — ML detention risk (25 sec)

**What to show:** Navigate to `ml-service/` and run:

```bash
curl -s http://localhost:8001/health | python3 -m json.tool
```

Then show the notebook or trained model file:

```bash
ls ml-service/trained_models/
# → detention_v1.joblib  metrics.json

cat ml-service/trained_models/metrics.json
```

**What to say:**
> "Phase 6 added a Python FastAPI microservice that runs an XGBoost classifier to predict which students are at risk of detention
> before they actually fall below the 75% threshold. Laravel calls it async via a queued job so the HTTP response
> isn't blocked. The model is retrained weekly on historical data. It's a separate container so the ML team
> can update the model independently of the web app."

---

## Section 5 — Queue and email (15 sec)

**What to show:** Open MailHog at http://localhost:8025.

Then trigger a test email (or show the Horizon dashboard if available at http://localhost:8000/horizon):

```bash
curl -s -X POST http://localhost:8000/api/v1/attendance \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"subject_id":1,"date":"2026-06-09","records":[{"student_id":1,"status":"absent"}]}'
```

**What to say:**
> "Attendance emails are dispatched to a Redis-backed queue and processed by Laravel Horizon.
> The HTTP response returns immediately — the interviewer sees 200 OK in under 100ms while
> the worker sends the email in the background. In production this swaps to Resend's SMTP gateway."

---

## Section 6 — CI pipeline (15 sec)

**What to show:** Open GitHub Actions tab on the repo (or show the `.github/workflows/ci.yml` file).

**What to say:**
> "Every push runs PHPUnit, the React build + lint, and pytest for the ML service.
> Then it builds all four Docker images to catch Dockerfile regressions.
> Nothing deploys automatically — Railway is triggered separately on merge to main.
> The test matrix runs in parallel so the whole CI pipeline finishes in about 3 minutes."

---

## Fallback — if something is broken

| Problem | Fallback |
|---------|----------|
| Docker is down | Open `SCALABLE_ARCHITECTURE.md` — walk through the architecture diagram in the doc instead |
| DB not seeded | `docker-compose exec api php artisan db:seed` then retry |
| ML service not responding | Show `ml-service/app/routes/predict.py` and `trained_models/metrics.json` instead |
| JWT login fails | Show the `AuthController.php` code and explain the flow |
| MailHog empty | Point to the `SendAttendanceEmailJob.php` and explain the async dispatch pattern |

---

## Talking points (if asked to go deeper)

- **Why Laravel over Node.js?** — Built-in Eloquent ORM, Horizon for queues, Form Requests for validation — less boilerplate for a CRUD-heavy domain.
- **Why JWT over sessions?** — Stateless auth supports horizontal scaling; Redis denylist solves the "you can't revoke a JWT" problem.
- **Why XGBoost for detention?** — Handles non-linear patterns in attendance trend data; interpretable feature importances; trains in seconds on this dataset size.
- **Why read replica?** — Keeps analytics/reporting queries off the write path; `config/database.php` routes SELECT queries automatically.
- **Why Resend over SES?** — Free tier (3k/month), no card, no domain verification required for sandbox — faster to demo.
