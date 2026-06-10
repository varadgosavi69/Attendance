<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * Seeds 200 students: 40 per department across 5 departments, evenly spread
 * across semesters 3-6 (10 students per department per semester).
 */
class StudentSeeder extends Seeder
{
    public const STUDENTS_PER_DEPARTMENT = 40;

    public function run(): void
    {
        $semesters = SubjectSeeder::SEMESTERS;
        $perSemester = (int) (self::STUDENTS_PER_DEPARTMENT / count($semesters));

        foreach (FacultySeeder::DEPARTMENTS as $department) {
            $rollSeq = 1;

            foreach ($semesters as $semester) {
                for ($i = 1; $i <= $perSemester; $i++) {
                    $rollNumber = sprintf('%s%03d', $department, $rollSeq);

                    Student::create([
                        'roll_number'  => $rollNumber,
                        'student_name' => "{$department} Student {$rollSeq}",
                        'email'        => strtolower($rollNumber).'@student.jdcollege.edu.in',
                        'parent_email' => 'parent.'.strtolower($rollNumber).'@example.com',
                        'department'   => $department,
                        'semester'     => $semester,
                    ]);

                    $rollSeq++;
                }
            }
        }
    }
}
