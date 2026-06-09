<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/EmailSender.php';

/**
 * DetentionProcessor
 *
 * Calculates monthly attendance percentages per student and flags
 * students below the configured DETENTION_THRESHOLD for detention.
 * Also sends automated detention notice emails.
 */
class DetentionProcessor
{
    private $db;
    private $logger;

    public function __construct()
    {
        $this->db     = Database::getInstance()->getConnection();
        $this->logger = new Logger();
    }

    /**
     * Calculate monthly attendance for all students in a given year+month.
     * Upserts results into the `detention` table.
     *
     * @param int $year  e.g. 2026
     * @param int $month e.g. 2 (February)
     * @return array  All student records with detention status
     */
    public function calculateMonthlyDetention(int $year, int $month): array
    {
        $monthStart  = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd    = date('Y-m-t', strtotime($monthStart)); // last day of month
        $monthLabel  = date('F Y', strtotime($monthStart));
        $threshold   = DETENTION_THRESHOLD;

        $this->logger->info("Detention: Calculating for {$monthLabel}");

        // Aggregate: per student, count distinct (date, subject) combos for total and present
        $sql = "
            SELECT
                s.student_id,
                s.student_name,
                s.email,
                s.roll_number,
                s.department,
                s.semester,
                COUNT(a.attendance_id)                                              AS total_classes,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END)              AS attended_classes,
                ROUND(
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END)
                    / NULLIF(COUNT(a.attendance_id), 0) * 100,
                2)                                                                  AS attendance_percentage
            FROM students s
            LEFT JOIN attendance a
                ON s.student_id = a.student_id
                AND a.attendance_date BETWEEN :start AND :end
            GROUP BY s.student_id, s.student_name, s.email, s.roll_number, s.department, s.semester
            ORDER BY s.department, s.roll_number
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        $upsert  = $this->db->prepare("
            INSERT INTO detention (student_id, month, total_classes, attended_classes, attendance_percentage, is_detained)
            VALUES (:student_id, :month, :total, :attended, :percentage, :detained)
            ON DUPLICATE KEY UPDATE
                total_classes          = VALUES(total_classes),
                attended_classes       = VALUES(attended_classes),
                attendance_percentage  = VALUES(attendance_percentage),
                is_detained            = VALUES(is_detained),
                generated_at           = CURRENT_TIMESTAMP
        ");

        foreach ($rows as $row) {
            $total      = (int) $row['total_classes'];
            $attended   = (int) $row['attended_classes'];
            $percentage = $total > 0 ? (float) $row['attendance_percentage'] : 0.0;
            $detained   = ($percentage < $threshold) ? 1 : 0;

            $upsert->execute([
                'student_id' => $row['student_id'],
                'month'      => $monthStart,
                'total'      => $total,
                'attended'   => $attended,
                'percentage' => $percentage,
                'detained'   => $detained,
            ]);

            $results[] = [
                'student_id'           => $row['student_id'],
                'student_name'         => $row['student_name'],
                'email'                => $row['email'],
                'roll_number'          => $row['roll_number'],
                'department'           => $row['department'],
                'semester'             => $row['semester'],
                'total_classes'        => $total,
                'attended_classes'     => $attended,
                'attendance_percentage'=> $percentage,
                'is_detained'          => $detained,
            ];
        }

        $detainedCount = count(array_filter($results, fn($r) => $r['is_detained']));
        $this->logger->info("Detention: {$monthLabel} — {$detainedCount} student(s) detained out of " . count($results));

        return $results;
    }

    /**
     * Send detention notice emails to detained students.
     * Marks notified_at in the DB after each successful email.
     * Applies 0.1s delay between each email to avoid spam filters.
     *
     * @param array  $students   Results from calculateMonthlyDetention()
     * @param int    $year
     * @param int    $month
     * @return array ['sent' => int, 'failed' => int]
     */
    public function sendDetentionEmails(array $students, int $year, int $month): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $sender     = new EmailSender();
        $sent       = 0;
        $failed     = 0;

        $markNotified = $this->db->prepare("
            UPDATE detention SET notified_at = NOW()
            WHERE student_id = :student_id AND month = :month
        ");

        foreach ($students as $student) {
            if (!$student['is_detained']) continue;

            $ok = $sender->sendDetentionNotice(
                $student['email'],
                $student['student_name'],
                $monthStart,
                $student['attendance_percentage'],
                $student['attended_classes'],
                $student['total_classes']
            );

            if ($ok) {
                $markNotified->execute([
                    'student_id' => $student['student_id'],
                    'month'      => $monthStart,
                ]);
                $sent++;
            } else {
                $failed++;
            }

            usleep(100000); // 0.1 second delay between each email
        }

        $this->logger->info("Detention emails: Sent={$sent}, Failed={$failed}");
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Fetch monthly attendance summary for a given year+month (read-only, no upsert).
     * Used by the UI for display before generating detention.
     */
    public function getMonthlyAttendance(int $year, int $month): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $sql = "
            SELECT
                s.student_id,
                s.roll_number,
                s.student_name,
                s.department,
                s.semester,
                COUNT(a.attendance_id)                                              AS total_classes,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END)              AS attended_classes,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END)               AS absent_classes,
                ROUND(
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END)
                    / NULLIF(COUNT(a.attendance_id), 0) * 100,
                2)                                                                  AS attendance_percentage,
                d.is_detained,
                d.notified_at
            FROM students s
            LEFT JOIN attendance a
                ON s.student_id = a.student_id
                AND a.attendance_date BETWEEN :start AND :end
            LEFT JOIN detention d
                ON s.student_id = d.student_id AND d.month = :month
            GROUP BY s.student_id, s.roll_number, s.student_name, s.department, s.semester, d.is_detained, d.notified_at
            ORDER BY s.department, s.roll_number
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start' => $monthStart, 'end' => $monthEnd, 'month' => $monthStart]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
