<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds login accounts: 1 admin, 5 department teachers, 2 HODs, 1 principal.
 *
 * All passwords are the literal string "password" — see
 * docs/demo-credentials.md. Demo-only, never use in production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make('password');

        User::create([
            'username'      => 'admin',
            'password_hash' => $passwordHash,
            'email'         => 'admin@jdcollege.edu.in',
            'full_name'     => 'System Administrator',
            'role'          => 'admin',
        ]);

        foreach (FacultySeeder::DEPARTMENTS as $department) {
            $faculty = Faculty::where('department', $department)
                ->where('faculty_name', "{$department} Teacher")
                ->first();

            User::create([
                'username'      => 'teacher.'.strtolower($department),
                'password_hash' => $passwordHash,
                'email'         => strtolower($department).'.teacher@jdcollege.edu.in',
                'full_name'     => "{$department} Teacher",
                'role'          => 'teacher',
                'faculty_id'    => $faculty->faculty_id,
                'department'    => $department,
            ]);
        }

        foreach (FacultySeeder::HOD_DEPARTMENTS as $department) {
            $faculty = Faculty::where('department', $department)
                ->where('faculty_name', "{$department} HOD")
                ->first();

            User::create([
                'username'      => 'hod.'.strtolower($department),
                'password_hash' => $passwordHash,
                'email'         => strtolower($department).'.hod@jdcollege.edu.in',
                'full_name'     => "{$department} HOD",
                'role'          => 'hod',
                'faculty_id'    => $faculty->faculty_id,
                'department'    => $department,
            ]);
        }

        User::create([
            'username'      => 'principal',
            'password_hash' => $passwordHash,
            'email'         => 'principal@jdcollege.edu.in',
            'full_name'     => 'College Principal',
            'role'          => 'principal',
        ]);
    }
}
