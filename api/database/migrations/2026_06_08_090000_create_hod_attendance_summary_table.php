<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table may already exist — it ships as a manual migration
        // (database/migration_hod_principal.sql) for the legacy app's shared DB.
        if (Schema::hasTable('hod_attendance_summary')) {
            return;
        }

        Schema::create('hod_attendance_summary', function (Blueprint $table) {
            $table->id();
            $table->string('department', 50);
            $table->unsignedTinyInteger('semester');
            $table->unsignedSmallInteger('year');
            $table->date('date');
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('present_count')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(0.00);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['department', 'semester', 'date'], 'uniq_dept_sem_date');
            $table->foreign('uploaded_by')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hod_attendance_summary');
    }
};
