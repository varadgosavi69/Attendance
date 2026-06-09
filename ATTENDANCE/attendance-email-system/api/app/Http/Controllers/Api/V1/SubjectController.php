<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\BuildsApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSubjectRequest;
use App\Http\Requests\Api\V1\UpdateSubjectRequest;
use App\Http\Requests\Api\V1\UploadSubjectsRequest;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Subjects', description: 'Subject catalog management (CRUD + bulk CSV upload)')]
class SubjectController extends Controller
{
    use BuildsApiResponses;

    #[OA\Get(
        path: '/subjects',
        summary: 'List subjects (paginated, filterable by department/semester)',
        security: [['bearerAuth' => []]],
        tags: ['Subjects'],
        parameters: [
            new OA\Parameter(name: 'department', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'semester', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 8)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of subjects', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'per_page', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                    ], type: 'object'),
                ]
            )),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'department' => ['nullable', 'string'],
            'semester'   => ['nullable', 'integer', 'between:1,8'],
            'per_page'   => ['nullable', 'integer', 'between:1,100'],
            'page'       => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = $request->integer('per_page', 20);

        $paginator = Subject::query()
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->string('department')))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $request->integer('semester')))
            ->orderBy('department')
            ->orderBy('semester')
            ->orderBy('subject_name')
            ->paginate($perPage);

        return $this->ok($paginator->items(), [
            'page'     => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total'    => $paginator->total(),
        ]);
    }

    #[OA\Get(
        path: '/subjects/{subject}',
        summary: 'Get a single subject',
        security: [['bearerAuth' => []]],
        tags: ['Subjects'],
        parameters: [new OA\Parameter(name: 'subject', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Subject record', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object'),
            ])),
            new OA\Response(response: 404, description: 'Subject not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function show(Subject $subject): JsonResponse
    {
        return $this->ok($subject);
    }

    #[OA\Post(
        path: '/subjects',
        summary: 'Create a subject (admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Subjects'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['subject_name', 'subject_code', 'department', 'semester'],
            properties: [
                new OA\Property(property: 'subject_name', type: 'string', example: 'Data Structures'),
                new OA\Property(property: 'subject_code', type: 'string', example: 'CS201'),
                new OA\Property(property: 'department', type: 'string', example: 'CSE'),
                new OA\Property(property: 'semester', type: 'integer', example: 3),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Subject created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — admin role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = Subject::create($request->validated());

        return $this->ok($subject, status: 201);
    }

    #[OA\Put(
        path: '/subjects/{subject}',
        summary: 'Update a subject (admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Subjects'],
        parameters: [new OA\Parameter(name: 'subject', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'subject_name', type: 'string'),
                new OA\Property(property: 'subject_code', type: 'string'),
                new OA\Property(property: 'department', type: 'string'),
                new OA\Property(property: 'semester', type: 'integer'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Subject updated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — admin role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject->update($request->validated());

        return $this->ok($subject->fresh());
    }

    #[OA\Delete(
        path: '/subjects/{subject}',
        summary: 'Delete a subject (admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Subjects'],
        parameters: [new OA\Parameter(name: 'subject', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Subject deleted', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'message', type: 'string')], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — admin role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 404, description: 'Subject not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function destroy(Subject $subject): JsonResponse
    {
        $subject->delete();

        return $this->ok(['message' => 'Subject deleted.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/subjects/upload — bulk CSV upload
    // CSV columns: subject_name, subject_code, department, semester
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/subjects/upload',
        summary: 'Bulk-create/update subjects from a CSV file (admin only)',
        description: 'CSV columns (a header row is skipped): subject_name, subject_code, department, semester',
        security: [['bearerAuth' => []]],
        tags: ['Subjects'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['subject_csv'],
                properties: [new OA\Property(property: 'subject_csv', type: 'string', format: 'binary')],
            ),
        )),
        responses: [
            new OA\Response(response: 200, description: 'Upload processed', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'added', type: 'integer'),
                    new OA\Property(property: 'errors', type: 'integer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — admin role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function upload(UploadSubjectsRequest $request): JsonResponse
    {
        $handle = fopen($request->file('subject_csv')->getRealPath(), 'r');
        fgetcsv($handle); // skip header row

        $added  = 0;
        $errors = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                $errors++;
                continue;
            }

            try {
                Subject::updateOrCreate(
                    ['subject_code' => trim($row[1])],
                    [
                        'subject_name' => trim($row[0]),
                        'department'   => trim($row[2]),
                        'semester'     => (int) trim($row[3]),
                    ]
                );
                $added++;
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        fclose($handle);

        return $this->ok(['added' => $added, 'errors' => $errors]);
    }
}
