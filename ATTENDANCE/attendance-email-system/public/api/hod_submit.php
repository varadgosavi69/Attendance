<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

try {
    $auth = new Auth();

    $dept        = trim($_POST['department']     ?? '');
    $semester    = (int)($_POST['semester']       ?? 0);
    $year        = (int)($_POST['year']           ?? 0);
    $date        = trim($_POST['date']            ?? '');
    $totalStu    = (int)($_POST['total_students'] ?? 0);
    $presentCnt  = (int)($_POST['present_count']  ?? 0);

    // Auth check
    $auth->requireRole(['hod']);
    $user = $auth->getUser();

    // Validate
    if (!$dept || !$semester || !$year || !$date || $totalStu <= 0) {
        echo json_encode(['success' => false, 'message' => 'All fields are required and Total Students must be > 0']);
        exit;
    }
    if ($presentCnt < 0 || $presentCnt > $totalStu) {
        echo json_encode(['success' => false, 'message' => 'Present count cannot exceed total students or be negative']);
        exit;
    }
    if ($date > date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'Cannot submit attendance for a future date']);
        exit;
    }

    $percentage = round(($presentCnt / $totalStu) * 100, 2);

    require_once __DIR__ . '/../../src/Database.php';
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        INSERT INTO hod_attendance_summary (department, semester, year, date, total_students, present_count, attendance_percentage, uploaded_by)
        VALUES (:dept, :sem, :year, :date, :total, :present, :pct, :uid)
        ON DUPLICATE KEY UPDATE
            total_students        = VALUES(total_students),
            present_count         = VALUES(present_count),
            attendance_percentage = VALUES(attendance_percentage),
            uploaded_by           = VALUES(uploaded_by),
            uploaded_at           = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        'dept'    => $dept,
        'sem'     => $semester,
        'year'    => $year,
        'date'    => $date,
        'total'   => $totalStu,
        'present' => $presentCnt,
        'pct'     => $percentage,
        'uid'     => $user['user_id'],
    ]);

    echo json_encode([
        'success'    => true,
        'message'    => "Attendance submitted: {$presentCnt}/{$totalStu} ({$percentage}%)",
        'percentage' => $percentage
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
