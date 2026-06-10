<?php

namespace App\Repositories;

use App\Models\Student;
use App\Repositories\Concerns\UsesReadConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-heavy student queries (rosters, dashboard counts). Pinned to the
 * `mysql::read` connection and cached per SCALABLE_ARCHITECTURE.md Section 6.
 */
class StudentRepository
{
    use UsesReadConnection;

    /**
     * Roster for a department + semester — populates the "mark attendance" student list.
     *
     * Cache: students:dept:{dept_id}:sem:{sem} — 30 minutes, invalidated on student CRUD.
     */
    public function rosterForClass(string $department, int $semester): Collection
    {
        return Cache::remember(
            "students:dept:{$department}:sem:{$semester}",
            now()->addMinutes(30),
            fn () => Student::on(self::readConnection())
                ->select('student_id', 'student_name', 'roll_number')
                ->where('department', $department)
                ->where('semester', $semester)
                ->orderBy('roll_number')
                ->get()
        );
    }

    /**
     * Student counts grouped by department — backs the principal's college-wide
     * dashboard. Not in the Section 6 cache table (the dashboard expects a
     * near-live total), so it reads straight from the replica.
     */
    public function countsByDepartment(): Collection
    {
        return Student::on(self::readConnection())
            ->select('department', DB::raw('COUNT(*) as count'))
            ->groupBy('department')
            ->orderBy('department')
            ->get();
    }

    /**
     * Top N students by current detention-risk score (risk_score > 0.7) —
     * backs the "high-risk students" dashboard widget (Phase 6,
     * SCALABLE_ARCHITECTURE.md §8). Reads the denormalized `students.risk_score`
     * column that MLPredictionService keeps in sync on every nightly
     * PredictDetentionRiskJob run, so the dashboard never has to join the
     * full `detention_predictions` history.
     */
    public function topDetentionRisks(int $limit = 10, ?string $department = null): Collection
    {
        return Student::on(self::readConnection())
            ->select('student_id', 'student_name', 'roll_number', 'department', 'semester', 'risk_score', 'risk_updated_at')
            ->where('risk_score', '>', 0.7)
            ->when($department, fn ($query) => $query->where('department', $department))
            ->orderByDesc('risk_score')
            ->limit($limit)
            ->get();
    }

    public function countAll(): int
    {
        return Student::on(self::readConnection())->count();
    }

    public function countForDepartment(string $department): int
    {
        return Student::on(self::readConnection())->where('department', $department)->count();
    }
}
