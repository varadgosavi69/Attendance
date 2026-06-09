<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\BuildsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Principal', description: 'College-wide overview for the principal role')]
class PrincipalController extends Controller
{
    use BuildsApiResponses;

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/reports/principal — college-wide overview
    // ──────────────────────────────────────────────────────────────────────────
    #[OA\Get(
        path: '/reports/principal',
        summary: 'Get a college-wide attendance overview (principal only)',
        security: [['bearerAuth' => []]],
        tags: ['Principal'],
        responses: [
            new OA\Response(response: 200, description: 'College-wide overview', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'total_students', type: 'integer'),
                    new OA\Property(property: 'total_subjects', type: 'integer'),
                    new OA\Property(property: 'total_faculty', type: 'integer'),
                    new OA\Property(property: 'avg_attendance', type: 'number', format: 'float'),
                    new OA\Property(property: 'department_counts', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'today_present', type: 'integer'),
                    new OA\Property(property: 'today_absent', type: 'integer'),
                    new OA\Property(property: 'today_total', type: 'integer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 403, description: 'Forbidden — principal role required', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ],
    )]
    public function overview(): JsonResponse
    {
        $todayPresent = Attendance::whereDate('attendance_date', today())->where('status', 'Present')->count();
        $todayAbsent  = Attendance::whereDate('attendance_date', today())->where('status', 'Absent')->count();

        $avgAttendance = Attendance::query()
            ->selectRaw("ROUND(AVG(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) * 100, 1) as avg")
            ->value('avg');

        return $this->ok([
            'total_students'    => Student::count(),
            'total_subjects'    => Subject::count(),
            'total_faculty'     => Faculty::count(),
            'avg_attendance'    => $avgAttendance !== null ? (float) $avgAttendance : 0.0,
            'department_counts' => Student::query()
                ->select('department', DB::raw('COUNT(*) as count'))
                ->groupBy('department')
                ->orderBy('department')
                ->get(),
            'today_present' => $todayPresent,
            'today_absent'  => $todayAbsent,
            'today_total'   => $todayPresent + $todayAbsent,
        ]);
    }
}
