# Attendance Email System — Scalable Architecture v2.0

**Purpose:** This document is the target architecture for refactoring the existing monolithic PHP attendance system into a production-grade, horizontally scalable application. Hand this to the coding agent as the source of truth.

**Migration strategy:** Strangler-fig pattern — keep the existing app running while incrementally replacing pieces. Do NOT rewrite from scratch. Each phase ships independently.

---

## 1. Technology Stack

| Layer | Old | New | Why |
|-------|-----|-----|-----|
| Framework | Raw PHP | **Laravel 11 (PHP 8.3)** | Built-in queue, mail, ORM, auth, validation |
| Auth | PHP Sessions | **JWT (tymon/jwt-auth) + Redis denylist** | Stateless, supports horizontal scaling |
| Database | Single MySQL | **MySQL primary + read replica** | Read/write separation |
| ORM | Raw PDO | **Eloquent (Laravel)** | Migrations, relationships, query builder |
| Cache | None | **Redis 7** | Sessions, hot data, rate limiting |
| Queue | None | **RabbitMQ** (or Redis queue for simpler start) | Async email, async jobs |
| Email Transport | Gmail SMTP (PHPMailer) | **AWS SES** (via Laravel Mail) | 14¢/1000 emails, no daily cap |
| Background Jobs | `cron` shell script | **Laravel Horizon + Supervisor** | Job monitoring, retries, parallelism |
| Web Server | Apache (XAMPP) | **Nginx + PHP-FPM** | Production-grade, reverse proxy |
| Load Balancer | None | **Nginx (upstream block)** or **AWS ALB** | Distribute traffic across app servers |
| CDN/Edge | None | **CloudFlare (free tier)** | TLS, WAF, DDoS protection, static caching |
| Container | None | **Docker + docker-compose** | Reproducible deployments |
| ML Service | None | **Python FastAPI + scikit-learn** | Detention risk prediction (portfolio asset) |
| Object Storage | Local `logs/` | **AWS S3** (or MinIO for dev) | Logs, student photo uploads |
| Monitoring | None | **Sentry (errors) + Grafana (metrics)** | Production observability |
| Secrets | `.env` file | **AWS Secrets Manager** (prod), `.env` (dev) | Don't commit secrets |

---

## 2. Folder Structure

```
attendance-system/
│
├── api/                              # Laravel application (PHP)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── AttendanceController.php
│   │   │   │   ├── StudentController.php
│   │   │   │   ├── SubjectController.php
│   │   │   │   ├── DetentionController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── HodController.php
│   │   │   │   └── PrincipalController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── JwtAuth.php
│   │   │   │   ├── RoleCheck.php
│   │   │   │   ├── RateLimit.php
│   │   │   │   └── ApiVersion.php
│   │   │   └── Requests/             # Form Request validation classes
│   │   ├── Models/                   # Eloquent models
│   │   │   ├── User.php
│   │   │   ├── Student.php
│   │   │   ├── Faculty.php
│   │   │   ├── Subject.php
│   │   │   ├── Attendance.php
│   │   │   └── Detention.php
│   │   ├── Services/                 # Business logic (was src/)
│   │   │   ├── AttendanceService.php
│   │   │   ├── DetentionService.php
│   │   │   ├── EmailService.php
│   │   │   ├── MLPredictionService.php
│   │   │   └── ReportService.php
│   │   ├── Repositories/             # Data access layer
│   │   │   ├── AttendanceRepository.php
│   │   │   ├── StudentRepository.php
│   │   │   └── DetentionRepository.php
│   │   ├── Jobs/                     # Queued jobs
│   │   │   ├── SendAttendanceEmailJob.php
│   │   │   ├── SendDetentionEmailJob.php
│   │   │   ├── GenerateMonthlyReportJob.php
│   │   │   └── PredictDetentionRiskJob.php
│   │   ├── Mail/                     # Mailable classes
│   │   │   ├── DailyAttendanceMail.php
│   │   │   └── DetentionNoticeMail.php
│   │   └── Console/Commands/         # Artisan commands (replaces cron scripts)
│   │       ├── SendDailyAttendance.php
│   │       └── GenerateDetention.php
│   ├── config/                       # Laravel config
│   ├── database/
│   │   ├── migrations/               # Schema migrations
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/
│   │   ├── api.php                   # API routes (versioned /api/v1/...)
│   │   └── web.php
│   ├── tests/
│   │   ├── Unit/
│   │   └── Feature/
│   └── .env.example
│
├── frontend/                         # React SPA (replaces public/*.php pages)
│   ├── src/
│   │   ├── pages/
│   │   ├── components/
│   │   ├── services/api.ts
│   │   └── store/                    # State management
│   ├── public/
│   └── package.json
│
├── ml-service/                       # Python FastAPI microservice
│   ├── app/
│   │   ├── main.py                   # FastAPI entry
│   │   ├── models/
│   │   │   ├── detention_risk.py     # XGBoost / sklearn classifier
│   │   │   └── attendance_anomaly.py # Isolation forest
│   │   ├── routes/
│   │   │   ├── predict.py
│   │   │   └── train.py
│   │   ├── data/loaders.py           # MySQL read replica connection
│   │   └── schemas.py                # Pydantic models
│   ├── notebooks/                    # Training experiments
│   ├── trained_models/               # Pickled model artifacts
│   ├── requirements.txt
│   └── Dockerfile
│
├── infrastructure/
│   ├── docker/
│   │   ├── api.Dockerfile
│   │   ├── worker.Dockerfile
│   │   ├── nginx.conf
│   │   └── php-fpm.conf
│   ├── docker-compose.yml            # Local dev (all services)
│   ├── docker-compose.prod.yml
│   └── k8s/                          # Optional: Kubernetes manifests
│
├── docs/
│   ├── api/openapi.yaml              # OpenAPI 3.0 spec
│   └── architecture.md
│
└── .github/workflows/                # CI/CD
    ├── api-tests.yml
    ├── ml-tests.yml
    └── deploy.yml
```

---

## 3. Database Schema Changes

### Existing tables — modify

```sql
-- users: add JWT-friendly columns
ALTER TABLE users
  ADD COLUMN remember_token VARCHAR(100) NULL,
  ADD COLUMN last_login_at TIMESTAMP NULL,
  ADD COLUMN failed_attempts TINYINT DEFAULT 0,
  ADD COLUMN locked_until TIMESTAMP NULL;

-- students: parent_email becomes mandatory; remove hardcoded recipient
ALTER TABLE students
  MODIFY COLUMN parent_email VARCHAR(255) NOT NULL,
  ADD COLUMN parent_phone VARCHAR(20) NULL,
  ADD COLUMN risk_score DECIMAL(4,3) DEFAULT NULL,  -- ML prediction
  ADD COLUMN risk_updated_at TIMESTAMP NULL;

-- attendance: add indexes + partition prep
ALTER TABLE attendance
  ADD INDEX idx_student_date (student_id, date),
  ADD INDEX idx_subject_date (subject_id, date),
  ADD INDEX idx_date_status (date, status);

-- Partition attendance by month (keeps queries fast as data grows)
ALTER TABLE attendance
  PARTITION BY RANGE (TO_DAYS(date)) (
    PARTITION p_2026_01 VALUES LESS THAN (TO_DAYS('2026-02-01')),
    PARTITION p_2026_02 VALUES LESS THAN (TO_DAYS('2026-03-01')),
    -- add monthly partitions
    PARTITION p_future VALUES LESS THAN MAXVALUE
  );
```

### New tables

```sql
-- Queue job tracking (Laravel manages this, just for reference)
CREATE TABLE jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  INDEX (queue)
);

CREATE TABLE failed_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(255) UNIQUE NOT NULL,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Email delivery tracking
CREATE TABLE email_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(255) NOT NULL,
  subject VARCHAR(500),
  type ENUM('attendance', 'detention', 'system') NOT NULL,
  status ENUM('queued', 'sent', 'failed', 'bounced') NOT NULL,
  ses_message_id VARCHAR(255) NULL,
  related_id BIGINT NULL,         -- student_id or detention_id
  error_message TEXT NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (status, created_at),
  INDEX (recipient)
);

-- API rate limiting (also in Redis, table is for audit)
CREATE TABLE rate_limit_violations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  endpoint VARCHAR(255) NOT NULL,
  attempts INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip_address, created_at)
);

-- Audit log (who did what, when)
CREATE TABLE audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  action VARCHAR(100) NOT NULL,   -- e.g. 'attendance.marked', 'detention.generated'
  entity_type VARCHAR(50),
  entity_id BIGINT,
  metadata JSON,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, created_at),
  INDEX (action, created_at)
);

-- ML predictions storage
CREATE TABLE detention_predictions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT NOT NULL,
  predicted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  risk_score DECIMAL(4,3) NOT NULL,           -- 0.000 to 1.000
  predicted_detention BOOLEAN NOT NULL,
  features_snapshot JSON,                      -- inputs used
  model_version VARCHAR(50) NOT NULL,
  INDEX (student_id, predicted_at),
  INDEX (risk_score)
);
```

---

## 4. Authentication Flow (JWT)

```
[Login Request]
    POST /api/v1/auth/login
    { "email": "...", "password": "..." }
         ↓
    AuthController::login()
         ↓
    Validate credentials (bcrypt verify)
         ↓
    Issue JWT (15-min access token) + Refresh token (7-day, stored in Redis)
         ↓
    Return: { access_token, refresh_token, user: {...} }

[Authenticated Request]
    Header: Authorization: Bearer <jwt>
         ↓
    JwtAuth middleware → decode + verify signature
         ↓
    Check Redis denylist (logged-out tokens)
         ↓
    Load user → attach to request
         ↓
    RoleCheck middleware → verify role allowed for endpoint
         ↓
    Controller method

[Token Refresh]
    POST /api/v1/auth/refresh
    { "refresh_token": "..." }
         ↓
    Verify refresh token in Redis
         ↓
    Issue new access token; rotate refresh token
         ↓
    Return: { access_token, refresh_token }

[Logout]
    POST /api/v1/auth/logout
         ↓
    Add JWT jti to Redis denylist (TTL = remaining token lifetime)
         ↓
    Delete refresh token from Redis
```

**Role-based access:** Middleware `role:admin,teacher` style guards per route, defined in `routes/api.php`.

---

## 5. Email Flow (Async, Queue-Based)

```
[Faculty marks attendance via UI]
    POST /api/v1/attendance
         ↓
    AttendanceController::store()
         ↓
    Validate + persist to MySQL (primary)
         ↓
    Dispatch SendAttendanceEmailJob to "emails" queue → return 200 OK immediately

[Worker process (Laravel Horizon)]
    Picks job from RabbitMQ "emails" queue
         ↓
    SendAttendanceEmailJob::handle()
         ↓
    EmailService → builds DailyAttendanceMail
         ↓
    Laravel Mail → AWS SES API call
         ↓
    Write email_logs row (status='sent', ses_message_id)
         ↓
    On failure: retry 3× with exponential backoff (1m, 5m, 15m)
         ↓
    On final failure: move to failed_jobs + alert via Sentry

[Detention email flow]
    Scheduled monthly: 1st of month, 9 AM
         ↓
    Console\Commands\GenerateDetention dispatches GenerateMonthlyReportJob
         ↓
    Job calculates % for all students → flags those below 75%
         ↓
    For each flagged student: dispatch SendDetentionEmailJob (one per student)
         ↓
    Workers process in parallel (5-10 concurrent)
```

**Key change:** HTTP request returns in <100ms regardless of student count. Email sending happens out-of-band.

**SES setup notes for the agent:**
- Verify sender domain in SES (DKIM, SPF, DMARC)
- Start in sandbox mode for dev; request production access before launch
- Set up SNS topic for bounce/complaint notifications → webhook updates `email_logs`

---

## 6. Caching Strategy (Redis)

| Cache Key Pattern | Data | TTL | Invalidation |
|-------------------|------|-----|--------------|
| `subjects:dept:{dept_id}:sem:{sem}` | Subject list | 1 hour | On subject CRUD |
| `students:dept:{dept_id}:sem:{sem}` | Student roster | 30 min | On student CRUD |
| `faculty:subjects:{faculty_id}` | Subjects faculty teaches | 1 hour | On assignment change |
| `attendance:monthly:{student_id}:{year}-{month}` | Monthly % | 1 hour | On attendance mark |
| `dashboard:hod:{dept_id}` | HOD dashboard summary | 5 min | On attendance mark |
| `jwt:denylist:{jti}` | Logged-out JWT IDs | = remaining token life | Auto-expire |
| `ratelimit:{ip}:{endpoint}` | Request count | 1 min sliding | Auto-expire |
| `session:{user_id}` | Active session metadata | 24 hours | On logout |

**Implementation:** Use Laravel's `Cache` facade with Redis driver. Repository methods check cache first, fallback to MySQL.

---

## 7. API Design (RESTful, Versioned)

**Base URL:** `https://api.attendance.example.com/api/v1`

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/login` | None | Faculty/Admin/HOD login |
| POST | `/auth/principal/login` | None | Principal-specific login |
| POST | `/auth/refresh` | Refresh token | Get new access token |
| POST | `/auth/logout` | JWT | Invalidate token |
| GET | `/auth/me` | JWT | Current user info |

### Attendance

| Method | Endpoint | Roles | Description |
|--------|----------|-------|-------------|
| POST | `/attendance` | teacher | Mark attendance (batch) |
| GET | `/attendance/students` | teacher | Get students for marking |
| GET | `/attendance/monthly/{student_id}` | all | Monthly summary |
| GET | `/attendance/subjects` | teacher | Faculty's subjects |

### Students / Subjects / Faculty

Standard CRUD: `GET /students`, `POST /students`, `PUT /students/{id}`, `DELETE /students/{id}`. Bulk upload: `POST /students/upload` (CSV).

### Reports

| Method | Endpoint | Roles | Description |
|--------|----------|-------|-------------|
| GET | `/reports/dashboard` | all (role-filtered) | Role-specific summary |
| GET | `/reports/detention` | hod, principal | Detention list |
| POST | `/reports/detention/generate` | principal | Trigger generation |
| GET | `/reports/hod/{dept_id}` | hod, principal | HOD dashboard |
| GET | `/reports/principal` | principal | College-wide |

### ML Service (internal API call from Laravel to FastAPI)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/ml/predict/detention-risk` | Predict risk for student(s) |
| POST | `/ml/detect/anomalies` | Detect anomalous attendance patterns |
| GET | `/ml/model/version` | Current model version |

### Response Format

```json
{
  "success": true,
  "data": { ... },
  "meta": { "page": 1, "per_page": 20, "total": 145 }
}
```

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Email is required",
    "details": { "field": "email" }
  }
}
```

**OpenAPI 3.0 spec** at `docs/api/openapi.yaml` — auto-generates client SDKs.

---

## 8. ML Service (Python FastAPI)

### Purpose

Separate Python microservice that the Laravel API calls over HTTP. Keeps ML code isolated from web code (different deploy cadence, different dependencies).

### Stack

- **FastAPI** (web framework)
- **scikit-learn** + **XGBoost** (modeling)
- **pandas** (feature engineering)
- **SQLAlchemy** (read from MySQL replica)
- **joblib** (model serialization)
- **uvicorn** (ASGI server)

### Models

**1. Detention Risk Classifier**
- **Input features (per student):** current attendance %, trend (last 7/14/30 days), department avg %, subject-wise variance, day-of-week patterns, days since last absence streak
- **Output:** `{ risk_score: 0.0-1.0, predicted_detention: bool, confidence: float }`
- **Algorithm:** XGBoost classifier (handles non-linear patterns well)
- **Training:** Weekly retrain on last 2 semesters of data
- **Endpoint:** `POST /predict/detention-risk` `{ student_ids: [1,2,3] }` → array of predictions

**2. Attendance Anomaly Detector**
- **Input:** Per-class attendance vector (which students marked present on which days)
- **Algorithm:** Isolation Forest (unsupervised)
- **Output:** Flagged class sessions with anomaly score
- **Use case:** Catches potential proxy attendance or data entry errors

### Integration Flow

```
Laravel ConcernedController
    ↓
Calls MLPredictionService::predictDetentionRisk($studentId)
    ↓
HTTP POST to http://ml-service:8000/predict/detention-risk
    ↓
FastAPI loads model from /trained_models/detention_v3.joblib
    ↓
Reads student features from MySQL replica
    ↓
Returns prediction JSON
    ↓
Laravel stores in detention_predictions table
    ↓
If risk_score > 0.7 → dispatch early-warning email job
```

### Training Pipeline (Airflow optional; cron-driven to start)

```
Sunday 2 AM:
  1. Extract features → CSV from MySQL replica
  2. Train XGBoost with 80/20 train/val split
  3. Evaluate: precision, recall, F1, AUC
  4. If metrics improve → save as new model version + atomic swap
  5. Log metrics to MLflow (or simple file)
```

---

## 9. Background Jobs & Scheduling

### Laravel Scheduler (`app/Console/Kernel.php`)

```
$schedule->command('attendance:send-daily')->dailyAt('17:00');
$schedule->command('detention:generate')->monthlyOn(1, '09:00');
$schedule->command('ml:predict-risks')->dailyAt('02:00');
$schedule->command('cache:warm')->everyFifteenMinutes();
$schedule->command('email-logs:cleanup')->daily();   // archive >90 days
```

### Job Queues (Horizon)

- **emails** queue: 5 workers, max 60s timeout
- **reports** queue: 2 workers, max 5min timeout (heavy aggregations)
- **ml** queue: 2 workers, max 2min timeout (calls ML service)
- **default** queue: 3 workers, misc tasks

### Retry Policy

- Attempts: 3
- Backoff: `[60, 300, 900]` seconds (1m, 5m, 15m)
- After 3 failures → `failed_jobs` table + Sentry alert

---

## 10. Deployment & Infrastructure

### Local Dev (docker-compose.yml)

Services:
- `nginx` (port 80 → routes to api, frontend)
- `api` (PHP-FPM with Laravel)
- `worker` (same image as api, runs `php artisan horizon`)
- `frontend` (React dev server)
- `ml-service` (FastAPI / uvicorn)
- `mysql-primary` (port 3306)
- `mysql-replica` (port 3307, configured as slave)
- `redis` (port 6379)
- `rabbitmq` (port 5672, management UI on 15672)
- `mailhog` (catches email in dev, port 8025)

### Production (recommended start: AWS, single region)

- **Compute:** ECS Fargate or EC2 with Docker (2× t3.small for api, 1× for worker)
- **Database:** RDS MySQL 8.0 (primary db.t3.small + read replica)
- **Cache:** ElastiCache Redis (cache.t3.micro)
- **Queue:** Amazon MQ (managed RabbitMQ) or skip and use Redis as queue
- **Email:** SES (production access requested)
- **Storage:** S3 bucket for logs + uploads
- **Edge:** CloudFlare (free tier — TLS, WAF, DDoS)
- **Monitoring:** Sentry (errors) + CloudWatch (basic metrics) + Grafana on free tier
- **CI/CD:** GitHub Actions → build images → push to ECR → deploy to ECS

**Estimated monthly cost (low traffic):** ~$45/month
- RDS primary: $15, RDS replica: $15, ElastiCache: $12, S3+SES: <$3, CloudFlare: $0

### Scaling Triggers

- App servers: CPU > 70% for 5 min → scale out
- Queue depth: pending jobs > 100 → spawn extra worker
- DB: replica lag > 30s → investigate, consider beefier instance

---

## 11. Environment Variables (`.env`)

```ini
# App
APP_NAME="Attendance System"
APP_ENV=production
APP_KEY=base64:...
APP_URL=https://api.attendance.example.com

# Database (primary)
DB_CONNECTION=mysql
DB_HOST=mysql-primary.internal
DB_PORT=3306
DB_DATABASE=attendance
DB_USERNAME=app_user
DB_PASSWORD=...

# Database (read replica)
DB_READ_HOST=mysql-replica.internal
DB_READ_PORT=3306

# Redis
REDIS_HOST=redis.internal
REDIS_PORT=6379
REDIS_PASSWORD=...
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Queue
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq.internal
RABBITMQ_PORT=5672
RABBITMQ_USER=...
RABBITMQ_PASSWORD=...

# JWT
JWT_SECRET=...
JWT_TTL=15           # access token: 15 min
JWT_REFRESH_TTL=10080 # refresh: 7 days

# Mail
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-south-1
MAIL_FROM_ADDRESS=noreply@attendance.example.com

# S3
AWS_BUCKET=attendance-logs-prod

# ML Service
ML_SERVICE_URL=http://ml-service:8000
ML_SERVICE_TIMEOUT=10

# Sentry
SENTRY_DSN=...
SENTRY_TRACES_SAMPLE_RATE=0.1
```

---

## 12. Security Hardening Checklist

- [ ] HTTPS only (HSTS header, 1-year max-age)
- [ ] CSRF tokens on all state-changing requests (Laravel default)
- [ ] Rate limiting on `/auth/login` (5 attempts / 15 min per IP)
- [ ] Rate limiting on API (60 req/min authenticated, 20/min unauthenticated)
- [ ] SQL injection: enforced by Eloquent + parameterized queries (no raw concatenation)
- [ ] XSS: React escapes by default; on backend, use Laravel's `e()` helper
- [ ] Password policy: min 10 chars, bcrypt cost 12
- [ ] Account lockout: 5 failed logins → lock 15 min
- [ ] JWT signing: RS256 (asymmetric) in production, not HS256
- [ ] Secrets: `.env` not committed; production uses AWS Secrets Manager
- [ ] Dependency scanning: `composer audit`, `npm audit`, `pip-audit` in CI
- [ ] Input validation: Laravel Form Requests on every endpoint
- [ ] CORS: whitelist frontend origin only
- [ ] Security headers: X-Frame-Options, X-Content-Type-Options, Referrer-Policy
- [ ] Audit log every privileged action (detention generation, bulk uploads)
- [ ] Database backups: daily snapshots, 30-day retention

---

## 13. Migration Plan (Phased, Non-Breaking)

**Run old + new systems in parallel during migration.**

### Phase 1: Foundation (Week 1-2)
1. Set up Laravel project alongside existing PHP code
2. Create Eloquent models matching existing tables
3. Set up Redis (local + dev environment)
4. Configure Docker setup for local development
5. Build OpenAPI spec from existing endpoints

### Phase 2: Auth migration (Week 3)
1. Implement JWT auth in Laravel
2. Build new `/auth/login` endpoint
3. Add JWT validation middleware
4. Migrate one role (start with admin) to JWT; keep others on sessions
5. Validate end-to-end, then migrate remaining roles

### Phase 3: Email queue (Week 4) — **HIGHEST IMPACT**
1. Set up RabbitMQ (or Redis queue)
2. Create `SendAttendanceEmailJob` and `SendDetentionEmailJob`
3. Configure AWS SES, verify domain
4. Replace `trigger_mailer.php` direct calls with `dispatch()`
5. Set up Laravel Horizon for monitoring
6. Run in shadow mode (queue jobs + send via old path) for 1 week
7. Cut over: remove old sending code

### Phase 4: API + frontend (Week 5-7)
1. Build versioned API endpoints (`/api/v1/...`) in Laravel
2. Build React frontend consuming new API
3. Migrate page-by-page: login → dashboard → attendance → reports
4. Keep old PHP pages running for fallback
5. Add CDN (CloudFlare) once stable

### Phase 5: Database scaling (Week 8)
1. Set up MySQL read replica
2. Add indexes per schema spec
3. Configure Laravel to use replica for reads
4. Add partition to attendance table
5. Load test before/after

### Phase 6: ML service (Week 9-10)
1. Bootstrap FastAPI service
2. Extract historical data, engineer features
3. Train initial XGBoost detention model
4. Add `/predict/detention-risk` endpoint
5. Integrate from Laravel — add risk_score to student records
6. Build dashboard widget showing high-risk students

### Phase 7: Production deployment (Week 11)
1. Deploy to AWS (ECS or EC2)
2. Set up CloudFlare, Sentry, monitoring
3. Run smoke tests, load tests
4. DNS cutover (5% → 25% → 100% traffic over 3 days)
5. Decommission old PHP system

### Phase 8: Polish (Week 12)
1. Add automated tests (target 70% coverage on services)
2. Write deployment runbook
3. Document API in OpenAPI
4. Set up alerting (PagerDuty / email / Slack)

---

## 14. Success Metrics

| Metric | Before | Target |
|--------|--------|--------|
| API p99 latency | unknown (likely 2-5s when emailing) | <200ms |
| Email send capacity | ~500/day (Gmail) | 50,000+/day (SES) |
| Concurrent users | ~10 (single Apache) | 500+ (horizontal scale) |
| DB query p95 | unknown | <50ms |
| Deployment time | manual, ~30 min | automated, <5 min |
| Test coverage | 0% | 70%+ on services |
| MTTR (incident) | unknown | <30 min |

---

## 15. What Stays the Same

To keep migration tractable, these don't change:

- Core business rules (75% attendance threshold, role definitions)
- Database table names (just add columns/indexes)
- User-facing terminology (admin / teacher / HOD / principal)
- Detention calculation logic (port to `DetentionService` 1:1)

---

## 16. Out of Scope (for v2.0)

Defer to v3.0:
- Mobile native apps (web responsive is enough)
- Multi-college / multi-tenant
- Real-time WebSocket notifications (use polling first)
- GraphQL API (REST is fine for this scale)
- Kubernetes (ECS is simpler to start)

---

**END OF SPEC.** Pass this document to the coding agent. Start with Phase 1. Do not skip phases.
