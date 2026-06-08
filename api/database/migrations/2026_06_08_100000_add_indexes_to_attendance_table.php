<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 5 — SCALABLE_ARCHITECTURE.md Section 3: indexes that back the
// dashboard / report / monthly-summary queries (filter by student+date,
// subject+date, or date+status).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            if (! Schema::hasIndex('attendance', 'idx_attendance_student_date')) {
                $table->index(['student_id', 'attendance_date'], 'idx_attendance_student_date');
            }

            if (! Schema::hasIndex('attendance', 'idx_attendance_subject_date')) {
                $table->index(['subject_id', 'attendance_date'], 'idx_attendance_subject_date');
            }

            if (! Schema::hasIndex('attendance', 'idx_attendance_date_status')) {
                $table->index(['attendance_date', 'status'], 'idx_attendance_date_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            if (Schema::hasIndex('attendance', 'idx_attendance_student_date')) {
                $table->dropIndex('idx_attendance_student_date');
            }

            if (Schema::hasIndex('attendance', 'idx_attendance_subject_date')) {
                $table->dropIndex('idx_attendance_subject_date');
            }

            if (Schema::hasIndex('attendance', 'idx_attendance_date_status')) {
                $table->dropIndex('idx_attendance_date_status');
            }
        });
    }
};
