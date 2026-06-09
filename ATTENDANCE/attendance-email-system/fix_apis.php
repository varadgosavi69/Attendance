<?php
// Fix all API files missing config.php include
// And seed additional subjects covering more dept/semester combos

require_once __DIR__ . '/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__))->safeLoad();

$db = new PDO(
    'mysql:host='.$_ENV['DB_HOST'].';port='.$_ENV['DB_PORT'].';dbname='.$_ENV['DB_NAME'].';charset=utf8mb4',
    $_ENV['DB_USER'], $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ── 1. Fix API files missing config.php ──────────────────────────────────────
$apiDir = __DIR__ . '/public/api';
$files = [
    'upload_subjects.php',
    'upload_students.php',
    'trigger_mailer.php',
    'mark_attendance.php',
    'logout.php',
    'get_monthly_attendance.php',
    'get_attendance_students.php',
    'generate_detention.php',
];

$needle      = "require_once __DIR__ . '/../../src/Auth.php';";
$replacement = "require_once __DIR__ . '/../../config/config.php';\nrequire_once __DIR__ . '/../../src/Auth.php';";

foreach ($files as $file) {
    $path = $apiDir . '/' . $file;
    if (!file_exists($path)) { echo "[SKIP] $file (not found)\n"; continue; }
    $content = file_get_contents($path);
    if (strpos($content, "config/config.php") !== false) {
        echo "[ALREADY OK] $file\n";
        continue;
    }
    $new = str_replace($needle, $replacement, $content);
    file_put_contents($path, $new);
    echo "[FIXED] $file — added config.php include\n";
}

// ── 2. Seed comprehensive subjects across all dept/semester combos ────────────
echo "\n=== Seeding subjects ===\n";

$subjects = [
    // CSE
    ['CS101', 'C Programming',              'CSE', 1],
    ['CS102', 'Mathematics I',              'CSE', 1],
    ['CS201', 'Data Structures',            'CSE', 2],
    ['CS202', 'Mathematics II',             'CSE', 2],
    ['CS301', 'Discrete Mathematics',       'CSE', 3],
    ['CS302', 'Computer Architecture',      'CSE', 3],
    ['CS401', 'Computer Networks',          'CSE', 4],
    ['CS402', 'Operating Systems',          'CSE', 4],
    ['CS403', 'Database Management',        'CSE', 4],
    ['CS501', 'Software Engineering',       'CSE', 5],
    ['CS502', 'Compiler Design',            'CSE', 5],
    ['CS601', 'Machine Learning',           'CSE', 6],
    ['CS602', 'Cloud Computing',            'CSE', 6],
    ['CS701', 'AI & Neural Networks',       'CSE', 7],
    ['CS801', 'Project & Seminar',          'CSE', 8],
    // ME
    ['ME101', 'Engineering Mechanics',      'ME', 1],
    ['ME201', 'Thermodynamics',             'ME', 2],
    ['ME301', 'Fluid Mechanics',            'ME', 3],
    ['ME401', 'Heat Transfer',              'ME', 4],
    ['ME501', 'Manufacturing Processes',    'ME', 5],
    ['ME601', 'Machine Design',             'ME', 6],
    ['ME701', 'Robotics',                   'ME', 7],
    ['ME801', 'Project',                    'ME', 8],
    // EE
    ['EE101', 'Basic Electrical Engg',      'EE', 1],
    ['EE201', 'Circuit Theory',             'EE', 2],
    ['EE301', 'Electromagnetic Fields',     'EE', 3],
    ['EE401', 'Power Electronics',          'EE', 4],
    ['EE501', 'Control Systems',            'EE', 5],
    ['EE601', 'Power Systems',              'EE', 6],
    ['EE701', 'Renewable Energy',           'EE', 7],
    ['EE801', 'Project',                    'EE', 8],
];

$stmt = $db->prepare("INSERT IGNORE INTO subjects (subject_code, subject_name, department, semester) VALUES (?, ?, ?, ?)");
$added = 0;
foreach ($subjects as $s) {
    $stmt->execute($s);
    if ($stmt->rowCount() > 0) {
        echo "[ADDED] {$s[0]} — {$s[1]} ({$s[2]}, Sem{$s[3]})\n";
        $added++;
    }
}

$total = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
echo "\nTotal subjects now: $total (added $added new)\n";
echo "\n=== All fixes complete! ===\n";
echo "Restart the PHP server and test the Attendance page — subjects will now load.\n";
