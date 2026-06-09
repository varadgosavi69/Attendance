<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

$auth = new Auth();
$auth->requireRole(['principal']);
$user = $auth->getUser();

$db = Database::getInstance()->getConnection();

// Current active tab
$tab = $_GET['tab'] ?? 'overview';

// ---- Data for Overview tab ----
$today = date('Y-m-d');
$totalStudents = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalSubjects = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$totalFaculty  = $db->query("SELECT COUNT(*) FROM faculty")->fetchColumn();

$avgAttendance = $db->query("SELECT ROUND(AVG(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) * 100, 1) FROM attendance")->fetchColumn() ?? 0;

// Department wise counts
$deptCounts = $db->query("SELECT department, COUNT(*) as cnt FROM students GROUP BY department ORDER BY department")->fetchAll(PDO::FETCH_ASSOC);

// Today's attendance summary
$todayPresent = $db->query("SELECT COUNT(*) FROM attendance WHERE status='Present' AND attendance_date = CURRENT_DATE")->fetchColumn();
$todayAbsent  = $db->query("SELECT COUNT(*) FROM attendance WHERE status='Absent' AND attendance_date = CURRENT_DATE")->fetchColumn();
$todayTotal   = $todayPresent + $todayAbsent;

// ---- Data for Students tab ----
$students = [];
if ($tab === 'students') {
    $students = $db->query("SELECT * FROM students ORDER BY department, roll_number")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Data for Subjects tab ----
$subjects = [];
if ($tab === 'subjects') {
    $subjects = $db->query("SELECT * FROM subjects ORDER BY department, semester, subject_name")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Data for Reports tab ----
$reportData = [];
if ($tab === 'reports') {
    $reportMonth = $_GET['month'] ?? date('Y-m', strtotime('first day of last month'));
    $parts = explode('-', $reportMonth);
    $rYear = (int)$parts[0];
    $rMonth = (int)$parts[1];
    $monthStart = sprintf('%04d-%02d-01', $rYear, $rMonth);
    $monthEnd   = date('Y-m-t', strtotime($monthStart));

    $reportData = $db->query("
        SELECT s.student_id, s.roll_number, s.student_name, s.department, s.semester,
               COUNT(a.attendance_id) AS total_classes,
               SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS attended,
               SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent,
               ROUND(SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.attendance_id), 0) * 100, 2) AS pct
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id AND a.attendance_date BETWEEN '$monthStart' AND '$monthEnd'
        GROUP BY s.student_id, s.roll_number, s.student_name, s.department, s.semester
        ORDER BY s.department, s.roll_number
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Data for Detention tab ----
$detentionData = [];
if ($tab === 'detention') {
    $detMonth = $_GET['month'] ?? date('Y-m', strtotime('first day of last month'));
    $parts = explode('-', $detMonth);
    $dYear = (int)$parts[0];
    $dMonth = (int)$parts[1];
    $dMonthStart = sprintf('%04d-%02d-01', $dYear, $dMonth);
    $dMonthEnd   = date('Y-m-t', strtotime($dMonthStart));

    $detentionData = $db->query("
        SELECT s.student_id, s.roll_number, s.student_name, s.email, s.department, s.semester,
               COUNT(a.attendance_id) AS total_classes,
               SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS attended,
               ROUND(SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.attendance_id), 0) * 100, 2) AS pct
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id AND a.attendance_date BETWEEN '$dMonthStart' AND '$dMonthEnd'
        GROUP BY s.student_id, s.roll_number, s.student_name, s.email, s.department, s.semester
        HAVING pct < " . DETENTION_THRESHOLD . " OR pct IS NULL
        ORDER BY pct ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard | <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --principal-primary: #7b2d8b;
            --principal-secondary: #c0392b;
            --principal-gradient: linear-gradient(135deg, #7b2d8b, #c0392b);
        }
        .sidebar { border-right-color: #f0e0f5; }
        .sidebar-header { background: var(--principal-gradient); border-radius: 14px; padding: 16px; margin-bottom: 30px; }
        .sidebar-title { color: #fff; font-family: 'Outfit', sans-serif; font-size: 15px; font-weight: 700; text-align: center; margin-top: 8px; }
        .sidebar-subtitle { color: rgba(255,255,255,0.75); font-size: 11px; text-align: center; }
        .nav-item.active { background: rgba(123,45,139,0.12); color: var(--principal-primary); }
        .nav-item:hover { color: var(--principal-primary); }

        .dept-card {
            background: #fff; border-radius: 14px; padding: 18px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border-left: 4px solid #4361ee; transition: transform 0.2s;
        }
        .dept-card:hover { transform: translateY(-3px); }
        .dept-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }

        .tab-filter { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-filter label { font-size: 14px; color: var(--text-light); font-weight: 500; }
        .tab-filter input[type="month"] {
            padding: 10px 15px; border-radius: 10px; border: 1px solid #e0e0e0;
            background: white; font-size: 14px; outline: none;
        }
        .tab-filter .btn-primary { padding: 10px 20px; }

        .badge.detained { background: rgba(239,35,60,0.12); color: #ef233c; }
        .badge.safe { background: rgba(42,157,143,0.12); color: #2a9d8f; }
        .badge.at-risk { background: rgba(251,133,0,0.12); color: #fb8500; }
        tr.row-detained td { background-color: rgba(239,35,60,0.04); }

        .progress-bar-wrap { width: 80px; height: 8px; background: #eee; border-radius: 4px; display: inline-block; vertical-align: middle; margin-right: 6px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 4px; }

        .summary-chips { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .chip { display: flex; align-items: center; gap: 10px; padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 500; }
        .chip.red { background: rgba(239,35,60,0.1); color: #ef233c; }
        .chip.green { background: rgba(42,157,143,0.1); color: #2a9d8f; }
        .chip.blue { background: rgba(67,97,238,0.1); color: #4361ee; }
        .chip i { font-size: 18px; }

        .search-box { position: relative; width: 280px; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .search-box input {
            width: 100%; padding: 8px 15px 8px 35px; border: 1px solid #e0e0e0;
            border-radius: 8px; font-size: 14px; outline: none;
        }

        .topbar-date { font-size: 13px; color: var(--text-light); margin-top: 2px; }
    </style>
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div style="text-align:center;">
                <i class="fa-solid fa-shield-halved" style="font-size:28px; color:#fff;"></i>
                <div class="sidebar-title">Principal Portal</div>
                <div class="sidebar-subtitle"><?php echo COLLEGE_NAME; ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="?tab=overview" class="nav-item <?php echo $tab === 'overview' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Overview</span>
            </a>
            <a href="?tab=reports" class="nav-item <?php echo $tab === 'reports' ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Attendance Reports</span>
            </a>
            <a href="?tab=students" class="nav-item <?php echo $tab === 'students' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i>
                <span>Students</span>
            </a>
            <a href="?tab=subjects" class="nav-item <?php echo $tab === 'subjects' ? 'active' : ''; ?>">
                <i class="fa-solid fa-book"></i>
                <span>Subjects</span>
            </a>
            <a href="?tab=detention" class="nav-item <?php echo $tab === 'detention' ? 'active' : ''; ?>">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Detention</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:#7b2d8b;">
                <i class="fa-solid fa-user-tie"></i>
                <div>
                    <div style="font-weight:600;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div style="font-size:11px;color:#a08aaa;">Principal</div>
                </div>
            </div>
            <a href="api/logout.php" class="nav-item logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">
                <?php
                $titles = [
                    'overview' => ['Overview Dashboard', date('l, d F Y')],
                    'reports'  => ['Attendance Reports', 'Monthly student-wise attendance breakdown'],
                    'students' => ['Student Directory', 'All enrolled students'],
                    'subjects' => ['Subject Catalog', 'All registered subjects'],
                    'detention'=> ['Detention Report', 'Students below ' . DETENTION_THRESHOLD . '% attendance'],
                ];
                $t = $titles[$tab] ?? $titles['overview'];
                ?>
                <h1><?php echo $t[0]; ?></h1>
                <p class="topbar-date"><?php echo $t[1]; ?></p>
            </div>
            <div class="user-profile">
                <div class="profile-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=7b2d8b&color=fff" alt="Principal">
                    <div class="text">
                        <span class="name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="role" style="color:#7b2d8b;">Principal</span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($tab === 'overview'): ?>
        <!-- ==================== OVERVIEW TAB ==================== -->
        <div class="stats-grid" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="icon-box" style="background:var(--principal-gradient);"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info">
                    <h3>Total Students</h3>
                    <p class="number"><?php echo $totalStudents; ?></p>
                    <span class="trend neutral">Across all departments</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background:linear-gradient(135deg,#4361ee,#4cc9f0);"><i class="fa-solid fa-user-check"></i></div>
                <div class="stat-info">
                    <h3>Avg Attendance</h3>
                    <p class="number"><?php echo $avgAttendance; ?>%</p>
                    <span class="trend <?php echo $avgAttendance >= 75 ? 'up' : 'down'; ?>"><?php echo $avgAttendance >= 75 ? '✓ Above threshold' : '⚠ Below threshold'; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background:linear-gradient(135deg,#2a9d8f,#264653);"><i class="fa-solid fa-book"></i></div>
                <div class="stat-info">
                    <h3>Total Subjects</h3>
                    <p class="number"><?php echo $totalSubjects; ?></p>
                    <span class="trend neutral">Registered</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background:linear-gradient(135deg,#f4a261,#e76f51);"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="stat-info">
                    <h3>Faculty Members</h3>
                    <p class="number"><?php echo $totalFaculty; ?></p>
                    <span class="trend neutral">Active</span>
                </div>
            </div>
        </div>

        <!-- Today's Summary -->
        <div class="content-card" style="margin-bottom:25px;">
            <div class="card-header"><h3>Today's Attendance — <?php echo date('d M Y'); ?></h3></div>
            <?php if ($todayTotal > 0): ?>
                <div class="summary-chips">
                    <div class="chip green"><i class="fa-solid fa-check-circle"></i> Present: <strong><?php echo $todayPresent; ?></strong></div>
                    <div class="chip red"><i class="fa-solid fa-times-circle"></i> Absent: <strong><?php echo $todayAbsent; ?></strong></div>
                    <div class="chip blue"><i class="fa-solid fa-users"></i> Total Records: <strong><?php echo $todayTotal; ?></strong></div>
                </div>
            <?php else: ?>
                <p style="padding:20px;color:var(--text-light);text-align:center;">No attendance recorded today yet.</p>
            <?php endif; ?>
        </div>

        <!-- Department Cards -->
        <div class="content-card">
            <div class="card-header"><h3>Department-wise Enrollment</h3></div>
            <div class="dept-grid" style="padding:15px 0;">
                <?php foreach ($deptCounts as $d): ?>
                <div class="dept-card">
                    <div style="font-size:12px;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                        <?php echo htmlspecialchars($d['department']); ?> Department
                    </div>
                    <div style="font-family:'Outfit',sans-serif;font-size:28px;font-weight:700;color:var(--text-color);">
                        <?php echo $d['cnt']; ?> <span style="font-size:14px;font-weight:400;color:var(--text-light);">students</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php elseif ($tab === 'students'): ?>
        <!-- ==================== STUDENTS TAB ==================== -->
        <div class="summary-chips">
            <div class="chip blue"><i class="fa-solid fa-users"></i> Total: <strong><?php echo count($students); ?></strong></div>
        </div>
        <div class="content-card">
            <div class="card-header">
                <h3>All Students</h3>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search students..." id="searchInput">
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Roll No.</th><th>Name</th><th>Email</th><th>Branch</th><th>Semester</th></tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['roll_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['student_name']); ?></td>
                            <td><span style="font-size:13px;color:var(--text-light);"><?php echo htmlspecialchars($s['email']); ?></span></td>
                            <td><span class="badge"><?php echo htmlspecialchars($s['department']); ?></span></td>
                            <td>Sem <?php echo $s['semester']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'subjects'): ?>
        <!-- ==================== SUBJECTS TAB ==================== -->
        <div class="summary-chips">
            <div class="chip blue"><i class="fa-solid fa-book"></i> Total: <strong><?php echo count($subjects); ?></strong></div>
        </div>
        <div class="content-card">
            <div class="card-header">
                <h3>All Subjects</h3>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search subjects..." id="searchInput">
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Code</th><th>Subject Name</th><th>Branch</th><th>Semester</th></tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($subjects as $s): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($s['subject_code']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($s['subject_name']); ?></strong></td>
                            <td><span class="badge"><?php echo htmlspecialchars($s['department']); ?></span></td>
                            <td>Semester <?php echo $s['semester']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'reports'): ?>
        <!-- ==================== REPORTS TAB ==================== -->
        <form class="tab-filter" method="get">
            <input type="hidden" name="tab" value="reports">
            <label><i class="fa-solid fa-calendar-days" style="margin-right:6px;"></i>Select Month:</label>
            <input type="month" name="month" value="<?php echo $reportMonth; ?>">
            <button type="submit" class="btn-primary"><i class="fa-solid fa-search" style="margin-right:6px;"></i>Load Report</button>
        </form>

        <?php
        $safe = array_filter($reportData, fn($r) => ($r['pct'] ?? 0) >= 75);
        $detained = array_filter($reportData, fn($r) => ($r['pct'] ?? 0) < 75 && ($r['pct'] ?? 0) > 0);
        $noData = array_filter($reportData, fn($r) => ($r['pct'] ?? 0) == 0);
        ?>
        <div class="summary-chips">
            <div class="chip green"><i class="fa-solid fa-users"></i> Total: <strong><?php echo count($reportData); ?></strong></div>
            <div class="chip green"><i class="fa-solid fa-check"></i> Safe (≥75%): <strong><?php echo count($safe); ?></strong></div>
            <div class="chip red"><i class="fa-solid fa-triangle-exclamation"></i> Below 75%: <strong><?php echo count($detained); ?></strong></div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Attendance — <?php echo date('F Y', strtotime($reportMonth . '-01')); ?></h3>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search student..." id="searchInput">
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Roll No.</th><th>Name</th><th>Branch</th><th>Sem</th><th>Total</th><th>Present</th><th>Absent</th><th>Attendance %</th><th>Status</th></tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($reportData as $r):
                            $pct = (float)($r['pct'] ?? 0);
                            $level = $pct >= 75 ? 'safe' : 'detained';
                            $rowClass = $pct < 75 ? 'row-detained' : '';
                            $barColor = $pct >= 80 ? '#2a9d8f' : ($pct >= 75 ? '#fb8500' : '#ef233c');
                            $statusLabel = $pct >= 75 ? '🟢 Safe' : '🔴 Detained';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><strong><?php echo htmlspecialchars($r['roll_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['department']); ?></td>
                            <td><?php echo $r['semester']; ?></td>
                            <td><?php echo (int)$r['total_classes']; ?></td>
                            <td style="color:#2a9d8f;font-weight:500;"><?php echo (int)$r['attended']; ?></td>
                            <td style="color:#ef233c;font-weight:500;"><?php echo (int)$r['absent']; ?></td>
                            <td>
                                <span class="progress-bar-wrap">
                                    <span class="progress-bar-fill" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $barColor; ?>;"></span>
                                </span>
                                <strong style="color:<?php echo $barColor; ?>;"><?php echo $pct; ?>%</strong>
                            </td>
                            <td><span class="badge <?php echo $level; ?>"><?php echo $statusLabel; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'detention'): ?>
        <!-- ==================== DETENTION TAB ==================== -->
        <form class="tab-filter" method="get">
            <input type="hidden" name="tab" value="detention">
            <label><i class="fa-solid fa-calendar-days" style="margin-right:6px;"></i>Select Month:</label>
            <input type="month" name="month" value="<?php echo $detMonth; ?>">
            <button type="submit" class="btn-primary"><i class="fa-solid fa-search" style="margin-right:6px;"></i>Check Detention</button>
        </form>

        <div class="summary-chips">
            <div class="chip red"><i class="fa-solid fa-triangle-exclamation"></i> Detained (below <?php echo DETENTION_THRESHOLD; ?>%): <strong><?php echo count($detentionData); ?></strong></div>
        </div>

        <?php if (empty($detentionData)): ?>
        <div class="content-card" style="text-align:center;padding:40px;">
            <i class="fa-solid fa-face-smile" style="font-size:48px;color:#2a9d8f;margin-bottom:15px;"></i>
            <h3 style="font-family:'Outfit',sans-serif;font-size:18px;">No detained students!</h3>
            <p style="color:var(--text-light);">All students have attendance above <?php echo DETENTION_THRESHOLD; ?>% for <?php echo date('F Y', strtotime($dMonthStart)); ?>.</p>
        </div>
        <?php else: ?>
        <div class="content-card">
            <div class="card-header">
                <h3>Detained Students — <?php echo date('F Y', strtotime($dMonthStart)); ?></h3>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search..." id="searchInput">
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Roll No.</th><th>Name</th><th>Email</th><th>Branch</th><th>Sem</th><th>Total</th><th>Present</th><th>Attendance %</th></tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($detentionData as $d):
                            $pct = (float)($d['pct'] ?? 0);
                            $barColor = '#ef233c';
                        ?>
                        <tr class="row-detained">
                            <td><strong><?php echo htmlspecialchars($d['roll_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['student_name']); ?></td>
                            <td><span style="font-size:12px;color:var(--text-light);"><?php echo htmlspecialchars($d['email']); ?></span></td>
                            <td><?php echo htmlspecialchars($d['department']); ?></td>
                            <td><?php echo $d['semester']; ?></td>
                            <td><?php echo (int)$d['total_classes']; ?></td>
                            <td style="color:#2a9d8f;font-weight:500;"><?php echo (int)$d['attended']; ?></td>
                            <td>
                                <span class="progress-bar-wrap">
                                    <span class="progress-bar-fill" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $barColor; ?>;"></span>
                                </span>
                                <strong style="color:<?php echo $barColor; ?>;"><?php echo $pct; ?>%</strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </main>

    <script>
        // Search filter for all tabs
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const term = this.value.toLowerCase();
                const rows = document.querySelectorAll('#tableBody tr');
                rows.forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>
