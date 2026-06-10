<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 6 months of synthetic attendance for every seeded student, across
 * the 2 subjects matching their department + semester.
 *
 * Each student is given a randomized "base attendance rate" so the dataset
 * produces a realistic spread, including some students below the 75%
 * detention threshold.
 */
class AttendanceSeeder extends Seeder
{
    private const MONTHS_OF_HISTORY = 6;

    private const CHUNK_SIZE = 1000;

    public function run(): void
    {
        $teacherIdByDepartment = Faculty::where('faculty_name', 'like', '% Teacher')
            ->pluck('faculty_id', 'department');

        $subjectsByDeptSemester = Subject::all()
            ->groupBy(fn (Subject $subject) => $subject->department.'-'.$subject->semester);

        $dates = $this->weekdaysOverLastMonths(self::MONTHS_OF_HISTORY);

        $rows = [];

        Student::orderBy('student_id')->chunk(50, function ($students) use (
            &$rows, $teacherIdByDepartment, $subjectsByDeptSemester, $dates
        ) {
            foreach ($students as $student) {
                $facultyId = $teacherIdByDepartment[$student->department];
                $subjects = $subjectsByDeptSemester[$student->department.'-'.$student->semester] ?? collect();

                // Base attendance rate spread between 55% and 97%, so roughly
                // the bottom quarter of students fall under the 75% threshold.
                $baseRate = mt_rand(55, 97) / 100;

                foreach ($dates as $date) {
                    foreach ($subjects as $subject) {
                        $rows[] = [
                            'student_id'      => $student->student_id,
                            'subject_id'      => $subject->subject_id,
                            'faculty_id'      => $facultyId,
                            'attendance_date' => $date->toDateString(),
                            'status'          => $this->randomStatus($baseRate),
                            'marked_at'       => $date->copy()->setTime(9, 0)->toDateTimeString(),
                        ];

                        if (count($rows) >= self::CHUNK_SIZE) {
                            DB::table('attendance')->insert($rows);
                            $rows = [];
                        }
                    }
                }
            }
        });

        if (! empty($rows)) {
            DB::table('attendance')->insert($rows);
        }
    }

    /**
     * @return Carbon[] every weekday (Mon-Fri) in the last $months months, ending today.
     */
    private function weekdaysOverLastMonths(int $months): array
    {
        $end = Carbon::today();
        $start = $end->copy()->subMonths($months);

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->isWeekday()) {
                $dates[] = $date->copy();
            }
        }

        return $dates;
    }

    private function randomStatus(float $baseRate): string
    {
        $roll = mt_rand(1, 100) / 100;

        if ($roll <= $baseRate) {
            return 'Present';
        }

        // Of the "not present" remainder, ~80% Absent, ~20% Leave.
        return mt_rand(1, 100) <= 80 ? 'Absent' : 'Leave';
    }
}
