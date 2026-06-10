# Known Test Issues

Pre-existing failures that were present before Phase 8. Do not attempt to fix these without a dedicated investigation task.

---

## 1. `AttendanceControllerTest > teacher can list students for marking`

**File:** `api/tests/Feature/AttendanceControllerTest.php`

**What's failing:** `assertJsonCount(2, 'data')` receives 0 instead of 2. The endpoint returns 200 but with an empty `data` array.

**Suspected cause:** The controller or repository uses the `read` database connection (configured via `DB_READ_CONNECTION`). In the test environment, the read connection resolves to SQLite `:memory:`, but the student records may have been inserted on the `default` (write) connection. If the read connection is resolved from a different SQLite handle in memory, the data is invisible.

**Fix approach:** Audit the controller and any injected repository to confirm which connection they read from. If using `DB::connection('read')`, ensure the test environment aliases `read` to the same `:memory:` handle as `default`. The `getEnvironmentSetUp()` in `TestCase.php` already sets `database.read_connection` to `sqlite`; the underlying `connections.read` array entry may also need to be set to `:memory:`.

---

## 2. `DashboardControllerTest > hod sees department summary shape`

**File:** `api/tests/Feature/DashboardControllerTest.php`

**What's failing:** HTTP 500 from the `/dashboard/hod` (or equivalent) endpoint.

**Suspected cause:** The `AttendanceRepository` or a related query builder uses MySQL-specific syntax or calls `information_schema` tables that do not exist in SQLite. Alternatively, the query may use `DATE_FORMAT`, `GROUP_CONCAT`, or other MySQL functions that SQLite does not support.

**Fix approach:** Run the test with `->withExceptions(false)` or add a `->dumpHeaders()` call to expose the exception message. Once the exact failing query is known, either add a SQLite-compatible fallback or use a query macro that switches behaviour based on the database driver (`DB::getDriverName()`).

---

## 3. `DashboardControllerTest > principal sees college wide summary shape`

**File:** `api/tests/Feature/DashboardControllerTest.php`

**What's failing:** HTTP 500 from the principal dashboard endpoint.

**Suspected cause:** Same root cause as issue #2 — the college-wide aggregation query likely uses MySQL-specific functions or `information_schema`. Both HOD and principal summary queries appear to share the same `AttendanceRepository` code path.

**Fix approach:** Same as issue #2. Fix both queries together once the MySQL-specific clause is identified.

---

## 4. `DetentionControllerTest > hod can list detained students`

**File:** `api/tests/Feature/DetentionControllerTest.php`

**What's failing:** `assertJsonPath('meta.total', 1)` receives 0. The endpoint returns 200 with an empty paginated result even after a detained student is seeded.

**Suspected cause:** The detention listing query filters on the `detention_records` table. Either (a) the test seeds a student but the `DetentionService` is not called to populate `detention_records` before the assertion, or (b) the query uses a JOIN or subquery that behaves differently in SQLite (e.g., a `HAVING` clause or a derived-table alias that SQLite rejects silently). A read-connection mismatch (same issue as #1) is also possible.

**Fix approach:** Check what data the test seeds into `detention_records` and confirm the seeded row matches the controller's filter criteria (department, semester, year/month). If the row is missing, the test needs to call `DetentionService::upsert()` or insert directly into `detention_records`. If the row exists but the query returns 0, enable query logging in the test (`DB::enableQueryLog()`) and inspect the generated SQL against SQLite's limitations.

---

## 5. `DetentionServiceTest` — intermittent `QueryException: disk I/O error` during `setUp()`

**File:** `api/tests/Unit/DetentionServiceTest.php`

**What's failing:** A random subtest fails with `SQLSTATE[HY000]: General error: 10 disk I/O error` while `RefreshDatabase` runs migrations in `parent::setUp()` (line 23). The specific migration/table that errors (`failed_jobs`, `cache_locks`, `detention_predictions`, `users`, ...) changes between runs, and the number of affected subtests is non-deterministic (observed 5/10, then 1/10, then 0/10 across consecutive reruns of the same file).

**Suspected cause:** SQLite's `:memory:` connection still spills temporary B-tree/journal data to disk (`PRAGMA temp_store`) for larger `CREATE TABLE`/`CREATE INDEX` statements. On this Windows host, the `api/` directory is a Docker Desktop bind mount, and SQLite's POSIX file locking on bind-mounted volumes is unreliable, occasionally surfacing as `SQLITE_IOERR`. A stray `api/attendance_db` SQLite file (now removed) was also being created on disk by some runs, which made the problem worse.

**Fix approach:** Not an application bug — no production code path is involved (failures occur inside Laravel's own migration runner against an in-memory test DB). Possible mitigations for a dedicated task: set `'journal_mode' => 'MEMORY'` and `'synchronous' => 'OFF'` for the `sqlite` connection in `config/database.php` (test-only), or run the Laravel test suite outside the Windows bind mount (e.g. in CI/Linux) where this has not been observed.
