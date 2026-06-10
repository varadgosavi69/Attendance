<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id('log_id');
            // students.student_id is a signed INT (legacy schema_mysql.sql),
            // so the FK column must match exactly (type + signedness) or
            // MySQL rejects the foreign key with errno 3780.
            $table->integer('student_id');
            $table->string('recipient_email');
            $table->enum('email_type', ['daily_attendance', 'detention_notice', 'monthly_report']);
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');
            $table->index(['student_id', 'email_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
