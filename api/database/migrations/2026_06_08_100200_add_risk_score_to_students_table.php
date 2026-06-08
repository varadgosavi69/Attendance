<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 5 — SCALABLE_ARCHITECTURE.md Section 3: storage for the Phase 6 ML
// service's detention-risk predictions (0.000–1.000) and when they were made.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'risk_score')) {
                $table->decimal('risk_score', 4, 3)->nullable()->after('parent_email');
            }

            if (! Schema::hasColumn('students', 'risk_updated_at')) {
                $table->timestamp('risk_updated_at')->nullable()->after('risk_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'risk_updated_at')) {
                $table->dropColumn('risk_updated_at');
            }

            if (Schema::hasColumn('students', 'risk_score')) {
                $table->dropColumn('risk_score');
            }
        });
    }
};
