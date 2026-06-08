<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\BuildsApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateDetentionRequest;
use App\Repositories\DetentionRepository;
use App\Services\DetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Detention', description: 'Detention report listing and generation')]
class DetentionController extends Controller
{
    use BuildsApiResponses;

    public function __construct(
        private readonly DetentionService $detentionService,
        private readonly DetentionRepository $detentions,
    ) {
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/reports/detention?month=YYYY-MM — list detention records
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/reports/detention',
        summary: 'List detained students for a month (HOD or principal)',
        security: [['bearerAuth' => []]],
        tags: ['Detention'],
        parameters: [
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'Format Y-m, defaults to last month', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-05')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of detained students', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'meta', properties: [
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'per_page', type: 'integer'),
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'month', type: 'string', example: '2026-05'),
                    new OA\Property(property: 'threshold', type: 'number', format: 'float', example: 75.0),
                ], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — hod or principal role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month'    => ['nullable', 'date_format:Y-m'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        $month = $request->string('month')->toString() ?: now()->subMonthNoOverflow()->format('Y-m');

        $paginator = $this->detentions->detainedForMonth($month, $request->integer('per_page', 20));

        return $this->ok($paginator->items(), [
            'page'      => $paginator->currentPage(),
            'per_page'  => $paginator->perPage(),
            'total'     => $paginator->total(),
            'month'     => $month,
            'threshold' => (float) config('attendance.detention_threshold', 75),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/reports/detention/generate — calculate + (optionally) notify
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/reports/detention/generate',
        summary: 'Calculate detention for a month and optionally queue notification emails (principal only)',
        security: [['bearerAuth' => []]],
        tags: ['Detention'],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'year', type: 'integer', nullable: true, example: 2026),
                new OA\Property(property: 'month', type: 'integer', nullable: true, example: 5),
                new OA\Property(property: 'send_emails', type: 'boolean', nullable: true, example: false),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Detention report generated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'year', type: 'integer'),
                    new OA\Property(property: 'month', type: 'integer'),
                    new OA\Property(property: 'total_students', type: 'integer'),
                    new OA\Property(property: 'detained_count', type: 'integer'),
                    new OA\Property(property: 'emails_queued', type: 'integer'),
                    new OA\Property(property: 'emails_skipped', type: 'integer'),
                    new OA\Property(property: 'students', type: 'array', items: new OA\Items(type: 'object')),
                ], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — principal role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function generate(GenerateDetentionRequest $request): JsonResponse
    {
        $validated  = $request->validated();
        $year       = (int) ($validated['year'] ?? now()->subMonthNoOverflow()->format('Y'));
        $month      = (int) ($validated['month'] ?? now()->subMonthNoOverflow()->format('m'));
        $sendEmails = (bool) ($validated['send_emails'] ?? false);

        $students      = $this->detentionService->calculateMonthly($year, $month);
        $detainedCount = $students->where('is_detained', true)->count();

        $emailResult = ['queued' => 0, 'skipped' => 0];
        if ($sendEmails) {
            $emailResult = $this->detentionService->queueNotifications($students, $year, $month);
        }

        return $this->ok([
            'message'         => 'Detention report generated.',
            'year'            => $year,
            'month'           => $month,
            'total_students'  => $students->count(),
            'detained_count'  => $detainedCount,
            'emails_queued'   => $emailResult['queued'],
            'emails_skipped'  => $emailResult['skipped'],
            'students'        => $students->values(),
        ]);
    }
}
