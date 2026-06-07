<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

$auth = new Auth();
$auth->requireRole(['hod']);
$user = $auth->getUser();

$db = Database::getInstance()->getConnection();
$hodDept = $user['department'] ?? '';

// Fetch HOD's own recent submissions (last 30 days)
$stmt = $db->prepare("
    SELECT * FROM hod_attendance_summary
    WHERE uploaded_by = :uid
    ORDER BY date DESC, semester ASC
    LIMIT 30
");
$stmt->execute(['uid' => $user['user_id']]);
$history = $stmt->fetchAll();

// Today's submission for this HOD (check if already submitted)
$today = date('Y-m-d');
$todayStmt = $db->prepare("
    SELECT COUNT(*) FROM hod_attendance_summary
    WHERE uploaded_by = :uid AND date = :today
");
$todayStmt->execute(['uid' => $user['user_id'], 'today' => $today]);
$todayCount = (int)$todayStmt->fetchColumn();

// Get distinct departments and semesters for dropdowns from students table
$depts = $db->query("SELECT DISTINCT department FROM students ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$sems  = [1,2,3,4,5,6,7,8];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard | JD College</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --hod-primary: #2a9d8f;
            --hod-secondary: #264653;
        }
        .sidebar-header-hod {
            background: linear-gradient(135deg, #2a9d8f, #264653);
            border-radius: 14px; padding: 16px; margin-bottom: 30px; text-align: center;
        }
        .nav-item.active { background: rgba(42,157,143,0.12); color: var(--hod-primary); }
        .nav-item:hover { color: var(--hod-primary); }

        .upload-form-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            padding: 28px; margin-bottom: 24px;
            border-top: 4px solid var(--hod-primary);
        }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 16px; }
        .form-field label { display: block; font-size: 13px; font-weight: 600; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 6px; }
        .form-field select,
        .form-field input[type="number"],
        .form-field input[type="date"] {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid #e0e0e0; border-radius: 10px;
            font-size: 14px; color: var(--text-color); outline: none;
            transition: border-color 0.2s;
            background: #fafafa;
        }
        .form-field select:focus,
        .form-field input:focus { border-color: var(--hod-primary); background: #fff; }

        .pct-preview {
            display: inline-block; padding: 10px 20px;
            background: linear-gradient(135deg,#2a9d8f15,#26465310);
            border: 1px solid #2a9d8f30; border-radius: 10px;
            font-size: 18px; font-weight: 700; color: var(--hod-primary);
            margin-top: 6px;
        }
        .btn-submit-hod {
            padding: 12px 30px;
            background: linear-gradient(135deg, #2a9d8f, #264653);
            color: #fff; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 10px;
            transition: opacity 0.2s, transform 0.2s;
        }
        .btn-submit-hod:hover { opacity: 0.9; transform: translateY(-1px); }
        #formMsg { margin-top: 14px; font-size: 14px; font-weight: 500; }
        .badge.pct-high   { background: rgba(42,157,143,0.12); color:#2a9d8f; }
        .badge.pct-medium { background: rgba(244,162,97,0.15);  color:#e76f51; }
        .badge.pct-low    { background: rgba(239,35,60,0.1);    color:#ef233c; }
        .today-banner {
            background: linear-gradient(135deg,#2a9d8f15,#26465308);
            border: 1px solid #2a9d8f30; border-radius: 12px;
            padding: 12px 18px; margin-bottom: 20px; font-size: 14px; color: #264653;
        }
    </style>
</head>
<body class="dashboard-body">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header-hod">
            <i class="fa-solid fa-person-chalkboard" style="font-size:28px; color:#fff;"></i>
            <div style="color:#fff; font-family:'Outfit',sans-serif; font-size:15px; font-weight:700; margin-top:6px;">HOD Portal</div>
            <div style="color:rgba(255,255,255,0.75); font-size:11px;"><?php echo htmlspecialchars($hodDept); ?> Department</div>
        </div>

        <nav class="sidebar-nav">
            <a href="hod_dashboard.php" class="nav-item active">
                <i class="fa-solid fa-upload"></i>
                <span>Upload Attendance</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div style="background:rgba(42,157,143,0.1); border-radius:10px; padding:10px 14px; margin-bottom:16px; font-size:13px;">
                <div style="font-weight:600; color:var(--text-color);"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div style="font-size:11px; color:var(--hod-primary);">HOD · <?php echo htmlspecialchars($hodDept); ?></div>
            </div>
            <a href="api/logout.php" class="nav-item logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <header class="topbar">
            <div class="page-title">
                <h1>Upload Attendance Summary</h1>
                <p><?php echo date('l, d F Y'); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($hodDept); ?> Department</p>
            </div>
            <div class="user-profile">
                <div class="profile-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=2a9d8f&color=fff" alt="HOD">
                    <div class="text">
                        <span class="name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="role" style="color:var(--hod-primary);">HOD</span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($todayCount > 0): ?>
        <div class="today-banner">
            <i class="fa-solid fa-circle-check" style="color:#2a9d8f;"></i>
            <strong>Today's report submitted.</strong> You can update it by submitting again — existing entry will be overwritten.
        </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="upload-form-card">
            <h3 style="font-family:'Outfit',sans-serif; font-size:18px; margin-bottom:20px;">
                <i class="fa-solid fa-file-arrow-up" style="color:var(--hod-primary);"></i>
                Submit Attendance Count
            </h3>
            <form id="hodForm">
                <div class="form-row">
                    <div class="form-field">
                        <label>Department</label>
                        <select name="department" id="deptSel" required>
                            <option value="<?php echo htmlspecialchars($hodDept ?: ''); ?>" selected>
                                <?php echo htmlspecialchars($hodDept ?: 'Select Department'); ?>
                            </option>
                            <?php foreach ($depts as $d): if ($d === $hodDept) continue; ?>
                            <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Semester</label>
                        <select name="semester" required>
                            <?php foreach ($sems as $s): ?>
                            <option value="<?php echo $s; ?>">Semester <?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Year</label>
                        <select name="year" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Date</label>
                        <input type="date" name="date" id="dateInput" max="<?php echo $today; ?>" value="<?php echo $today; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label>Total Students</label>
                        <input type="number" name="total_students" id="totalStu" min="1" placeholder="e.g. 60" required>
                    </div>
                    <div class="form-field">
                        <label>Present Count</label>
                        <input type="number" name="present_count" id="presentCnt" min="0" placeholder="e.g. 48" required>
                    </div>
                    <div class="form-field" style="display:flex; flex-direction:column; justify-content:flex-end;">
                        <label>Attendance %</label>
                        <div class="pct-preview" id="pctPreview">—</div>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                    <button type="submit" class="btn-submit-hod" id="submitBtn">
                        <i class="fa-solid fa-paper-plane"></i> Submit Report
                    </button>
                </div>
                <div id="formMsg"></div>
            </form>
        </div>

        <!-- History -->
        <div class="content-card">
            <div class="card-header">
                <h3>Submission History (Last 30 Days)</h3>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Year</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Attendance %</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px; color:var(--text-light);">No submissions yet. Upload today's attendance above.</td></tr>
                        <?php else: foreach ($history as $h):
                            $pct = (float)$h['attendance_percentage'];
                            $lvl = $pct>=75?'pct-high':($pct>=50?'pct-medium':'pct-low');
                        ?>
                        <tr>
                            <td><strong><?php echo date('D, d M Y', strtotime($h['date'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['department']); ?></td>
                            <td>Sem <?php echo $h['semester']; ?></td>
                            <td>Year <?php echo $h['year']; ?></td>
                            <td><?php echo $h['total_students']; ?></td>
                            <td><?php echo $h['present_count']; ?></td>
                            <td><span class="badge <?php echo $lvl; ?>"><?php echo $pct; ?>%</span></td>
                            <td style="font-size:12px; color:var(--text-light);"><?php echo date('d M, H:i', strtotime($h['uploaded_at'])); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Live % preview
        function updatePct() {
            const total   = parseInt(document.getElementById('totalStu').value) || 0;
            const present = parseInt(document.getElementById('presentCnt').value) || 0;
            const el = document.getElementById('pctPreview');
            if (total > 0) {
                const pct = Math.round((present / total) * 1000) / 10;
                el.textContent = pct + '%';
                el.style.color = pct >= 75 ? '#2a9d8f' : pct >= 50 ? '#e76f51' : '#ef233c';
            } else {
                el.textContent = '—';
                el.style.color = '';
            }
        }
        document.getElementById('totalStu').addEventListener('input', updatePct);
        document.getElementById('presentCnt').addEventListener('input', updatePct);

        // Form submit
        document.getElementById('hodForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const msg = document.getElementById('formMsg');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
            msg.textContent = '';

            try {
                const res  = await fetch('api/hod_submit.php', { method: 'POST', body: new FormData(this) });
                const data = await res.json();
                if (data.success) {
                    msg.innerHTML = `<span style="color:#2a9d8f;">✅ ${data.message}</span>`;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.innerHTML = `<span style="color:#ef233c;">❌ ${data.message}</span>`;
                }
            } catch (err) {
                msg.innerHTML = `<span style="color:#ef233c;">❌ Connection error</span>`;
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Report';
        });
    </script>
</body>
</html>
