# Phase 5 load test — before/after procedure

`dashboard_load_test.js` hits `GET /api/v1/reports/dashboard` with **100
concurrent users for 60 seconds** and reports p50/p95/p99 latency
(SCALABLE_ARCHITECTURE.md Section 14). Run it twice — once against the
pre-Phase-5 code, once against the post-Phase-5 code — to get a real
before/after comparison.

This needs Docker, PHP/Composer, and k6 (or `ab`), none of which are present
in the environment Claude ran in, so **you'll need to run this part
yourself** and paste the results back for review.

## 0. Install k6 (if you don't have it)

```powershell
winget install k6 --source winget
# or: choco install k6
```

No k6? Skip to "Apache Bench alternative" at the bottom — `ab` ships with
WAMP/XAMPP (`C:\wamp64\bin\apache\apache2.x.x\bin\ab.exe`).

## 1. Capture the "before" baseline (pre-Phase-5 code)

```powershell
cd D:\ATTENDANCE\ATTENDANCE\attendance-email-system

# Stash the Phase 5 changes so the working tree matches the last commit
git stash push -u -m "phase-5-wip"

cd infrastructure
docker compose up -d --build
docker compose exec api php artisan migrate --seed   # seed_data.sql gives you a login + some rows

# Make sure there's enough data to make the dashboard query interesting —
# the difference between "before" and "after" only shows up once the
# attendance table has tens of thousands of rows. Adjust the count to taste:
docker compose exec api php artisan tinker --execute="
  \App\Models\Attendance::factory()->count(50000)->create();
"
```

Grab a login for the load test (use a seeded account, e.g. the `admin@college.edu`
/ HOD / principal account from `database/seed_data.sql` — check
`MANUAL_DB_SETUP.txt` if you're not sure of the password), then run:

```powershell
k6 run `
  -e BASE_URL=http://localhost `
  -e LOAD_TEST_EMAIL=admin@college.edu `
  -e LOAD_TEST_PASSWORD=<seeded-password> `
  ..\infrastructure\load-tests\dashboard_load_test.js

# Save the JSON summary so you can diff it later
Move-Item load-test-results.json before.json
```

## 2. Capture the "after" results (Phase 5 code)

```powershell
cd D:\ATTENDANCE\ATTENDANCE\attendance-email-system
git stash pop    # restore the Phase 5 changes

cd infrastructure
docker compose down -v          # fresh primary + replica, replication starts clean
docker compose up -d --build
docker compose exec api php artisan migrate --seed
docker compose exec api php artisan tinker --execute="
  \App\Models\Attendance::factory()->count(50000)->create();
"

# Give the replica a moment to catch up on the bulk insert before testing reads
docker compose exec mysql-replica mysqladmin -uroot -psecret extended-status | findstr Seconds_Behind
```

Run the same command as before (same VUs, duration, account):

```powershell
k6 run `
  -e BASE_URL=http://localhost `
  -e LOAD_TEST_EMAIL=admin@college.edu `
  -e LOAD_TEST_PASSWORD=<seeded-password> `
  ..\infrastructure\load-tests\dashboard_load_test.js

Move-Item load-test-results.json after.json
```

## 3. Report back

Paste both summaries (or just the `latency_ms` block from each) — that's
enough to compute the before/after deltas:

```
                 BEFORE   AFTER
p50 (ms)         ?        ?
p95 (ms)         ?        ?
p99 (ms)         ?        ?
requests/sec     ?        ?
failed rate      ?        ?
```

A few things worth checking if the numbers look off:
- **`failed_rate` near 1.0** usually means the login in `setup()` failed
  (wrong credentials, or migrations/seed didn't run) — check the k6 stderr
  for the thrown error message before trusting the latency numbers.
- **No improvement after Phase 5** can mean the dataset is too small for the
  indexes/partitioning/replica routing to matter — bump the `factory()->count()`
  in step 1/2 (e.g. to 200k+) and rerun both sides.
- **Replica lag** — if `Seconds_Behind_Source` is high during the "after" run,
  reads may return slightly stale data; that's expected under heavy write load
  and is why `sticky => true` is set in `config/database.php`.

## Apache Bench alternative

If k6 isn't available, `ab` can drive the load (though you'll need to fetch
the JWT yourself first, since `ab` can't script a login):

```powershell
# 1. Get a token
$body = '{"email":"admin@college.edu","password":"<seeded-password>"}'
$login = Invoke-RestMethod -Uri http://localhost/api/v1/auth/login -Method Post -Body $body -ContentType application/json
$token = $login.data.access_token

# 2. Write it to a header file ab can read
"Authorization: Bearer $token" | Out-File -Encoding ascii headers.txt

# 3. Run 100 concurrent users against the dashboard for ~60s
#    (-n is total requests, not duration — pick a number that takes ~60s
#    at your observed req/sec, or use -t 60 with a recent ab build)
ab -n 6000 -c 100 -t 60 -H "Authorization: Bearer $token" http://localhost/api/v1/reports/dashboard
```

`ab`'s "Percentage of the requests served within a certain time (ms)" table
gives you the 50%, 95%, and 99% lines directly — that's your p50/p95/p99.
