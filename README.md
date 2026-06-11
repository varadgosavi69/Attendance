# Attendance Management System

Production-grade refactor of a monolithic PHP attendance system into a
Laravel 11 + microservices architecture. Tracks classroom attendance,
queues automated parent emails for absentees, and surfaces detention-risk
predictions via a FastAPI ML service.

## Status

**v1.0.0** — 138 / 138 PHPUnit tests passing, all CI jobs green.

## What changed in the refactor

The original system was raw PHP per page with inline SQL, blocking
PHPMailer SMTP, and no separation of concerns. The refactor follows a
strangler-fig migration into:

- **Laravel 11 API** — JWT auth, Eloquent models, service / repository layers
- **Redis-backed async email queue** via Laravel Horizon (removes SMTP from request path)
- **MySQL primary + read replica** with a `UsesReadConnection` abstraction
- **FastAPI ML microservice** for detention-risk inference
- **Docker Compose** orchestration of all services (7 containers)
- **GitHub Actions CI** with isolated service containers for production parity

Full architecture rationale: [`SCALABLE_ARCHITECTURE.md`](SCALABLE_ARCHITECTURE.md)

## Repository layout

```
.
├── ATTENDANCE/
│   └── attendance-email-system/      ← the project lives here
│       ├── api/                      ← Laravel 11 application
│       ├── ml-service/               ← FastAPI / XGBoost service
│       ├── frontend/                 ← React / TypeScript scaffold
│       ├── infrastructure/           ← Docker Compose, nginx
│       ├── docs/                     ← architecture, known issues, model card
│       └── scripts/                  ← smoke tests, demo seeders
├── SCALABLE_ARCHITECTURE.md          ← migration design doc
├── .github/workflows/                ← CI pipeline
└── README.md
```

## Quick start

```bash
cd ATTENDANCE/attendance-email-system
docker compose -f infrastructure/docker-compose.yml up -d
curl http://localhost/api/v1/health
```

Wait ~30 seconds for all 7 services to become healthy. The smoke test at
`scripts/smoke_test.sh` exercises the full request path.

## Running tests

```bash
# Laravel API — 138 tests, ~95s
cd ATTENDANCE/attendance-email-system/api
php artisan test

# FastAPI ML service — 55 tests, 82% coverage
cd ../ml-service
pytest
```

## Tech stack

| Layer | Choice |
|---|---|
| Backend API | Laravel 11 (PHP 8.3) |
| Auth | tymon/jwt-auth, Redis denylist |
| Queue | Laravel Horizon, Redis driver |
| Email (dev / prod) | MailHog / Resend SMTP |
| Primary DB | MySQL 8 |
| Read replica | MySQL 8 (separate container) |
| ML service | FastAPI, scikit-learn, XGBoost |
| Observability | Sentry |
| Deployment | Docker Compose locally; Railway (optional) |

## Author

Bobby — [@varadgosavi69](https://github.com/varadgosavi69)  
CSE / AIML engineering student, J D College of Engineering & Management.
