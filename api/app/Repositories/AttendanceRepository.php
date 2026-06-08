<?php

namespace App\Repositories;

use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-heavy attendance queries that back dashboards, reports, and monthly
 * summaries. Every method here is a plain SELECT, so it is pinned to the
 * `mysql::read` connection (the replica configured in config/database.php —
 * see SCALABLE_ARCHITECTURE.md Section 10/11) and cached per Section 6.
 */
class AttendanceRepository
{
    private const READ_CONNECTION = 'mysql::read';

    /**
     * Per-subject breakdown of one student's attendance for a calendar month.
     *
     * Cache: attendance:monthly:{student_id}:{year}-{month} — 1 hour, invalidated on attendance mark.
     */
    public function monthlySummaryForStudent(int $studentId, string $yearMonth): Collection
    {
        return Cache::remember(
            "attendance:monthly:{$studentId}:{$yearMonth}",
            now()->addHour(),
            function () use ($studentId, $yearMonth) {
                $monthStart = "{$yearMonth}-01";
                $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();

                return Attendance::on(self::READ_CONNECTION)
                    ->join('subjects', 'attendance.subject_id', '=', 'subjects.subject_id')
                    ->where('attendance.student_id', $studentId)
                    ->whereBetween('attendance.attendance_date', [$monthStart, $monthEnd])
                    ->groupBy('subjects.subject_id', 'subjects.subject_name', 'subjects.subject_code')
                    ->selectRaw('subjects.subject_id, subjects.subject_name, subjects.subject_code')
                    ->selectRaw('COUNT(attendance.attendance_id) as total_classes')
                    ->selectRaw("SUM(CASE WHEN attendance.status = 'Present' THEN 1 ELSE 0 END) as attended_classes")
                    ->get();
            }
        );
    }

    /**
     * Subjects a faculty member teaches — populates the "mark attendance" picker.
     *
     * Cache: faculty:subjects:{faculty_id} — 1 hour, invalidated on assignment change.
     */
    public function subjectsForFaculty(int $facultyId): Collection
    {
        return Cache::remember(
            "faculty:subjects:{$facultyId}",
            now()->addHour(),
            fn () => DB::connection(self::READ_CONNECTION)
                ->table('subjects')
                ->join('faculty_subjects', 'subjects.subject_id', '=', 'faculty_subjects.subject_id')
                ->where('faculty_subjects.faculty_id', $facultyId)
                ->orderBy('subjects.department')
                ->orderBy('subjects.semester')
                ->orderBy('subjects.subject_name')
                ->get(['subjects.subject_id', 'subjects.subject_name', 'subjects.subject_code', 'subjects.department', 'subjects.semester'])
        );
    }

    /**
     * HOD department dashboard: average attendance + most recently submitted summaries.
     *
     * Cache: dashboard:hod:{dept_id} — 5 minutes, invalidated on attendance mark.
     */
    public function hodDashboardSummary(string $department): array
    {
        return Cache::remember(
            "dashboard:hod:{$department}",
            now()->addMinutes(5),
            fn () => [
                'avg_attendance' => $this->averageAttendance($department),
                'recent_summaries' => DB::connection(self::READ_CONNECTION)
                    ->table('hod_attendance_summary')
                    ->where('department', $department)
                    ->orderByDesc('date')
                    ->limit(5)
                    ->get(),
            ]
        );
    }

    /**
     * Most recently marked attendance entries — the faculty dashboard's
     * "recent activity" feed. Not in the Section 6 cache table: it needs to
     * reflect marks within seconds, so it reads straight from the replica.
     */
    public function recentActivity(int $limit = 5): Collection
    {
        return Attendance::on(self::READ_CONNECTION)
            ->with(['student:student_id,student_name', 'subject:subject_id,subject_name'])
            ->orderByDesc('marked_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Today's Present / Absent counts, optionally scoped to a department.
     * Not in the Section 6 cache table for the same reason as recentActivity().
     *
     * @return array{present: int, absent: int}
     */
    public function todayStatusCounts(?string $department = null): array
    {
        $query = Attendance::on(self::READ_CONNECTION)->whereDate('attendance_date', today());

        if ($department !== null) {
            $query->whereHas('student', fn ($q) => $q->where('department', $department));
        }

        return [
            'present' => (clone $query)->where('status', 'Present')->count(),
            'absent'  => (clone $query)->where('status', 'Absent')->count(),
        ];
    }

    /**
     * Number of distinct subjects with attendance marked today.
     */
    public function subjectsMarkedToday(): int
    {
        return Attendance::on(self::READ_CONNECTION)
            ->whereDate('attendance_date', today())
            ->distinct('subject_id')
            ->count('subject_id');
    }

    /**
     * College- or department-wide "Present" rate, as a percentage.
     */
    public function averageAttendance(?string $department = null): float
    {
        $query = Attendance::on(self::READ_CONNECTION);

        if ($department !== null) {
            $query->whereHas('student', fn ($q) => $q->where('department', $department));
        }

        $avg = $query->selectRaw("ROUND(AVG(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) * 100, 1) as avg")->value('avg');

        return $avg !== null ? (float) $avg : 0.0;
    }
}
