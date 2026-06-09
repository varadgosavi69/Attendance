<?php

namespace App\Services;

use App\Models\DetentionPrediction;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MLPredictionService
{
    /**
     * POST /predict/detention-risk to the ml-service for a batch of students,
     * persist each returned score to `detention_predictions`, and hand back
     * the stored rows (see SCALABLE_ARCHITECTURE.md §8 "Integration Flow").
     *
     * @param  int[]  $studentIds
     * @return Collection<int, DetentionPrediction>
     *
     * @throws RuntimeException when the ml-service is unreachable or errors
     */
    public function predictDetentionRisk(array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        $response = Http::baseUrl(config('services.ml_service.url'))
            ->timeout(config('services.ml_service.timeout'))
            ->acceptJson()
            ->post('/predict/detention-risk', ['student_ids' => array_values($studentIds)]);

        if ($response->failed()) {
            throw new RuntimeException(
                "ML service prediction request failed: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $body         = $response->json();
        $modelVersion = $body['model_version'] ?? 'unknown';

        foreach ($body['skipped_student_ids'] ?? [] as $skippedId) {
            Log::info('ML service skipped student — no attendance history to score from.', [
                'student_id' => $skippedId,
            ]);
        }

        return collect($body['predictions'] ?? [])
            ->map(fn (array $prediction) => $this->store($prediction, $modelVersion));
    }

    private function store(array $prediction, string $modelVersion): DetentionPrediction
    {
        $record = DetentionPrediction::create([
            'student_id'          => $prediction['student_id'],
            'predicted_at'        => now(),
            'risk_score'          => $prediction['risk_score'],
            'predicted_detention' => $prediction['predicted_detention'],
            'features_snapshot'   => $prediction['features'] ?? null,
            'model_version'       => $modelVersion,
        ]);

        // Denormalize the latest score onto `students` so the dashboard's
        // high-risk widget can read it directly without joining the full
        // prediction history (the column Phase 5 added for exactly this).
        Student::where('student_id', $record->student_id)->update([
            'risk_score'      => $record->risk_score,
            'risk_updated_at' => $record->predicted_at,
        ]);

        return $record;
    }
}
