<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/AttendanceProcessor.php';
require_once __DIR__ . '/../../src/EmailSender.php';
require_once __DIR__ . '/../../src/Logger.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set execution limits for scale (3000+ students)
set_time_limit(0);
ini_set('memory_limit', '512M');

$logger = new Logger();
$date = date('Y-m-d');

try {
    $processor = new AttendanceProcessor();
    $emailSender = new EmailSender();

    $studentsData = $processor->getDailyAttendance($date);

    if (empty($studentsData)) {
        echo json_encode(['success' => true, 'message' => "No attendance records found for today ({$date}).", 'sent' => 0]);
        exit;
    }

    $count = 0;
    $failed = 0;

    // We will use the same logic as the cron script
    foreach ($studentsData as $email => $data) {
        // We keep the user's test override for now as requested
        $targetEmail = 'gosavivarad6905@gmail.com';

        $sent = $emailSender->sendAttendanceReport($targetEmail, $data['name'], $data['attendance'], $date);
        if ($sent) {
            $count++;
        } else {
            $failed++;
        }
        usleep(100000); // 0.1s delay for UI responsiveness
    }

    echo json_encode([
        'success' => true,
        'message' => "Emails processed successfully.",
        'sent' => $count,
        'failed' => $failed
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>