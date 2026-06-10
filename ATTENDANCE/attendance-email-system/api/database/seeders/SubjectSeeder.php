<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Seeds 2 subjects per department per semester (semesters 3-6), and links
 * each subject to that department's teacher in faculty_subjects.
 */
class SubjectSeeder extends Seeder
{
    public const SEMESTERS = [3, 4, 5, 6];

    private const SUBJECT_NAMES = [
        1 => 'Core Theory',
        2 => 'Core Lab & Applications',
    ];

    public function run(): void
    {
        foreach (FacultySeeder::DEPARTMENTS as $department) {
            $teacher = Faculty::where('department', $department)
                ->where('faculty_name', "{$department} Teacher")
                ->first();

            foreach (self::SEMESTERS as $semester) {
                foreach (self::SUBJECT_NAMES as $index => $name) {
                    $subject = Subject::create([
                        'subject_name' => "{$department} Sem {$semester} - {$name}",
                        'subject_code' => "{$department}{$semester}0{$index}",
                        'department'   => $department,
                        'semester'     => $semester,
                    ]);

                    $teacher->subjects()->attach($subject->subject_id);
                }
            }
        }
    }
}
