# Known Test Issues

As of commit `d12f063` the suite passes **138/138** (382 assertions). The only remaining known issue is the intermittent Windows-only SQLite error below.

---

## 1. `DetentionServiceTest` — intermittent `QueryException: disk I/O error` during `setUp()`

**File:** `api/tests/Unit/DetentionServiceTest.php`

**What's failing:** A random subtest fails with `SQLSTATE[HY000]: General error: 10 disk I/O error` while `RefreshDatabase` runs migrations in `parent::setUp()` (line 23). The specific migration/table that errors (`failed_jobs`, `cache_locks`, `detention_predictions`, `users`, ...) changes between runs, and the number of affected subtests is non-deterministic (observed 5/10, then 1/10, then 0/10 across consecutive reruns of the same file).

**Suspected cause:** SQLite's `:memory:` connection still spills temporary B-tree/journal data to disk (`PRAGMA temp_store`) for larger `CREATE TABLE`/`CREATE INDEX` statements. On this Windows host, the `api/` directory is a Docker Desktop bind mount, and SQLite's POSIX file locking on bind-mounted volumes is unreliable, occasionally surfacing as `SQLITE_IOERR`. A stray `api/attendance_db` SQLite file (now removed) was also being created on disk by some runs, which made the problem worse.

**Fix approach:** Not an application bug — no production code path is involved (failures occur inside Laravel's own migration runner against an in-memory test DB). Possible mitigations for a dedicated task: set `'journal_mode' => 'MEMORY'` and `'synchronous' => 'OFF'` for the `sqlite` connection in `config/database.php` (test-only), or run the Laravel test suite outside the Windows bind mount (e.g. in CI/Linux) where this has not been observed.

---

# Resolved

The four failures below were all symptoms of one bug: `StudentRepository` and `DetentionRepository` hardcoded the read connection as `'mysql::read'`, bypassing the `DB_READ_CONNECTION` test override, so read queries tried to reach MySQL (or a different connection handle) instead of the in-memory SQLite database the tests seeded. Resolved by commit `d12f063` (*refactor(repositories): centralize read-connection resolution in UsesReadConnection trait*), which routes all repository reads through `config('database.read_connection')`.

| # | Test | Symptom |
|---|------|---------|
| 1 | `AttendanceControllerTest > teacher can list students for marking` | `assertJsonCount(2, 'data')` received 0 — roster read went to the hardcoded connection, not the seeded SQLite DB |
| 2 | `DashboardControllerTest > hod sees department summary shape` | HTTP 500 — dashboard aggregate queries failed on the unreachable `mysql::read` connection |
| 3 | `DashboardControllerTest > principal sees college wide summary shape` | HTTP 500 — same code path as #2 |
| 4 | `DetentionControllerTest > hod can list detained students` | `assertJsonPath('meta.total', 1)` received 0 — detention listing read from the hardcoded connection |
