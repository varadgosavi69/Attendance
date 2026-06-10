<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Services\MLPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class MLPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private MLPredictionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MLPredictionService::class);
    }

    private function makeStudent(string $roll = 'CS001'): Student
    {
        return Student::create([
            'roll_number'  => $roll,
            'student_name' => 'Test Student',
            'email'        => $roll . '@test.edu',
            'department'   => 'CS',
            'semester'     => 3,
        ]);
    }

    private function mlResponse(array $predictions = [], array $skipped = [], string $version = 'detention_v1'): array
    {
        return [
            'model_version'       => $version,
            'predictions'         => $predictions,
            'skipped_student_ids' => $skipped,
        ];
    }

    // ── edge cases ────────────────────────────────────────────────────────────

    public function test_empty_student_ids_returns_empty_collection_without_http_call(): void
    {
        Http::fake();

        $result = $this->service->predictDetentionRisk([]);

        $this->assertEmpty($result);
        Http::assertNothingSent();
    }

    // ── happy path ────────────────────────────────────────────────────────────

    public function test_successful_prediction_stores_detention_prediction_record(): void
    {
        $student = $this->makeStudent();

        Http::fake(['*/predict/detention-risk' => Http::response($this->mlResponse([
            [
                'student_id'          => $student->student_id,
                'risk_score'          => 0.82,
                'predicted_detention' => true,
                'features'            => ['attendance_rate' => 0.72],
            ],
        ]), 200)]);

        $results = $this->service->predictDetentionRisk([$student->student_id]);

        $this->assertCount(1, $results);
        $this->assertDatabaseHas('detention_predictions', [
            'student_id'    => $student->student_id,
            'model_version' => 'detention_v1',
        ]);
    }

    public function test_risk_score_is_denormalized_onto_students_table(): void
    {
        $student = $this->makeStudent('CS002');

        Http::fake(['*/predict/detention-risk' => Http::response($this->mlResponse([
            [
                'student_id'          => $student->student_id,
                'risk_score'          => 0.75,
                'predicted_detention' => true,
                'features'            => null,
            ],
        ]), 200)]);

        $this->service->predictDetentionRisk([$student->student_id]);

        $this->assertEquals(0.75, (float) $student->fresh()->risk_score);
    }

    public function test_model_version_is_stored_on_prediction_record(): void
    {
        $student = $this->makeStudent('CS003');

        Http::fake(['*/predict/detention-risk' => Http::response($this->mlResponse(
            [[
                'student_id'          => $student->student_id,
                'risk_score'          => 0.5,
                'predicted_detention' => false,
                'features'            => null,
            ]],
            [],
            'detention_v2_experimental'
        ), 200)]);

        $results = $this->service->predictDetentionRisk([$student->student_id]);

        $this->assertEquals('detention_v2_experimental', $results->first()->model_version);
    }

    // ── error handling ────────────────────────────────────────────────────────

    public function test_http_500_throws_runtime_exception(): void
    {
        Http::fake(['*/predict/detention-risk' => Http::response(['error' => 'model not loaded'], 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ML service prediction request failed/');

        $this->service->predictDetentionRisk([1]);
    }

    public function test_http_503_throws_runtime_exception(): void
    {
        Http::fake(['*/predict/detention-risk' => Http::response(null, 503)]);

        $this->expectException(RuntimeException::class);
        $this->service->predictDetentionRisk([1]);
    }

    public function test_connection_exception_propagates(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out after 5 seconds');
        });

        $this->expectException(ConnectionException::class);

        $this->service->predictDetentionRisk([1]);
    }

    // ── skipped students ──────────────────────────────────────────────────────

    public function test_skipped_student_ids_are_logged_as_info(): void
    {
        $student = $this->makeStudent('CS004');

        Http::fake(['*/predict/detention-risk' => Http::response($this->mlResponse(
            [],
            [$student->student_id]
        ), 200)]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) =>
                str_contains($msg, 'skipped student') &&
                ($ctx['student_id'] ?? null) === $student->student_id
            );

        $results = $this->service->predictDetentionRisk([$student->student_id]);

        $this->assertEmpty($results);
    }

    public function test_mix_of_predictions_and_skipped_students(): void
    {
        $scored  = $this->makeStudent('CS005');
        $skipped = $this->makeStudent('CS006');

        Http::fake(['*/predict/detention-risk' => Http::response($this->mlResponse(
            [[
                'student_id'          => $scored->student_id,
                'risk_score'          => 0.3,
                'predicted_detention' => false,
                'features'            => null,
            ]],
            [$skipped->student_id]
        ), 200)]);

        Log::shouldReceive('info')->once();

        $results = $this->service->predictDetentionRisk([$scored->student_id, $skipped->student_id]);

        $this->assertCount(1, $results);
        $this->assertEquals($scored->student_id, $results->first()->student_id);
    }
}
