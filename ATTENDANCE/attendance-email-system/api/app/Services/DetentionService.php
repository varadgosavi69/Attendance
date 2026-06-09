<?php

namespace App\Services;

use App\Models\Detention;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DetentionService
{
    public function __construct(private readonly EmailService $emailService)
    {
    }

    /**
     * Recalculate attendance percentages for every student over the given
     * month, upsert `detention` rows, and return the per-student results.
     * Mirrors the legacy DetentionProcessor::calculateMonthlyDetention.
     */
    public function calculateMonthly(int $year, int $month): Collection
    {
        [$start, $end] = $this->monthBounds($year, $month);
        $threshold     = (float) config('attendance.detention_threshold', 75);

        $rows = DB::table('students as s')
            ->leftJoin('attendance as a', function ($join) use ($start, $end) {
                $join->on('s.student_id', '=', 'a.student_id')
                     ->whereBetween('a.attendance_date', [$start, $end]);
            })
            ->groupBy('s.student_id', 's.student_name', 's.email', 's.roll_number', 's.department', 's.semester')
            ->orderBy('s.department')
            ->orderBy('s.roll_number')
            ->selectRaw('s.student_id, s.student_name, s.email, s.roll_number, s.department, s.semester')
            ->selectRaw('COUNT(a.attendance_id) as total_classes')
            ->selectRaw("SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as attended_classes")
            ->get();

        $results = $rows->map(function ($row) use ($start, $threshold) {
            $total      = (int) $row->total_classes;
            $attended   = (int) $row->attended_classes;
            $percentage = $total > 0 ? round($attended / $total * 100, 2) : 0.0;
            $detained   = $percentage < $threshold;

            // `month` is cast to `date`, which Eloquent serializes as a full
            // datetime string on save — an exact-match lookup against the raw
            // Y-m-d value would never find the existing row and would insert
            // a duplicate on every regeneration. Look it up with `whereDate`.
            $detention = Detention::where('student_id', $row->student_id)
                ->whereDate('month', $start)
                ->first() ?? new Detention([
                    'student_id' => $row->student_id,
                    'month'      => $start,
                ]);

            $detention->fill([
                'total_classes'         => $total,
                'attended_classes'      => $attended,
                'attendance_percentage' => $percentage,
                'is_detained'           => $detained,
                'generated_at'          => now(),
            ])->save();

            return [
                'student_id'            => $row->student_id,
                'student_name'          => $row->student_name,
                'email'                 => $row->email,
                'roll_number'           => $row->roll_number,
                'department'            => $row->department,
                'semester'              => $row->semester,
                'total_classes'         => $total,
                'attended_classes'      => $attended,
                'attendance_percentage' => $percentage,
                'is_detained'           => $detained,
            ];
        });

        return $results;
    }

    /**
     * Read-only monthly attendance summary joined with any existing
     * detention record — used to preview before generating a report.
     */
    public function getMonthlyAttendance(int $year, int $month): Collection
    {
        [$start, $end] = $this->monthBounds($year, $month);

        return DB::table('students as s')
            ->leftJoin('attendance as a', function ($join) use ($start, $end) {
                $join->on('s.student_id', '=', 'a.student_id')
                     ->whereBetween('a.attendance_date', [$start, $end]);
            })
            ->leftJoin('detention as d', function ($join) use ($start) {
                $join->on('s.student_id', '=', 'd.student_id')
                     ->where('d.month', '=', $start);
            })
            ->groupBy('s.student_id', 's.roll_number', 's.student_name', 's.department', 's.semester', 'd.is_detained', 'd.notified_at')
            ->orderBy('s.department')
            ->orderBy('s.roll_number')
            ->selectRaw('s.student_id, s.roll_number, s.student_name, s.department, s.semester')
            ->selectRaw('COUNT(a.attendance_id) as total_classes')
            ->selectRaw("SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as attended_classes")
            ->selectRaw("SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_classes")
            ->selectRaw(
                "ROUND(SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.attendance_id), 0) * 100, 2) as attendance_percentage"
            )
            ->addSelect('d.is_detained', 'd.notified_at')
            ->get();
    }

    /**
     * Queue detention-notice emails (async, via the Phase 3 email queue)
     * for every detained student that has a parent email on file.
     *
     * @param  Collection<int, array>  $students  Results from calculateMonthly()
     * @return array{queued: int, skipped: int}
     */
    public function queueNotifications(Collection $students, int $year, int $month): array
    {
        $monthLabel = sprintf('%04d-%02d', $year, $month);
        $queued     = 0;
        $skipped    = 0;

        foreach ($students->where('is_detained', true) as $row) {
            $student = Student::find($row['student_id']);

            if (! $student || ! $student->parent_email) {
                $skipped++;
                continue;
            }

            $this->emailService->queueDetentionNotice($student, [
                'attendance_percentage' => $row['attendance_percentage'],
                'attended_classes'      => $row['attended_classes'],
                'total_classes'         => $row['total_classes'],
                'required_percentage'   => (float) config('attendance.detention_threshold', 75),
            ], $monthLabel);

            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * @return array{0: string, 1: string} [monthStart, monthEnd] as Y-m-d
     */
    private function monthBounds(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        return [$start, $end];
    }
}
