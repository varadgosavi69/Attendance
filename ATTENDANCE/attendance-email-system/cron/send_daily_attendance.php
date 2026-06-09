<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AttendanceProcessor.php';
require_once __DIR__ . '/../src/EmailSender.php';
require_once __DIR__ . '/../src/Logger.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$logger = new Logger();
$logger->info("Manual Trigger Started.");

$date = date('Y-m-d');
$processor = new AttendanceProcessor();
$emailSender = new EmailSender();

$studentsData = $processor->getDailyAttendance($date);

if (empty($studentsData)) {
    echo "NO_RECORDS_FOUND_FOR_" . $date;
    $logger->info("No records found.");
    exit;
}

$count = 0;
$failed = 0;
foreach ($studentsData as $email => $data) {
    // TESTING OVERRIDE
    $targetEmail = 'gosavivarad6905@gmail.com';
    if ($emailSender->sendAttendanceReport($targetEmail, $data['name'], $data['attendance'], $date)) {
        $count++;
    } else {
        $failed++;
    }
    usleep(100000);
}

echo "COMPLETED|SENT:{$count}|FAILED:{$failed}";
$logger->info("Manual Trigger Finished. Sent: {$count}");
?>