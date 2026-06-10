# College Attendance & Detention Management System

A Laravel + FastAPI platform that digitizes student attendance, automates parent
notification emails, and predicts detention risk before students fall below the
75% threshold.

## Problem Statement

Manual, paper-based attendance tracking is slow, error-prone, and gives parents
no visibility into their child's attendance until it's too late. Detention
shortfalls are typically only discovered at the end of a semester, when there
is no time left to intervene. This project replaces that workflow with a
digital attendance system, an asynchronous email pipeline that notifies
parents the same day, and an ML-driven detention-risk score that flags
at-risk students early enough for academic staff to act.

## Architecture Summary

The system is built as a four-tier architecture: an **edge** tier (Nginx,
CloudFlare-ready) that terminates traffic and serves as a reverse proxy; an
**application** tier (the Laravel 13 / PHP 8.3 API, stateless and JWT-authenticated
so it can scale horizontally); an **async** tier (Redis-backed queues processed
by Laravel Horizon, used for outbound email and detention-risk jobs); and a
**data** tier (MySQL 8 with a primary + read replica, plus Redis for cache,
sessions, and the JWT denylist). A separate Python/FastAPI **ML microservice**
sits alongside the data tier, reading from the MySQL replica to score detention
risk with an XGBoost classifier.

Each tier can be scaled or replaced independently — for example, the ML service
can be redeployed with a retrained model without touching the Laravel API, and
read-heavy reporting queries can be pointed at additional replicas without
affecting write throughput. See [`docs/architecture.md`](docs/architecture.md)
for sequence diagrams and the reasoning behind each architectural decision.

```
                 ┌────────────┐
   Browser ─────▶│   Nginx    │
                 └─────┬──────┘
                       │
                       ▼
               ┌───────────────┐        ┌───────────────┐
               │  Laravel API  │◀──────▶│ Redis (cache,  │
               │ (JWT, PHP-FPM)│        │ sessions, queue│
               └───────┬───────┘        │ JWT denylist)  │
                       │                 └───────┬───────┘
                       │                         │
                       │                 ┌───────▼───────┐
                       │                 │ Horizon Worker │
                       │                 │ (email jobs)   │
                       │                 └───────┬───────┘
                       │                         │
                       ▼                         ▼
             ┌─────────────────┐         ┌──────────────┐
             │ MySQL Primary    │────────▶│ MySQL Replica│
             │ (read + write)   │ replic. │ (read-only)  │
             └─────────────────┘         └──────┬───────┘
                                                  │
                                                  ▼
                                         ┌──────────────────┐
                                         │ ML Service        │
                                         │ (FastAPI/XGBoost) │
                                         │ over HTTP         │
                                         └──────────────────┘
```

## Tech Stack

| Layer | Technology | Why |
|-------|------------|-----|
| Application | Laravel 13 / PHP 8.3 | Built-in queue, mail, ORM, validation — minimal boilerplate for a CRUD-heavy domain |
| Auth | JWT (tymon/jwt-auth) + Redis | Stateless auth that scales horizontally; Redis denylist makes logout/token revocation actually work |
| Database | MySQL 8 (primary + replica) | Read/write separation keeps reporting and dashboard queries off the write path |
| Cache | Redis 7 | Sessions, hot data, rate limiting |
| Queue | Redis-driven Laravel Horizon | Async email and ML-prediction jobs with retry, monitoring, and parallelism |
| Email | Resend SMTP (prod) / MailHog (local) | Resend's free tier needs no card or domain verification, ideal for demos; MailHog catches all local mail for inspection |
| ML | FastAPI + XGBoost | Separate microservice so the model can be retrained/redeployed independently of the web app |
| Container | Docker / docker-compose | Reproducible local and production environments across 7 services |
| Edge | Nginx + CloudFlare-ready | Reverse proxy, TLS termination, static caching |
| Monitoring | Sentry | Error tracking for both the Laravel API and the ML service |
| CI | GitHub Actions (planned) | Not yet configured in this repo — see Roadmap |

## Key Features

- JWT authentication with a Redis-backed token denylist for real logout/revocation
- Asynchronous email queue (Laravel Horizon) with retries and a persisted `email_logs` audit trail
- MySQL primary + read replica split, with monthly partitioning on the `attendance` table
- Fully Dockerized 7-service stack (Nginx, Laravel API, Horizon worker, ML service, MySQL primary, MySQL replica, Redis, MailHog)
- FastAPI ML microservice predicting detention risk via XGBoost, with 82% measured test coverage
- 134+ Laravel tests (Feature + Unit) and 55 ML-service tests
- Role-based dashboards for Teacher, HOD, and Principal

## Quick Start

```bash
# 1. Clone the repository
git clone <repo-url>
cd ATTENDANCE/attendance-email-system

# 2. Move into the infrastructure directory
cd infrastructure

# 3. Copy environment files (from the api/ and ml-service/ directories)
cp ../api/.env.example ../api/.env
cp ../ml-service/.env.example ../ml-service/.env

# 4. Start the full stack (Nginx, API, worker, ML service, MySQL x2, Redis, MailHog)
docker-compose up -d

# 5. Wait for MySQL primary/replica health checks to pass
docker-compose ps

# 6. Generate the app key, run migrations, and seed demo data
docker-compose exec api php artisan key:generate
docker-compose exec api php artisan migrate
docker-compose exec api php artisan db:seed

# 7. Smoke test
curl -s http://localhost/api/v1/health | python3 -m json.tool
```

Once running:

- API: http://localhost/api/v1
- MailHog (dev email inbox): http://localhost:8025
- ML service: http://localhost:8000

Demo account credentials are listed in [`docs/demo-credentials.md`](docs/demo-credentials.md).

## Project Structure

```
attendance-email-system/
├── api/                  # Laravel 13 API
│   ├── app/              # Controllers, Models, Services, Repositories, Jobs, Mail
│   ├── config/           # Laravel configuration (database, JWT, services, etc.)
│   ├── database/         # Migrations, seeders, factories
│   ├── routes/           # api.php (versioned /api/v1 routes)
│   └── tests/            # Feature and Unit PHPUnit tests
├── ml-service/           # FastAPI detention-risk microservice
│   ├── app/              # Routes, models, feature engineering, data loaders
│   ├── tests/            # pytest suite
│   └── trained_models/   # Serialized XGBoost model + metrics.json
├── infrastructure/        # docker-compose files, Dockerfiles, Nginx/MySQL config
├── docs/                  # Architecture, model card, demo script, known issues
├── frontend/              # React SPA (frozen for this phase — backend focus)
├── src/, public/, cron/   # Legacy PHP application (pre-migration, not modified)
└── database/              # Legacy SQL schema/seed files
```

## Testing

### Laravel API (PHPUnit)

```bash
docker-compose exec api php artisan test
```

Tests run against SQLite `:memory:` — no MySQL connection required. As of
Phase 8 there are **134 tests** across `tests/Feature` and `tests/Unit`,
covering auth, attendance marking, role-based access, the email queue,
detention scoring, and the Laravel-side ML prediction client. Four
pre-existing failures are documented in
[`docs/known-issues.md`](docs/known-issues.md) and are out of scope for this
phase.

**Coverage**: PCOV/Xdebug are not installed in the API container, so
`--coverage` reports "Code coverage driver not available." Based on the
tests added in Phase 8, estimated coverage of `app/Services` is **~85%**. To
get exact numbers, install [PCOV](https://github.com/krakjoe/pcov) (faster,
recommended for CI) or [Xdebug](https://xdebug.org/docs/install) in the API
container, then run:

```bash
docker-compose exec api php artisan test --coverage
```

### ML Service (pytest)

```bash
docker-compose exec ml-service pytest -v --cov=app --cov-report=term
```

Tests use a synthetic XGBoost model and mock the MySQL data loader — no
database connection required. The suite has **55 tests** with **82%
measured coverage** of `app/`.

## Roadmap / Future Work

- **Train a production XGBoost model** — the current `trained_models/detention_v1.joblib`
  was trained on 21 synthetic samples (see [`docs/ml-model-card.md`](docs/ml-model-card.md)).
  Phase 6 completion is retraining on the seeded 200-student / 6-month dataset.
- **Database indexes and partitioning rollout** — `attendance` table partitioning
  migration exists; Phase 5 completion covers indexing the remaining
  high-traffic query paths and validating partition pruning under load.
- **React frontend** — Phase 4 scope. The `frontend/` SPA scaffold exists but
  is frozen during this backend-focused phase.
- **Production deployment** — Railway configuration exists (`railway.toml`),
  but a full production deploy with managed MySQL/Redis has not been run.
- **CI pipeline** — GitHub Actions workflow for PHPUnit, pytest, and Docker
  image builds is not yet configured.

## Known Issues

See [`docs/known-issues.md`](docs/known-issues.md) for the four pre-existing
test failures (DashboardController 500s on SQLite, DetentionController
pagination) and their suspected causes.

## License & Credits

Licensed under the [MIT License](LICENSE).

Built by Varad Gosavi as a phased modernization of a legacy PHP attendance
system originally built for JD College Engineering & Management.
