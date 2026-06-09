<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();
$auth->requireRole(['teacher', 'admin']);
$user = $auth->getUser();

$logsDir = __DIR__ . '/../logs';
$logFiles = [];

if (is_dir($logsDir)) {
    $files = scandir($logsDir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (strpos($file, '.log') !== false) {
            $logFiles[] = [
                'name' => $file,
                'size' => round(filesize($logsDir . '/' . $file) / 1024, 2) . ' KB',
                'date' => date("Y-m-d H:i:s", filemtime($logsDir . '/' . $file))
            ];
        }
    }
}

// Check if a specific log is requested
$viewLog = null;
$logContent = "";
if (isset($_GET['view']) && preg_match('/^[a-zA-Z0-9\-_.]+\.log$/', $_GET['view'])) {
    $filePath = $logsDir . '/' . $_GET['view'];
    if (file_exists($filePath)) {
        $viewLog = $_GET['view'];
        $logContent = file_get_contents($filePath);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | Teacher Portal</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Consolas', monospace;
            font-size: 13px;
            line-height: 1.5;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .log-level-INFO {
            color: #569cd6;
        }

        .log-level-SUCCESS {
            color: #4ec9b0;
        }

        .log-level-ERROR {
            color: #f44747;
            font-weight: bold;
        }

        .log-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>

<body class="dashboard-body">
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/dashboard-logo.png" alt="Logo" class="sidebar-logo">
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fa-solid fa-grid-2"></i>
                <span>Dashboard</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <i class="fa-solid fa-user-check"></i>
                <span>Attendance</span>
            </a>
            <a href="students.php" class="nav-item">
                <i class="fa-solid fa-users"></i>
                <span>Students</span>
            </a>
            <a href="subjects.php" class="nav-item">
                <i class="fa-solid fa-book"></i>
                <span>Subjects</span>
            </a>
            <a href="logs.php" class="nav-item active">
                <i class="fa-solid fa-receipt"></i>
                <span>Logs</span>
            </a>
            <a href="detention.php" class="nav-item">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Detention</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="api/logout.php" class="nav-item logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">
                <h1>System Logs</h1>
                <p>Track email deliveries and system activity</p>
            </div>
            <div class="user-profile">
                <div class="profile-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=random"
                        alt="Profile">
                    <div class="text">
                        <span class="name">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </span>
                        <span class="role">
                            <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-card">
            <?php if ($viewLog): ?>
                <div class="log-meta">
                    <a href="logs.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                    <h3>Viewing:
                        <?php echo htmlspecialchars($viewLog); ?>
                    </h3>
                </div>
                <div class="log-viewer">
                    <?php
                    $lines = explode("\n", $logContent);
                    foreach ($lines as $line) {
                        if (empty(trim($line)))
                            continue;
                        $class = '';
                        if (strpos($line, '[INFO]') !== false)
                            $class = 'log-level-INFO';
                        if (strpos($line, '[SUCCESS]') !== false)
                            $class = 'log-level-SUCCESS';
                        if (strpos($line, '[ERROR]') !== false)
                            $class = 'log-level-ERROR';
                        echo "<div class='{$class}'>" . htmlspecialchars($line) . "</div>";
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Execution History</h3>
                    <button id="sendReportsBtn" class="btn-primary" style="background: #4361ee; padding: 10px 20px;">
                        <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> Send Daily Reports Now
                    </button>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Log File Name</th>
                                <th>File Size</th>
                                <th>last Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logFiles)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 30px;">No logs found yet. Once you run
                                        the mailer, records will appear here.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logFiles as $log): ?>
                                    <tr>
                                        <td><i class="fa-solid fa-file-lines" style="margin-right: 10px; color: #888;"></i> <strong>
                                                <?php echo htmlspecialchars($log['name']); ?>
                                            </strong></td>
                                        <td>
                                            <?php echo $log['size']; ?>
                                        </td>
                                        <td>
                                            <?php echo $log['date']; ?>
                                        </td>
                                        <td>
                                            <a href="?view=<?php echo urlencode($log['name']); ?>" class="btn-primary"
                                                style="padding: 5px 15px; font-size: 12px; text-decoration: none;">View Detail</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        const sendReportsBtn = document.getElementById('sendReportsBtn');
        if (sendReportsBtn) {
            sendReportsBtn.addEventListener('click', async () => {
                if (!confirm('Are you sure you want to send daily reports?')) return;

                const originalText = sendReportsBtn.innerHTML;
                sendReportsBtn.disabled = true;
                sendReportsBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

                try {
                    const response = await fetch('api/trigger_mailer.php');
                    const text = await response.text();
                    console.log('Server Response:', text);

                    const result = JSON.parse(text);
                    if (result.success) {
                        alert(`✅ Success: ${result.sent} emails processed!`);
                        location.reload();
                    } else {
                        alert(`❌ Error: ${result.message}`);
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    alert('❌ Connection failed. Check if server is running.');
                } finally {
                    sendReportsBtn.disabled = false;
                    sendReportsBtn.innerHTML = originalText;
                }
            });
        }
    </script>
</body>

</html>