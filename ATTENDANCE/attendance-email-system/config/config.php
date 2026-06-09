<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3308'); // MySQL default port
define('DB_NAME', $_ENV['DB_NAME'] ?? 'attendance_db');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');   // XAMPP default user
define('DB_PASS', $_ENV['DB_PASS'] ?? '');       // XAMPP default password (empty)

// SMTP Configuration — Primary account (used as fallback if no pool defined)
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? 'your-email@gmail.com');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? 'your-app-password');
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');

// SMTP Rotation Pool — JSON array of multiple SMTP accounts
// Each entry: {"host":"smtp.gmail.com","port":587,"username":"acc@gmail.com","password":"app-pass","secure":"tls"}
// Gmail limit: 500 emails/day per account. We rotate at 450 to stay safe.
define('SMTP_ROTATION_LIMIT', (int)($_ENV['SMTP_ROTATION_LIMIT'] ?? 450));

$_smtpAccountsRaw = $_ENV['SMTP_ACCOUNTS'] ?? '';
if (!empty($_smtpAccountsRaw)) {
    $_smtpAccounts = json_decode($_smtpAccountsRaw, true) ?? [];
} else {
    // Fall back to primary account as single-entry pool
    $_smtpAccounts = [[
        'host'     => SMTP_HOST,
        'port'     => (int) SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'secure'   => SMTP_SECURE,
    ]];
}
define('SMTP_ACCOUNTS', $_smtpAccounts);

// Application Settings
define('APP_NAME', $_ENV['APP_NAME'] ?? 'College Attendance Portal');
define('BASE_URL', $_ENV['BASE_URL'] ?? 'http://localhost/attendance-email-system/public');
define('TIMEZONE', $_ENV['TIMEZONE'] ?? 'Asia/Kolkata');
define('COLLEGE_NAME', $_ENV['COLLEGE_NAME'] ?? 'My College');

// Detention Settings
// Students below this attendance % will be marked as detained
define('DETENTION_THRESHOLD', (float)($_ENV['DETENTION_THRESHOLD'] ?? 75));

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set Timezone
date_default_timezone_set(TIMEZONE);
?>