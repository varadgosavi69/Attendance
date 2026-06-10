# 3-Minute Interview Demo Script

A live walkthrough for an interview or portfolio review. Follow the timeline
top to bottom — each row is roughly the stated duration. Have a terminal,
this repo, and a browser ready before you start.

**Before the call:**

```bash
cd ATTENDANCE/attendance-email-system/infrastructure
docker-compose up -d
docker-compose ps   # confirm all 7 containers are healthy
```

Have these open in your browser: `docs/architecture.md`, Horizon
(`http://localhost/horizon`), and MailHog (`http://localhost:8025`).

| Time | Action | Words to say |
|------|--------|---------------|
| 0:00–0:30 | Open `docs/architecture.md`. Point at the ASCII diagram. | "This is a four-tier system: Nginx at the edge, a stateless Laravel API behind it, a Redis-backed async tier running Horizon for queued jobs, and a MySQL primary/replica data tier. There's also a separate FastAPI microservice for ML detention-risk scoring that talks to the replica over HTTP." |
| 0:30–0:45 | In a terminal: `docker-compose ps` | "Here's the running stack — Nginx, the Laravel API, a Horizon worker, two MySQL instances (primary and read replica), Redis, MailHog for catching dev email, and the ML service. Because the API is stateless and JWT-authenticated, I could put a load balancer in front of multiple API containers and scale horizontally without touching session state." |
| 0:45–1:15 | `curl -s -X POST http://localhost/api/v1/auth/login -H "Content-Type: application/json" -d '{"email":"admin@jdcollege.edu.in","password":"password"}' \| python3 -m json.tool` | "Logging in returns a short-lived 15-minute access token plus a 7-day refresh token. The refresh token is tracked in Redis, and logout adds the access token's JTI to a Redis denylist — so unlike plain JWT, logout actually revokes the token instead of just deleting it client-side." |
| 1:15–2:00 | `curl -X POST http://localhost/api/v1/attendance -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"subject_id":1,"date":"2026-06-10","records":[{"student_id":1,"status":"absent"}]}'` then switch to Horizon, then MailHog | "Marking a student absent writes the attendance row and queues a `SendAttendanceEmailJob` — the API responds immediately. Here in Horizon you can see the job get picked up and processed by the worker. And here in MailHog is the resulting parent-notification email, with the `email_logs` row marked as sent. In production this same job sends through Resend's SMTP instead of MailHog." |
| 2:00–2:30 | `curl -s -X POST http://localhost:8000/predict/detention-risk -H "Content-Type: application/json" -d '{"student_ids":[1,2,3]}' \| python3 -m json.tool` | "This calls the FastAPI ML service directly — it builds seven features per student: current attendance %, three trend windows, department average, subject-variance, and longest absence streak, then runs them through an XGBoost classifier. **Note:** the model checked into this repo is currently trained on a 21-row synthetic dataset for development — retraining on the full seeded 200-student dataset is the next piece of Phase 6 work, tracked in the README roadmap and model card." |
| 2:30–2:50 | `curl -s -H "Authorization: Bearer $TOKEN" http://localhost/api/v1/reports/detention \| python3 -m json.tool` | "This is the detention report endpoint — HODs and the principal use it to see which students in their department have crossed below the 75% threshold, paginated with a `meta.total` count. If the predictions endpoint above is wired into the dashboard, this is also where the model's risk scores would surface for proactive outreach, ahead of the actual threshold being crossed." |
| 2:50–3:00 | Switch back to `docs/architecture.md` or the README roadmap section | "The biggest open items are retraining the detention model on real seeded data, finishing the read-replica indexing/partitioning work, and building out the React frontend — the API and ML service are the parts I focused on for this phase." |

## If something fails live

| Problem | What to say / do |
|---------|-------------------|
| A container restarts or shows unhealthy mid-demo | "One of the containers is restarting — that's actually a good moment to point at the Horizon/health-check setup: in production this would trigger an alert via Sentry rather than silently failing." Run `docker-compose ps` again after a few seconds; most services recover on their own. If not, fall back to walking through `docs/architecture.md` and the code instead of live calls. |
| Internet is slow / curl hangs | Everything in this demo runs against `localhost` — no external network calls are required except the optional Resend SMTP in production (which isn't used locally; MailHog is). If a command hangs, Ctrl+C and re-run; if it's still slow, narrate from the code instead (`AttendanceController.php`, `SendAttendanceEmailJob.php`). |
| ML service isn't responding / model not loaded | Skip the live `curl` and instead open `ml-service/app/models/detention_risk.py` to walk through the feature list, and `ml-service/trained_models/metrics.json` to show the last training run. Be upfront: "the model artifact here is a development placeholder — it hasn't been retrained on the seeded dataset yet." |
| MailHog inbox is empty | Point at `app/Jobs/SendAttendanceEmailJob.php` and `email_logs` table schema instead, and explain the dispatch flow from `docs/architecture.md`'s sequence diagram. |
| JWT login fails (wrong seed data, etc.) | Open `docs/demo-credentials.md` to confirm the exact seeded email/password, or fall back to `AuthController.php` and walk through the login → JWT → Redis denylist flow from the architecture doc. |
