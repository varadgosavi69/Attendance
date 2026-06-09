<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faculty')) {
            Schema::create('faculty', function (Blueprint $table) {
                $table->id('faculty_id');
                $table->string('faculty_name', 100);
                $table->string('email', 100)->unique();
                $table->string('department', 50);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id('subject_id');
                $table->string('subject_name', 100);
                $table->string('subject_code', 20)->unique();
                $table->string('department', 50);
                $table->tinyInteger('semester')->unsigned();
            });
        }

        if (! Schema::hasTable('faculty_subjects')) {
            Schema::create('faculty_subjects', function (Blueprint $table) {
                $table->unsignedBigInteger('faculty_id');
                $table->unsignedBigInteger('subject_id');
                $table->primary(['faculty_id', 'subject_id']);
            });
        }

        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table) {
                $table->id('student_id');
                $table->string('roll_number', 20)->unique();
                $table->string('student_name', 100);
                $table->string('email', 100)->unique();
                $table->string('department', 50);
                $table->tinyInteger('semester')->unsigned();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('attendance')) {
            Schema::create('attendance', function (Blueprint $table) {
                $table->id('attendance_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('faculty_id');
                $table->date('attendance_date');
                $table->enum('status', ['Present', 'Absent', 'Leave'])->default('Absent');
                $table->timestamp('marked_at')->nullable();
            });
        }

        if (! Schema::hasTable('detention')) {
            Schema::create('detention', function (Blueprint $table) {
                $table->id('detention_id');
                $table->unsignedBigInteger('student_id');
                $table->date('month');
                $table->unsignedInteger('total_classes')->default(0);
                $table->unsignedInteger('attended_classes')->default(0);
                $table->decimal('attendance_percentage', 5, 2)->default(0.00);
                $table->boolean('is_detained')->default(false);
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('generated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('detention');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('students');
        Schema::dropIfExists('faculty_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('faculty');
    }
};
