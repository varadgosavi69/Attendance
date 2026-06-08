<?php

namespace App\Repositories;

use App\Models\Detention;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-heavy detention queries (the monthly detention report). Pinned to
 * the `mysql::read` connection per SCALABLE_ARCHITECTURE.md Section 10
 * ("Configure Laravel to use replica for reads").
 */
class DetentionRepository
{
    private const READ_CONNECTION = 'mysql::read';

    /**
     * Paginated list of detained students for a month, worst attendance first.
     *
     * Not in the Section 6 cache table — `DetentionService::calculateMonthly`
     * already persists the computed rows, so this is a plain paginated read
     * that only needs the replica, not an extra cache layer on top.
     */
    public function detainedForMonth(string $yearMonth, int $perPage = 20): LengthAwarePaginator
    {
        $monthStart = "{$yearMonth}-01";

        return Detention::on(self::READ_CONNECTION)
            ->with('student:student_id,student_name,roll_number,email,department,semester')
            ->whereDate('month', $monthStart)
            ->where('is_detained', true)
            ->orderBy('attendance_percentage')
            ->paginate($perPage);
    }
}
