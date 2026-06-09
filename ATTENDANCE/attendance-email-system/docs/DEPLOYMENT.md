# Attendance System — Deployment Guide

Three ways to run this project:

| Mode | Command | Use for |
|------|---------|---------|
| **Dev** | `docker-compose up` | Day-to-day development, hot reload |
| **Local prod** | `docker-compose -f infrastructure/docker-compose.prod.yml up` | Testing production images locally |
| **Railway** | Push to GitHub | Live demo, sharing with interviewers |

---

## Prerequisites

- Docker Desktop ≥ 4.x with Compose v2 (`docker compose version`)
- 4 GB RAM allocated to Docker (adjust in Docker Desktop → Settings → Resources)
- The following ports must be free before starting: `80`, `3306`, `3307`, `6379`, `8000`

---

## 1. Run Locally with Dev Tooling

Full stack with hot reload, MailHog for email, and source-code bind mounts.

### First-time setup

```bash
cd ATTENDANCE/attendance-email-system

# Copy env template
cp api/.env.example api/.env

# Start all services
docker-compose -f infrastructure/docker-compose.yml up -d

# Run migrations + generate keys (first time only)
docker-compose -f infrastructure/docker-compose.yml exec api php artisan key:generate
docker-compose -f infrastructure/docker-compose.yml exec api php artisan jwt:secret
docker-compose -f infrastructure/docker-compose.yml exec api php artisan migrate --force
docker-compose -f infrastructure/docker-compose.yml exec api php artisan db:seed
```

### Service URLs

| Service | URL | Notes |
|---------|-----|-------|
| Laravel API | http://localhost:8000/api/v1 | Direct PHP-FPM via nginx |
| React frontend | http://localhost:3000 | Vite dev server with HMR |
| FastAPI ML service | http://localhost:8001 | Uvicorn dev server |
| MailHog (email UI) | http://localhost:8025 | Catches all outgoing email |
| MySQL primary | localhost:3306 | User: root, Password: from `.env` |
| MySQL replica | localhost:3307 | Read-only |
| Redis | localhost:6379 | |

### Day-to-day commands

```bash
# Start / stop
docker-compose -f infrastructure/docker-compose.yml up -d
docker-compose -f infrastructure/docker-compose.yml down

# Tail logs
docker-compose -f infrastructure/docker-compose.yml logs -f api
docker-compose -f infrastructure/docker-compose.yml logs -f worker

# Run artisan commands
docker-compose -f infrastructure/docker-compose.yml exec api php artisan <command>

# Open a shell in the api container
docker-compose -f infrastructure/docker-compose.yml exec api sh
```

---

## 2. Run Locally in Production Mode

Builds production Docker images (code baked in, no source mounts, OPcache enabled).
Uses Resend for transactional email instead of MailHog.

### Setup

**Step 1** — Create `infrastructure/.env.prod` from the template below:

```ini
# ── App ───────────────────────────────────────────────────────────────────────
APP_KEY=                    # php artisan key:generate --show
APP_URL=http://localhost
APP_ENV=production

# ── Database ──────────────────────────────────────────────────────────────────
DB_DATABASE=attendance_db
DB_USERNAME=appuser
DB_PASSWORD=change_me_strong_password
DB_ROOT_PASSWORD=change_me_root_password

# ── Redis ─────────────────────────────────────────────────────────────────────
REDIS_PASSWORD=

# ── JWT ───────────────────────────────────────────────────────────────────────
JWT_SECRET=                 # php artisan jwt:secret --show

# ── Email (Resend) ────────────────────────────────────────────────────────────
# Free tier: 3,000 emails/month. Sign up at https://resend.com
RESEND_API_KEY=re_your_key_here
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# ── Business rules ────────────────────────────────────────────────────────────
DETENTION_THRESHOLD=75

# ── Sentry (optional — leave empty to disable) ────────────────────────────────
SENTRY_LARAVEL_DSN=
SENTRY_ML_DSN=
```

**Step 2** — Generate the missing secrets:

```bash
# APP_KEY
docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# JWT_SECRET (any long random string)
openssl rand -hex 64
```

**Step 3** — Build and start:

```bash
cd ATTENDANCE/attendance-email-system

docker-compose -f infrastructure/docker-compose.prod.yml \
  --env-file infrastructure/.env.prod \
  build

docker-compose -f infrastructure/docker-compose.prod.yml \
  --env-file infrastructure/.env.prod \
  up -d
```

**Step 4** — Initialize (first time only):

```bash
docker-compose -f infrastructure/docker-compose.prod.yml exec api \
  php artisan migrate --force

docker-compose -f infrastructure/docker-compose.prod.yml exec api \
  php artisan db:seed
```

### Service URLs (production mode)

| Service | URL |
|---------|-----|
| Full app | http://localhost |
| API health | http://localhost/api/v1/health |

---

## 3. Deploy to Railway

Railway is a PaaS that deploys directly from GitHub. Each microservice is a separate Railway service within one project. The free tier (Hobby plan) covers all four services for a demo.

### Step 1 — Create a Railway project

1. Sign in at [railway.app](https://railway.app)
2. Click **New Project** → **Deploy from GitHub repo**
3. Authorize Railway and select this repository

### Step 2 — Add managed databases

Inside the project:

1. **+ New** → **Database** → **MySQL 8**
   - After creation, open the MySQL service → **Variables** tab
   - Note `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`

2. **+ New** → **Database** → **Redis**
   - Note `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` from Variables

### Step 3 — Deploy the API service

1. **+ New** → **GitHub Repo** → select this repo
2. In the service settings:
   - **General → Root Directory**: `ATTENDANCE/attendance-email-system/api`
   - **Build → Dockerfile Path**: `../infrastructure/docker/api.Dockerfile`
   - **Deploy → Health Check Path**: `/api/v1/health`
3. Add these environment variables (Variables tab):

```ini
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # generate locally: php artisan key:generate --show
APP_URL=                      # your Railway domain e.g. https://api-xxxx.up.railway.app

DB_CONNECTION=mysql
DB_HOST=                      # MYSQL_HOST from database service
DB_PORT=3306
DB_DATABASE=                  # MYSQL_DATABASE
DB_USERNAME=                  # MYSQL_USER
DB_PASSWORD=                  # MYSQL_PASSWORD
DB_READ_HOST=                 # same as DB_HOST (no replica in Railway free tier)
DB_READ_PORT=3306

REDIS_HOST=                   # from Redis service
REDIS_PORT=6379
REDIS_PASSWORD=               # from Redis service
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

JWT_SECRET=                   # openssl rand -hex 64
JWT_TTL=15
JWT_REFRESH_TTL=10080

MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=                # your Resend API key (re_xxxx)
MAIL_FROM_ADDRESS=            # verified sender in Resend account
MAIL_ENCRYPTION=tls

ML_SERVICE_URL=               # fill in after Step 5
DETENTION_THRESHOLD=75

SENTRY_LARAVEL_DSN=           # optional
```

### Step 4 — Deploy the Worker service

1. **+ New** → **GitHub Repo** → same repo
2. Service settings:
   - **Root Directory**: `ATTENDANCE/attendance-email-system/api`
   - **Dockerfile Path**: `../infrastructure/docker/worker.Dockerfile`
   - No health check (background process)
3. Copy all environment variables from the API service (same config)

### Step 5 — Deploy the ML service

1. **+ New** → **GitHub Repo** → same repo
2. Service settings:
   - **Root Directory**: `ATTENDANCE/attendance-email-system/ml-service`
   - **Dockerfile Path**: `../infrastructure/docker/ml-service.Dockerfile`
   - **Health Check Path**: `/health`
3. Environment variables:

```ini
DB_READ_HOST=                 # same as API's DB_HOST
DB_READ_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DETENTION_THRESHOLD=75
SENTRY_DSN=                   # optional
```

4. After deployment, copy the Railway-assigned domain (e.g. `https://ml-xxxx.up.railway.app`)
5. Go back to the API service → Variables → set `ML_SERVICE_URL` to this domain

### Step 6 — Deploy the Frontend service

1. **+ New** → **GitHub Repo** → same repo
2. Service settings:
   - **Root Directory**: `ATTENDANCE/attendance-email-system/frontend`
   - **Dockerfile Path**: `../infrastructure/docker/frontend.Dockerfile`
3. Build arguments:

```ini
VITE_API_BASE_URL=            # your API Railway domain
```

### Step 7 — Initialize the database

Use the Railway CLI or the in-dashboard shell on the API service:

```bash
# Install Railway CLI
npm install -g @railway/cli

# Log in and link
railway login
railway link

# Run migrations
railway run --service attendance-api php artisan migrate --force
railway run --service attendance-api php artisan db:seed
railway run --service attendance-api php artisan jwt:secret
```

---

## Verifying Health Endpoints

### API health

```bash
curl https://your-api.railway.app/api/v1/health | python3 -m json.tool
```

Expected response (`200 OK`):

```json
{
  "success": true,
  "data": {
    "status": "ok",
    "version": "2.0.0",
    "checks": {
      "database": { "status": "ok" },
      "redis":    { "status": "ok" },
      "queue":    { "status": "ok", "pending_jobs": 0 },
      "ml":       { "status": "ok", "model_loaded": true }
    }
  }
}
```

If any check shows `"status": "error"`, look at the `"message"` field — it contains the exception text.

### ML service health

```bash
curl https://your-ml.railway.app/health
# → {"status":"ok","model_loaded":true,"model_version":"detention_v1"}
```

### Automated smoke test

```bash
chmod +x scripts/smoke_test.sh

# Local prod
./scripts/smoke_test.sh

# Railway
BASE_URL=https://your-api.railway.app ./scripts/smoke_test.sh
```

---

## Viewing Logs

### Local docker-compose (dev)

```bash
docker-compose -f infrastructure/docker-compose.yml logs -f           # all services
docker-compose -f infrastructure/docker-compose.yml logs -f api        # Laravel
docker-compose -f infrastructure/docker-compose.yml logs -f worker     # Horizon
docker-compose -f infrastructure/docker-compose.yml logs -f ml-service # FastAPI
```

### Local docker-compose (prod)

```bash
docker-compose -f infrastructure/docker-compose.prod.yml \
  --env-file infrastructure/.env.prod logs -f
```

### Railway

Dashboard → select service → **Logs** tab.

Or via CLI:

```bash
railway logs --service attendance-api
railway logs --service attendance-worker
railway logs --service attendance-ml
```

---

## Environment Variables Reference

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_KEY` | ✅ | — | Laravel app key — `php artisan key:generate --show` |
| `APP_URL` | ✅ | `http://localhost` | Public URL of the API |
| `DB_HOST` | ✅ | — | MySQL primary host |
| `DB_DATABASE` | ✅ | `attendance_db` | Database name |
| `DB_USERNAME` | ✅ | — | DB user |
| `DB_PASSWORD` | ✅ | — | DB password |
| `DB_READ_HOST` | ❌ | `= DB_HOST` | MySQL read replica (falls back to primary if unset) |
| `REDIS_HOST` | ✅ | — | Redis host |
| `REDIS_PASSWORD` | ❌ | — | Redis password (empty = no auth) |
| `JWT_SECRET` | ✅ | — | JWT signing secret — `openssl rand -hex 64` |
| `RESEND_API_KEY` | ✅ prod | — | Resend API key — free at resend.com (≤ 3k emails/month) |
| `MAIL_FROM_ADDRESS` | ✅ prod | — | Verified sender address in your Resend account |
| `ML_SERVICE_URL` | ✅ | `http://ml-service:8000` | URL of the FastAPI ML service |
| `DETENTION_THRESHOLD` | ❌ | `75` | Attendance % below which detention is triggered |
| `SENTRY_LARAVEL_DSN` | ❌ | — | Sentry DSN for Laravel — leave empty to disable |
| `SENTRY_ML_DSN` | ❌ | — | Sentry DSN for ML service — leave empty to disable |
