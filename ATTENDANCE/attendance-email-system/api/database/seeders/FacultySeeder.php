<?php

namespace Database\Seeders;

use App\Models\Faculty;
use Illuminate\Database\Seeder;

/**
 * Seeds one teaching faculty member per department, plus two HOD faculty
 * records for the departments that get an HOD user (CSE, IT).
 */
class FacultySeeder extends Seeder
{
    public const DEPARTMENTS = ['CSE', 'IT', 'ENTC', 'MECH', 'CIVIL'];

    public const HOD_DEPARTMENTS = ['CSE', 'IT'];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $department) {
            Faculty::create([
                'faculty_name' => "{$department} Teacher",
                'email'        => strtolower($department).'.teacher@jdcollege.edu.in',
                'department'   => $department,
            ]);
        }

        foreach (self::HOD_DEPARTMENTS as $department) {
            Faculty::create([
                'faculty_name' => "{$department} HOD",
                'email'        => strtolower($department).'.hod@jdcollege.edu.in',
                'department'   => $department,
            ]);
        }
    }
}
