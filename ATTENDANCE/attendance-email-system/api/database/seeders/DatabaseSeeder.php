<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Tables populated by this seeder, truncated first so db:seed is
     * repeatable (the demo dataset replaces whatever was there before).
     */
    private const SEEDED_TABLES = [
        'attendance',
        'detention_predictions',
        'email_logs',
        'faculty_subjects',
        'students',
        'subjects',
        'users',
        'faculty',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::SEEDED_TABLES as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->call([
            FacultySeeder::class,
            UserSeeder::class,
            SubjectSeeder::class,
            StudentSeeder::class,
            AttendanceSeeder::class,
        ]);
    }
}
