# Architecture

This document describes the four-tier architecture of the Attendance &
Detention Management System and the reasoning behind its key design
decisions. For a high-level diagram and quick start, see the
[project README](../README.md).

## The Four Tiers

**Edge.** Nginx terminates incoming HTTP traffic and reverse-proxies it to the
Laravel API container. It is the only service exposed on port 80, and is
configured to be CloudFlare-ready (so TLS, WAF, and static-asset caching can
be added in front of it without changing application code).

**Application.** The Laravel 13 / PHP 8.3 API (`api/`) is stateless — every
request is authenticated via a JWT bearer token, so no session affinity is
required and the API container can be scaled horizontally behind Nginx.
Controllers live under `app/Http/Controllers/Api/V1`, business logic in
`app/Services`, and data access in `app/Repositories`.

**Async.** Redis backs both the cache and the queue. Laravel Horizon runs as
a separate worker container, processing two kinds of background work:
outbound parent-notification emails (`SendAttendanceEmailJob`,
`SendDetentionEmailJob`) and detention-risk prediction calls to the ML
service. Offloading these to a queue keeps API response times low and makes
email delivery retryable.

**Data.** MySQL 8 runs as a primary (read/write) plus a read replica.
`config/database.php` defines a named `read` connection (`mysql::read`) that
`AttendanceRepository` and reporting queries use explicitly; the connection
is marked `sticky` so a request that just wrote data reads its own write back
from the primary rather than a potentially-lagging replica. The `attendance`
table is partitioned by month (see
`api/database/migrations/2026_06_08_100100_partition_attendance_table_by_month.php`)
so dashboard and monthly-report queries only scan relevant partitions.

**ML microservice.** A separate FastAPI application (`ml-service/`) reads
from the MySQL replica and serves an XGBoost classifier over HTTP. Laravel's
`MLPredictionService` calls it via `Http::baseUrl(config('services.ml_service.url'))`
and persists the results to the `detention_predictions` table. Running this
as its own container means the model can be retrained and redeployed
independently of the web application.

## Sequence Diagrams

### 1. Login flow (JWT issuance + Redis refresh storage)

```mermaid
sequenceDiagram
    participant Client
    participant Nginx
    participant API as Laravel API (AuthController)
    participant DB as MySQL Primary
    participant Redis

    Client->>Nginx: POST /api/v1/auth/login {email, password}
    Nginx->>API: forward request
    API->>DB: SELECT user WHERE email/username = ?
    DB-->>API: user row (password_hash, locked_until)
    API->>API: check lockout, Hash::check(password)
    API->>API: JWTAuth::fromUser(user) -> access_token (15 min TTL)
    API->>API: generate refresh_token, store in Redis (7 day TTL)
    API->>Redis: SETEX refresh:{token} 604800 user_id
    API-->>Client: 200 {access_token, refresh_token, expires_in, user}

    Note over Client,API: Subsequent requests
    Client->>API: Authorization: Bearer {access_token}
    API->>API: validate JWT signature + expiry

    Note over Client,Redis: Logout
    Client->>API: POST /api/v1/auth/logout
    API->>Redis: add access token jti to denylist (TTL = remaining token life)
    API-->>Client: 200 {success: true}
```

### 2. Attendance marking → queue → worker → SMTP → email_logs

```mermaid
sequenceDiagram
    participant Teacher as Teacher (Client)
    participant API as Laravel API (AttendanceController)
    participant DB as MySQL Primary
    participant EmailSvc as EmailService
    participant Redis as Redis Queue
    participant Worker as Horizon Worker
    participant SMTP as MailHog / Resend SMTP

    Teacher->>API: POST /api/v1/attendance {subject_id, date, records[]}
    API->>DB: upsert attendance rows (per student)
    API->>EmailSvc: queueDailyAttendance(student, attendanceData, date)
    EmailSvc->>DB: INSERT email_logs (status=queued)
    EmailSvc->>Redis: dispatch SendAttendanceEmailJob onQueue('emails')
    API-->>Teacher: 200 OK (returns immediately)

    Redis->>Worker: pop SendAttendanceEmailJob
    Worker->>DB: load student + email_log row
    Worker->>SMTP: send attendance email to parent_email
    alt send succeeds
        Worker->>DB: UPDATE email_logs SET status=sent
    else send fails
        Worker->>DB: UPDATE email_logs SET status=failed, attempts++
        Worker->>Redis: re-queue (Horizon retry policy)
    end
```

### 3. Detention prediction call (Laravel → FastAPI → DB → dashboard)

```mermaid
sequenceDiagram
    participant Principal as Principal/HOD (Client)
    participant API as Laravel API (DetentionController)
    participant MLSvc as MLPredictionService
    participant FastAPI as ML Service (FastAPI/XGBoost)
    participant Replica as MySQL Replica
    participant Primary as MySQL Primary

    Principal->>API: POST /api/v1/detention/generate
    API->>MLSvc: predictDetentionRisk(studentIds[])
    MLSvc->>FastAPI: POST /predict/detention-risk {student_ids}
    FastAPI->>Replica: load attendance history for student_ids
    FastAPI->>FastAPI: feature engineering + XGBoost.predict_proba
    FastAPI-->>MLSvc: {predictions[], skipped_student_ids[], model_version}
    MLSvc->>Primary: INSERT/UPDATE detention_predictions per student
    MLSvc-->>API: Collection<DetentionPrediction>
    API-->>Principal: 200 {data: [...]}

    Note over Principal,Primary: Dashboard read (separate request)
    Principal->>API: GET /api/v1/detention
    API->>Replica: SELECT FROM detention_records / detention_predictions
    Replica-->>API: rows
    API-->>Principal: 200 {data: [...], meta: {total}}
```

## Decisions

**Why JWT over sessions?** The API needs to scale horizontally behind Nginx
without sticky sessions or a shared session store being a hard dependency.
JWT access tokens are short-lived (15 minutes) and self-contained, so any API
instance can validate a request without a round trip. The well-known downside
— you can't revoke a JWT before it expires — is solved with a Redis-backed
denylist that `logout` writes to, checked on every authenticated request.

**Why a queue over synchronous email?** Sending email synchronously inside
the `POST /attendance` request would tie the response time to SMTP latency
and any transient mail-server failures. Dispatching `SendAttendanceEmailJob`
to a Redis queue lets the API return immediately, and Horizon's retry policy
absorbs transient SMTP failures without the teacher seeing an error. The
`email_logs` table gives an audit trail of what was sent, queued, or failed.

**Why a read replica?** Dashboard and reporting queries (HOD/Principal
summaries, detention listings) can be expensive aggregations. Routing them
through the named `read` connection keeps that load off the primary, which
needs to stay responsive for attendance writes. The `sticky` flag avoids the
classic "I just submitted attendance and it's not showing up yet" problem
caused by replication lag.

**Why a separate microservice for ML?** Keeping the XGBoost model in a
Python/FastAPI service rather than embedding it in Laravel means the model
can be retrained and redeployed on its own schedule, by a different
toolchain (scikit-learn/XGBoost/pandas), without rebuilding or restarting the
PHP application. It also isolates a Python dependency tree from the PHP one,
and the read-only replica connection means the ML service cannot accidentally
write to production data.
