<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\BuildsApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MarkAttendanceRequest;
use App\Models\Attendance;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use App\Repositories\AttendanceRepository;
use App\Repositories\StudentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\Facades\JWTAuth;

#[OA\Tag(name: 'Attendance', description: 'Marking and viewing attendance')]
class AttendanceController extends Controller
{
    use BuildsApiResponses;

    public function __construct(
        private readonly AttendanceRepository $attendance,
        private readonly StudentRepository $students,
    ) {
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/attendance — mark attendance for a class (batch)
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Post(
        path: '/attendance',
        summary: 'Mark attendance for a class (batch)',
        security: [['bearerAuth' => []]],
        tags: ['Attendance'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['subject_id', 'date', 'records'],
            properties: [
                new OA\Property(property: 'subject_id', type: 'integer', example: 1),
                new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-08'),
                new OA\Property(
                    property: 'records',
                    type: 'object',
                    description: 'Map of student_id => status (Present|Absent|Leave)',
                    example: ['101' => 'Present', '102' => 'Absent'],
                ),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Attendance marked', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'count', type: 'integer'),
                    ], type: 'object'),
                ]
            )),
            new OA\Response(response: 403, description: 'Not authorized for this subject', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function store(MarkAttendanceRequest $request): JsonResponse
    {
        $user      = JWTAuth::parseToken()->authenticate();
        $validated = $request->validated();

        $facultyId = $this->resolveFacultyId($user, (int) $validated['subject_id']);

        if ($facultyId === null) {
            return $this->fail('FORBIDDEN', 'You are not authorized to mark attendance for this subject.', 403);
        }

        DB::transaction(function () use ($validated, $facultyId) {
            foreach ($validated['records'] as $studentId => $status) {
                Attendance::updateOrCreate(
                    [
                        'student_id'      => $studentId,
                        'subject_id'      => $validated['subject_id'],
                        'attendance_date' => $validated['date'],
                    ],
                    [
                        'faculty_id' => $facultyId,
                        'status'     => $status,
                        'marked_at'  => now(),
                    ]
                );
            }
        });

        return $this->ok(['message' => 'Attendance marked successfully.', 'count' => count($validated['records'])]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/attendance/students?semester=&branch=
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/attendance/students',
        summary: 'List students for marking attendance',
        security: [['bearerAuth' => []]],
        tags: ['Attendance'],
        parameters: [
            new OA\Parameter(name: 'semester', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 8)),
            new OA\Parameter(name: 'branch', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'CSE'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of students', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'student_id', type: 'integer'),
                        new OA\Property(property: 'student_name', type: 'string'),
                        new OA\Property(property: 'roll_number', type: 'string'),
                    ], type: 'object')),
                ]
            )),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function students(Request $request): JsonResponse
    {
        $request->validate([
            'semester' => ['required', 'integer', 'between:1,8'],
            'branch'   => ['required', 'string'],
        ]);

        $students = $this->students->rosterForClass(
            $request->string('branch')->toString(),
            $request->integer('semester'),
        );

        return $this->ok($students);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/attendance/subjects — subjects the faculty teaches
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/attendance/subjects',
        summary: "List the authenticated faculty's subjects",
        security: [['bearerAuth' => []]],
        tags: ['Attendance'],
        responses: [
            new OA\Response(response: 200, description: 'List of subjects', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'subject_id', type: 'integer'),
                        new OA\Property(property: 'subject_name', type: 'string'),
                        new OA\Property(property: 'subject_code', type: 'string'),
                        new OA\Property(property: 'department', type: 'string'),
                        new OA\Property(property: 'semester', type: 'integer'),
                    ], type: 'object')),
                ]
            )),
        ],
    )]
    public function subjects(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->faculty_id) {
            $subjects = $this->attendance->subjectsForFaculty((int) $user->faculty_id);
        } else {
            // Admins are not linked to a faculty record — fall back to all subjects
            $subjects = Subject::query()
                ->orderBy('department')
                ->orderBy('semester')
                ->orderBy('subject_name')
                ->get(['subject_id', 'subject_name', 'subject_code', 'department', 'semester']);
        }

        return $this->ok($subjects);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/attendance/monthly/{student}?month=YYYY-MM
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/attendance/monthly/{student}',
        summary: 'Get a monthly attendance summary for a student, broken down by subject',
        security: [['bearerAuth' => []]],
        tags: ['Attendance'],
        parameters: [
            new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'Format Y-m, defaults to last month', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-05')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Monthly attendance summary', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'month', type: 'string', example: '2026-05'),
                        new OA\Property(property: 'total_classes', type: 'integer'),
                        new OA\Property(property: 'attended_classes', type: 'integer'),
                        new OA\Property(property: 'attendance_percentage', type: 'number', format: 'float'),
                    ], type: 'object'),
                ]
            )),
            new OA\Response(response: 404, description: 'Student not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function monthly(Request $request, int $student): JsonResponse
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $studentModel = Student::findOrFail($student);

        $month = $request->string('month')->toString() ?: now()->subMonthNoOverflow()->format('Y-m');

        $rows = $this->attendance->monthlySummaryForStudent($student, $month);

        $bySubject = $rows->map(function ($row) {
            $total    = (int) $row->total_classes;
            $attended = (int) $row->attended_classes;

            return [
                'subject_id'            => $row->subject_id,
                'subject_name'          => $row->subject_name,
                'subject_code'          => $row->subject_code,
                'total_classes'         => $total,
                'attended_classes'      => $attended,
                'attendance_percentage' => $total > 0 ? round($attended / $total * 100, 2) : 0.0,
            ];
        });

        $totalClasses    = (int) $bySubject->sum('total_classes');
        $attendedClasses = (int) $bySubject->sum('attended_classes');

        return $this->ok([
            'student' => [
                'student_id'   => $studentModel->student_id,
                'student_name' => $studentModel->student_name,
                'roll_number'  => $studentModel->roll_number,
            ],
            'month'                 => $month,
            'total_classes'         => $totalClasses,
            'attended_classes'      => $attendedClasses,
            'attendance_percentage' => $totalClasses > 0 ? round($attendedClasses / $totalClasses * 100, 2) : 0.0,
            'subjects'              => $bySubject->values(),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Determine which faculty_id to record attendance under, enforcing the
     * faculty-subject ownership check that the legacy endpoint performed.
     * Returns null when the user is not allowed to mark this subject.
     */
    private function resolveFacultyId($user, int $subjectId): ?int
    {
        if ($user->faculty_id) {
            $owns = DB::table('faculty_subjects')
                ->where('faculty_id', $user->faculty_id)
                ->where('subject_id', $subjectId)
                ->exists();

            return $owns ? (int) $user->faculty_id : null;
        }

        // Admins are not linked to a faculty record — resolve by matching name (legacy fallback)
        $faculty = Faculty::where('faculty_name', $user->full_name)->first();

        return $faculty?->faculty_id;
    }
}
