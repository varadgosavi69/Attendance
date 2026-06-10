<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 6 — SCALABLE_ARCHITECTURE.md §3: stores each detention-risk prediction
// the ml-service returns, so PredictDetentionRiskJob has an audit trail of
// every score (and the feature snapshot it was computed from) over time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detention_predictions', function (Blueprint $table) {
            $table->id();
            // students.student_id is a signed INT (legacy schema_mysql.sql),
            // so the FK column must match exactly (type + signedness) or
            // MySQL rejects the foreign key with errno 3780.
            $table->integer('student_id');
            $table->timestamp('predicted_at')->useCurrent();
            $table->decimal('risk_score', 4, 3);
            $table->boolean('predicted_detention');
            $table->json('features_snapshot')->nullable();
            $table->string('model_version', 50);

            $table->index(['student_id', 'predicted_at']);
            $table->index('risk_score');
            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detention_predictions');
    }
};
