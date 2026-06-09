<?php
/**
 * One-click database setup for XAMPP.
 * Run this once: http://localhost/attendance-email-system/public/setup_database.php
 * Then use the app and delete or rename this file for security.
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_NAME'] ?? 'attendance_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup | Attendance System</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 560px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .box { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { margin: 0 0 8px; font-size: 1.35rem; color: #333; }
        p { margin: 0 0 16px; color: #555; font-size: 15px; }
        .ok { color: #0d8050; }
        .err { color: #c00; }
        .warn { color: #b45309; }
        ul { margin: 0; padding-left: 20px; }
        a { color: #4361ee; }
        .step { margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Database setup</h1>
        <p>Creating database and tables for the Attendance system…</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 16px 0;">
        <?php
        $messages = [];
        $success = false;

        try {
            // Connect without database to create it
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $messages[] = ['ok', 'Connected to MySQL at ' . $host . ':' . $port . '.'];

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $messages[] = ['ok', "Database \"{$dbname}\" created or already exists."];

            $pdo->exec("USE `{$dbname}`");

            $schemaPath = __DIR__ . '/../database/schema_mysql.sql';
            if (!is_readable($schemaPath)) {
                throw new Exception("Schema file not found: database/schema_mysql.sql");
            }
            $sql = file_get_contents($schemaPath);
            $sql = preg_replace('/--.*$/m', '', $sql);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function ($s) { return $s !== ''; }
            );

            foreach ($statements as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    if (strpos($stmt, 'INSERT INTO users') !== false && $e->getCode() == 23000) {
                        $messages[] = ['ok', 'Admin user already exists (skipped).'];
                    } else {
                        throw $e;
                    }
                }
            }
            $messages[] = ['ok', 'Tables created and admin user ready.'];

            $success = true;
            $messages[] = ['ok', 'Setup complete. You can now <a href="index.php">log in</a> with username <strong>admin</strong> and password <strong>password123</strong>.'];
            $messages[] = ['warn', 'For security, delete or rename <code>setup_database.php</code> after setup.'];
        } catch (PDOException $e) {
            $messages[] = ['err', 'Database error: ' . $e->getMessage()];
            $messages[] = ['warn', 'Make sure XAMPP MySQL is running and .env has correct DB_HOST, DB_PORT (usually 3306), DB_USER, DB_PASS.'];
        } catch (Exception $e) {
            $messages[] = ['err', $e->getMessage()];
        }

        foreach ($messages as $m) {
            list($type, $text) = $m;
            $class = $type === 'ok' ? 'ok' : ($type === 'err' ? 'err' : 'warn');
            echo '<p class="' . $class . '">' . $text . '</p>';
        }
        ?>
        <p><a href="index.php">Go to Login</a></p>
    </div>
</body>
</html>
