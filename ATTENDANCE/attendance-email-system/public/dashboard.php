<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$auth->requireRole(['teacher', 'admin', 'hod']);

$user = $auth->getUser();

// Fetch Dashboard Stats
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();

// 1. Total Students
$totalStudents = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();

// 2. Avg Attendance
$avgAttendance = $db->query("SELECT ROUND(AVG(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) * 100, 1) FROM attendance")->fetchColumn() ?? 0;

// 3. Emails Sent (Absentees today)
$emailsSent = $db->query("SELECT COUNT(*) FROM attendance WHERE status = 'Absent' AND attendance_date = CURRENT_DATE")->fetchColumn();

// 4. Classes Today
$classesToday = $db->query("SELECT COUNT(DISTINCT subject_id) FROM attendance WHERE attendance_date = CURRENT_DATE")->fetchColumn();

// 5. Recent Activity
$recentStmt = $db->query("
    SELECT a.*, s.student_name, sub.subject_name 
    FROM attendance a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN subjects sub ON a.subject_id = sub.subject_id 
    ORDER BY a.marked_at DESC 
    LIMIT 5
");
$recentActivity = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal | Dashboard</title>
    <!-- Fonts & Icons -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Styles -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="dashboard-body">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/dashboard-logo.png" alt="JD College Logo" class="sidebar-logo">
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
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
            <a href="logs.php" class="nav-item">
                <i class="fa-solid fa-receipt"></i>
                <span>Logs</span>
            </a>
            <a href="detention.php" class="nav-item">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Detention</span>
            </a>
            <a href="subjects.php" class="nav-item">
                <i class="fa-solid fa-book"></i>
                <span>Subjects</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="api/logout.php" class="nav-item logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Welcome back,
                    <?php echo htmlspecialchars($user['full_name']); ?> 👋
                </p>
            </div>

            <div class="user-profile">
                <div class="notification-bell">
                    <i class="fa-regular fa-bell"></i>
                    <span class="badge">3</span>
                </div>
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

        <!-- Dashboard Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon-box purple">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Students</h3>
                    <p class="number"><?php echo number_format($totalStudents); ?></p>
                    <span class="trend neutral">Currently Enrolled</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box blue">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Avg. Attendance</h3>
                    <p class="number"><?php echo $avgAttendance; ?>%</p>
                    <span class="trend up"><i class="fa-solid fa-arrow-up"></i> Overall</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box orange">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
                <div class="stat-info">
                    <h3>Emails Sent</h3>
                    <p class="number"><?php echo number_format($emailsSent); ?></p>
                    <span class="trend down"><i class="fa-solid fa-arrow-down"></i> Today</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box green">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Classes Today</h3>
                    <p class="number"><?php echo $classesToday; ?></p>
                    <span class="trend neutral">On Track</span>
                </div>
            </div>
        </div>

        <!-- Recent Attendance Table -->
        <div class="content-card">
            <div class="card-header">
                <h3>Recent Attendance Activity</h3>
                <button class="btn-primary">View All</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentActivity)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 20px;">No recent attendance records
                                    found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <div class="student-cell">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($activity['student_name']); ?>&background=random"
                                                alt="">
                                            <span><?php echo htmlspecialchars($activity['student_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['subject_name']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($activity['attendance_date'])); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo strtolower($activity['status']); ?>">
                                            <?php echo htmlspecialchars($activity['status']); ?>
                                        </span>
                                    </td>
                                    <td><button class="btn-icon"><i class="fa-solid fa-ellipsis-vertical"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script src="js/dashboard.js"></script>
</body>

</html>