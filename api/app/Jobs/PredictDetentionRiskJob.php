<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\MLPredictionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class PredictDetentionRiskJob implements ShouldQueue
{
    use Queueable;

    public int   $tries   = 3;
    public array $backoff = [60, 300, 900];
    public int   $timeout = 120; // `ml` queue: 2 workers, 2-min timeout (SCALABLE_ARCHITECTURE.md §9)

    /**
     * @param  int[]|null  $studentIds  Defaults to every student when omitted
     *                                  (the nightly scheduled run scores everyone).
     */
    public function __construct(
        public readonly ?array $studentIds = null,
    ) {}

    public function handle(MLPredictionService $predictions): void
    {
        $studentIds = $this->studentIds ?? Student::query()->pluck('student_id')->all();

        if ($studentIds === []) {
            return;
        }

        $results = $predictions->predictDetentionRisk($studentIds);

        Log::info('Detention-risk predictions generated.', [
            'requested' => count($studentIds),
            'scored'    => $results->count(),
            'high_risk' => $results->where('risk_score', '>', 0.7)->count(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('PredictDetentionRiskJob failed.', ['error' => $e->getMessage()]);
    }
}
