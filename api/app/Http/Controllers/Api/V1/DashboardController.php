<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\BuildsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Subject;
use App\Repositories\AttendanceRepository;
use App\Repositories\StudentRepository;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\Facades\JWTAuth;

#[OA\Tag(name: 'Dashboard', description: 'Role-filtered summary statistics')]
class DashboardController extends Controller
{
    use BuildsApiResponses;

    public function __construct(
        private readonly AttendanceRepository $attendance,
        private readonly StudentRepository $students,
    ) {
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/reports/dashboard — role-filtered summary
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/reports/dashboard',
        summary: 'Get a role-filtered dashboard summary (faculty, HOD, or principal view)',
        description: 'The shape of `data` depends on the authenticated user\'s role: admin/teacher get a faculty summary, hod gets a department summary, principal gets a college-wide summary.',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard summary', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'object'),
            ])),
        ],
    )]
    public function summary(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();

        return match ($user->role) {
            'principal' => $this->ok($this->collegeWideSummary()),
            'hod'       => $this->ok($this->departmentSummary($user->department)),
            default     => $this->ok($this->facultySummary()),
        };
    }

    // ── admin / teacher ───────────────────────────────────────────────────────
    private function facultySummary(): array
    {
        $today = $this->attendance->todayStatusCounts();

        return [
            'total_students'   => $this->students->countAll(),
            'avg_attendance'   => $this->attendance->averageAttendance(),
            'absentees_today'  => $today['absent'],
            'classes_today'    => $this->attendance->subjectsMarkedToday(),
            'recent_activity'  => $this->attendance->recentActivity(5)
                ->map(fn ($a) => [
                    'student_name' => $a->student?->student_name,
                    'subject_name' => $a->subject?->subject_name,
                    'status'       => $a->status,
                    'marked_at'    => $a->marked_at,
                ]),
        ];
    }

    // ── hod ───────────────────────────────────────────────────────────────────
    private function departmentSummary(?string $department): array
    {
        $summary = $this->attendance->hodDashboardSummary((string) $department);

        return [
            'department'         => $department,
            'total_students'     => $this->students->countForDepartment((string) $department),
            'avg_attendance'     => $summary['avg_attendance'],
            'recent_summaries'   => $summary['recent_summaries'],
            'high_risk_students' => $this->highRiskWidget($department),
        ];
    }

    // ── principal ─────────────────────────────────────────────────────────────
    private function collegeWideSummary(): array
    {
        $today = $this->attendance->todayStatusCounts();

        return [
            'total_students'      => $this->students->countAll(),
            'total_subjects'      => Subject::count(),
            'total_faculty'       => Faculty::count(),
            'avg_attendance'      => $this->attendance->averageAttendance(),
            'department_counts'   => $this->students->countsByDepartment(),
            'today_present'       => $today['present'],
            'today_absent'        => $today['absent'],
            'today_total'         => $today['present'] + $today['absent'],
            'high_risk_students'  => $this->highRiskWidget(),
        ];
    }

    /**
     * Top-10 detention-risk widget (Phase 6, SCALABLE_ARCHITECTURE.md §8 —
     * "If risk_score > 0.7"): scoped to the HOD's own department, college-wide
     * for the principal. Backed by `students.risk_score`, the column
     * MLPredictionService refreshes on every PredictDetentionRiskJob run.
     */
    private function highRiskWidget(?string $department = null): array
    {
        return $this->students->topDetentionRisks(10, $department)
            ->map(fn ($student) => [
                'student_id'      => $student->student_id,
                'student_name'    => $student->student_name,
                'roll_number'     => $student->roll_number,
                'department'      => $student->department,
                'risk_score'      => (float) $student->risk_score,
                'risk_updated_at' => $student->risk_updated_at,
            ])
            ->all();
    }
}
