<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Creates the users table matching the legacy attendance_db schema
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id('user_id');
                $table->string('username', 50)->unique();
                $table->string('password_hash', 255);
                $table->string('email', 100)->unique();
                $table->string('full_name', 100);
                $table->enum('role', ['admin', 'faculty', 'hod', 'principal'])->default('faculty');
                $table->unsignedBigInteger('faculty_id')->nullable();
                $table->string('department', 50)->nullable();
                $table->timestamp('last_login')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->tinyInteger('failed_attempts')->unsigned()->default(0);
                $table->timestamp('locked_until')->nullable();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
