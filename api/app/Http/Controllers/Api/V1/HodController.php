<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\BuildsApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HodSubmitRequest;
use App\Models\HodAttendanceSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\Facades\JWTAuth;

#[OA\Tag(name: 'HOD', description: 'Head-of-department attendance submission and dashboard')]
class HodController extends Controller
{
    use BuildsApiResponses;

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/hod/summary — submit a daily attendance summary for a class
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/hod/summary',
        summary: 'Submit a daily attendance summary for a department/semester (HOD only)',
        security: [['bearerAuth' => []]],
        tags: ['HOD'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['department', 'semester', 'year', 'date', 'total_students', 'present_count'],
            properties: [
                new OA\Property(property: 'department', type: 'string', example: 'CSE'),
                new OA\Property(property: 'semester', type: 'integer', example: 4),
                new OA\Property(property: 'year', type: 'integer', example: 2026),
                new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-08'),
                new OA\Property(property: 'total_students', type: 'integer', example: 60),
                new OA\Property(property: 'present_count', type: 'integer', example: 54),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Summary submitted', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'percentage', type: 'number', format: 'float'),
                    new OA\Property(property: 'summary', type: 'object'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — hod role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function submit(HodSubmitRequest $request): JsonResponse
    {
        $user      = JWTAuth::parseToken()->authenticate();
        $validated = $request->validated();

        $percentage = round($validated['present_count'] / $validated['total_students'] * 100, 2);

        // Look up by `whereDate` rather than `updateOrCreate`'s exact-match —
        // the `date` cast normalizes stored values to a full datetime string,
        // which would never match the raw Y-m-d input and cause duplicate-key errors.
        $summary = HodAttendanceSummary::where('department', $validated['department'])
            ->where('semester', $validated['semester'])
            ->whereDate('date', $validated['date'])
            ->first() ?? new HodAttendanceSummary([
                'department' => $validated['department'],
                'semester'   => $validated['semester'],
                'date'       => $validated['date'],
            ]);

        $summary->fill([
            'year'                  => $validated['year'],
            'total_students'        => $validated['total_students'],
            'present_count'         => $validated['present_count'],
            'attendance_percentage' => $percentage,
            'uploaded_by'           => $user->user_id,
        ])->save();

        return $this->ok([
            'message'    => "Attendance submitted: {$validated['present_count']}/{$validated['total_students']} ({$percentage}%)",
            'percentage' => $percentage,
            'summary'    => $summary,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/reports/hod/{department} — HOD dashboard for a department
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/reports/hod/{department}',
        summary: 'List historical attendance summaries for a department (HOD or principal)',
        description: 'HODs may only view their own department; principals may view any department.',
        security: [['bearerAuth' => []]],
        tags: ['HOD'],
        parameters: [
            new OA\Parameter(name: 'department', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'CSE'),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated department attendance summaries', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'meta', properties: [
                    new OA\Property(property: 'page', type: 'integer'),
                    new OA\Property(property: 'per_page', type: 'integer'),
                    new OA\Property(property: 'total', type: 'integer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — may only view own department', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function dashboard(Request $request, string $department): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        // HODs may only view their own department; principals may view any
        if ($user->role === 'hod' && $user->department !== $department) {
            return $this->fail('FORBIDDEN', 'You may only view your own department.', 403);
        }

        $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = HodAttendanceSummary::query()
            ->where('department', $department)
            ->orderByDesc('date')
            ->paginate($request->integer('per_page', 20));

        return $this->ok($paginator->items(), [
            'page'     => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total'    => $paginator->total(),
        ]);
    }
}
