<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\BuildsApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudentRequest;
use App\Http\Requests\Api\V1\UpdateStudentRequest;
use App\Http\Requests\Api\V1\UploadStudentsRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Students', description: 'Student directory management (CRUD + bulk CSV upload)')]
class StudentController extends Controller
{
    use BuildsApiResponses;

    #[OA\Get(
        path: '/students',
        summary: 'List students (paginated, filterable by department/semester)',
        security: [['bearerAuth' => []]],
        tags: ['Students'],
        parameters: [
            new OA\Parameter(name: 'department', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'semester', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 8)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of students', content: new OA\JsonContent(
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

        $paginator = Student::query()
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->string('department')))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', $request->integer('semester')))
            ->orderBy('roll_number')
            ->paginate($perPage);

        return $this->ok($paginator->items(), [
            'page'     => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total'    => $paginator->total(),
        ]);
    }

    #[OA\Get(
        path: '/students/{student}',
        summary: 'Get a single student',
        security: [['bearerAuth' => []]],
        tags: ['Students'],
        parameters: [new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Student record', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object'),
            ])),
            new OA\Response(response: 404, description: 'Student not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function show(Student $student): JsonResponse
    {
        return $this->ok($student);
    }

    #[OA\Post(
        path: '/students',
        summary: 'Create a student (admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Students'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['roll_number', 'student_name', 'email', 'department', 'semester'],
            properties: [
                new OA\Property(property: 'roll_number', type: 'string', example: 'CSE2026001'),
                new OA\Property(property: 'student_name', type: 'string', example: 'Asha Rao'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'parent_email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'department', type: 'string', example: 'CSE'),
                new OA\Property(property: 'semester', type: 'integer', example: 4),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Student created', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — admin role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = Student::create($request->validated());

        return $this->ok($student, status: 201);
    }

    #[OA\Put(
        path: '/students/{student}',
        summary: 'Update a student (admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Students'],
        parameters: [new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'roll_number', type: 'string'),
                new OA\Property(property: 'student_name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'parent_email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'department', type: 'string'),
                new OA\Property(property: 'semester', type: 'integer'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Student updated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — admin role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $student->update($request->validated());

        return $this->ok($student->fresh());
    }

    #[OA\Delete(
        path: '/students/{student}',
        summary: 'Delete a student (admin only)',
        security: [['bearerAuth' => []]],
        tags: ['Students'],
        parameters: [new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Student deleted', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [new OA\Property(property: 'message', type: 'string')], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — admin role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 404, description: 'Student not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return $this->ok(['message' => 'Student deleted.']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/students/upload — bulk CSV upload
    // CSV columns: roll_number, student_name, email, department, semester
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/students/upload',
        summary: 'Bulk-create/update students from a CSV file (admin only)',
        description: 'CSV columns (no header values required to match, but a header row is skipped): roll_number, student_name, email, department, semester',
        security: [['bearerAuth' => []]],
        tags: ['Students'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['student_csv'],
                properties: [new OA\Property(property: 'student_csv', type: 'string', format: 'binary')],
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
    public function upload(UploadStudentsRequest $request): JsonResponse
    {
        $handle = fopen($request->file('student_csv')->getRealPath(), 'r');
        fgetcsv($handle); // skip header row

        $added  = 0;
        $errors = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) {
                $errors++;
                continue;
            }

            try {
                Student::updateOrCreate(
                    ['roll_number' => trim($row[0])],
                    [
                        'student_name' => trim($row[1]),
                        'email'        => trim($row[2]),
                        'department'   => trim($row[3]),
                        'semester'     => (int) trim($row[4]),
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
