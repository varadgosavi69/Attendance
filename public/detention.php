<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();
$auth->requireRole(['teacher', 'admin']);
$user = $auth->getUser();

// Default: show previous month
$defaultMonth = date('Y-m', strtotime('first day of last month'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detention Report | Teacher Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .detention-controls {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { display:block; margin-bottom:8px; font-size:14px; color:var(--text-light); font-weight:500; }
        .filter-group input[type="month"] {
            width: 100%; padding: 10px 15px; border-radius: 10px;
            border: 1px solid #e0e0e0; background: white; font-size: 14px; outline: none;
        }
        .filter-group input[type="month"]:focus { border-color: var(--primary-color); }

        /* Status badges */
        .badge.detained    { background: rgba(239,35,60,0.12); color:#ef233c; }
        .badge.at-risk     { background: rgba(251,133,0,0.12); color:#fb8500; }
        .badge.safe        { background: rgba(42,157,143,0.12); color:#2a9d8f; }

        /* Row color coding */
        tr.row-detained td { background-color: rgba(239,35,60,0.04); }
        tr.row-at-risk  td { background-color: rgba(251,133,0,0.04); }

        .summary-chips {
            display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .chip {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 500;
        }
        .chip.red    { background: rgba(239,35,60,0.1);  color:#ef233c; }
        .chip.orange { background: rgba(251,133,0,0.1);  color:#fb8500; }
        .chip.green  { background: rgba(42,157,143,0.1); color:#2a9d8f; }
        .chip i      { font-size: 18px; }

        .progress-bar-wrap {
            width: 80px; height: 8px; background: #eee; border-radius: 4px; display:inline-block;
            vertical-align: middle; margin-right: 6px; overflow:hidden;
        }
        .progress-bar-fill { height: 100%; border-radius: 4px; }

        .btn-danger {
            padding: 10px 20px; background: #ef233c; color: white; border: none;
            border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-danger:hover { background: #c9184a; }

        .btn-secondary {
            padding: 10px 20px; background: #f0f2f5; color: var(--text-color); border: none;
            border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-secondary:hover { background: #e0e2e5; }

        #resultsSection { display: none; }
        .notified-tag { font-size: 11px; color: #888; font-style: italic; }
    </style>
</head>

<body class="dashboard-body">
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/dashboard-logo.png" alt="JD College Logo" class="sidebar-logo">
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fa-solid fa-grid-2"></i><span>Dashboard</span>
            </a>
            <a href="attendance.php" class="nav-item">
                <i class="fa-solid fa-user-check"></i><span>Attendance</span>
            </a>
            <a href="students.php" class="nav-item">
                <i class="fa-solid fa-users"></i><span>Students</span>
            </a>
            <a href="subjects.php" class="nav-item">
                <i class="fa-solid fa-book"></i><span>Subjects</span>
            </a>
            <a href="logs.php" class="nav-item">
                <i class="fa-solid fa-receipt"></i><span>Logs</span>
            </a>
            <a href="detention.php" class="nav-item active">
                <i class="fa-solid fa-triangle-exclamation"></i><span>Detention</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="api/logout.php" class="nav-item logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">
                <h1>Monthly Detention Report</h1>
                <p>View attendance summaries and issue detention notices</p>
            </div>
            <div class="user-profile">
                <div class="profile-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=random" alt="Profile">
                    <div class="text">
                        <span class="name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Controls -->
        <div class="content-card" style="margin-bottom:25px;">
            <div class="detention-controls">
                <div class="filter-group">
                    <label><i class="fa-solid fa-calendar-days" style="margin-right:6px;"></i>Select Month</label>
                    <input type="month" id="monthPicker" value="<?php echo $defaultMonth; ?>">
                </div>
                <button id="loadReportBtn" class="btn-primary" style="padding:12px 24px;">
                    <i class="fa-solid fa-chart-bar" style="margin-right:8px;"></i>Load Report
                </button>
                <button id="generateBtn" class="btn-secondary" style="padding:12px 24px;">
                    <i class="fa-solid fa-rotate" style="margin-right:8px;"></i>Generate Detention
                </button>
                <button id="sendEmailsBtn" class="btn-danger" style="padding:12px 24px;">
                    <i class="fa-solid fa-paper-plane" style="margin-right:8px;"></i>Send Detention Notices
                </button>
            </div>
            <p style="font-size:13px; color:var(--text-light);">
                <i class="fa-solid fa-circle-info" style="margin-right:5px;"></i>
                Detention threshold: <strong><?php echo DETENTION_THRESHOLD; ?>%</strong> — Students below this are marked detained.
                <span style="margin-left:12px; color:#fb8500;">🟠 At-Risk: 75–80%</span>
                <span style="margin-left:8px; color:#ef233c;">🔴 Detained: below <?php echo DETENTION_THRESHOLD; ?>%</span>
            </p>
        </div>

        <!-- Results -->
        <div id="resultsSection">
            <!-- Summary Chips -->
            <div class="summary-chips">
                <div class="chip green"><i class="fa-solid fa-users"></i><span>Total: <strong id="totalCount">0</strong></span></div>
                <div class="chip red"><i class="fa-solid fa-triangle-exclamation"></i><span>Detained: <strong id="detainedCount">0</strong></span></div>
                <div class="chip orange"><i class="fa-solid fa-circle-exclamation"></i><span>At-Risk (75–80%): <strong id="atRiskCount">0</strong></span></div>
            </div>

            <!-- Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3 id="reportTitle">Attendance Report</h3>
                    <input type="text" id="searchInput" placeholder="🔍 Search student..." style="padding:8px 14px; border-radius:8px; border:1px solid #e0e0e0; font-size:13px; outline:none;">
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Roll No.</th>
                                <th>Student Name</th>
                                <th>Branch</th>
                                <th>Sem</th>
                                <th>Total Classes</th>
                                <th>Attended</th>
                                <th>Absent</th>
                                <th>Attendance %</th>
                                <th>Status</th>
                                <th>Notice</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const loadBtn       = document.getElementById('loadReportBtn');
        const generateBtn   = document.getElementById('generateBtn');
        const sendEmailsBtn = document.getElementById('sendEmailsBtn');
        const monthPicker   = document.getElementById('monthPicker');
        const tableBody     = document.getElementById('reportTableBody');
        const resultsSection= document.getElementById('resultsSection');
        const searchInput   = document.getElementById('searchInput');

        let currentStudents = [];
        const THRESHOLD = <?php echo DETENTION_THRESHOLD; ?>;

        // ---- Load Report (read-only view) ----
        loadBtn.addEventListener('click', async () => {
            const month = monthPicker.value;
            if (!month) return alert('Please select a month.');

            loadBtn.disabled = true;
            loadBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            tableBody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px;">Loading...</td></tr>';
            resultsSection.style.display = 'block';

            try {
                const res  = await fetch(`api/get_monthly_attendance.php?month=${month}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message);
                currentStudents = data.data;
                renderTable(currentStudents, month);
                generateBtn.disabled  = false;
                sendEmailsBtn.disabled= false;
            } catch (e) {
                tableBody.innerHTML = `<tr><td colspan="10" style="text-align:center;color:#ef233c;">${e.message}</td></tr>`;
            } finally {
                loadBtn.disabled  = false;
                loadBtn.innerHTML = '<i class="fa-solid fa-chart-bar" style="margin-right:8px;"></i>Load Report';
            }
        });

        // ---- Generate Detention (calculate + store, no email) ----
        generateBtn.addEventListener('click', async () => {
            const month = monthPicker.value;
            if (!month) return alert('Please select a month.');
            if (!confirm(`Generate detention report for ${month}? This will update the database.`)) return;
            await runGenerate(month, false);
        });

        // ---- Send Detention Emails ----
        sendEmailsBtn.addEventListener('click', async () => {
            const month = monthPicker.value;
            if (!month) return alert('Please select a month.');
            if (!confirm(`Send detention notices for ${month}? This will send emails to detained students.`)) return;
            await runGenerate(month, true);
        });

        async function runGenerate(monthStr, sendEmails) {
            const parts = monthStr.split('-');
            const year  = parseInt(parts[0]);
            const month = parseInt(parts[1]);

            const activeBtn = sendEmails ? sendEmailsBtn : generateBtn;
            const origText  = activeBtn.innerHTML;
            activeBtn.disabled = true;
            activeBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

            try {
                const res  = await fetch('api/generate_detention.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ year, month, send_emails: sendEmails })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.message);
                currentStudents = data.students;
                renderTable(currentStudents, monthStr);

                let msg = `✅ Report generated!\n📊 Total: ${data.total_students} | 🔴 Detained: ${data.detained_count}`;
                if (sendEmails) msg += `\n📧 Emails sent: ${data.emails_sent} | Failed: ${data.emails_failed}`;
                alert(msg);
            } catch (e) {
                alert('❌ Error: ' + e.message);
            } finally {
                activeBtn.disabled = false;
                activeBtn.innerHTML = origText;
            }
        }

        // ---- Render Table ----
        function renderTable(students, month) {
            const monthLabel = new Date(month + '-15').toLocaleString('default', { month: 'long', year: 'numeric' });
            document.getElementById('reportTitle').textContent = `Attendance — ${monthLabel}`;

            let detained = 0, atRisk = 0;
            tableBody.innerHTML = '';

            students.forEach(s => {
                const pct  = parseFloat(s.attendance_percentage) || 0;
                const total = parseInt(s.total_classes) || 0;
                const att  = parseInt(s.attended_classes) || 0;
                const abs  = parseInt(s.absent_classes) || (total - att);

                let rowClass = '', badgeClass = 'safe', statusLabel = '🟢 Safe';
                if (pct < THRESHOLD) {
                    rowClass = 'row-detained'; badgeClass = 'detained'; statusLabel = '🔴 Detained';
                    detained++;
                } else if (pct < 80) {
                    rowClass = 'row-at-risk'; badgeClass = 'at-risk'; statusLabel = '🟠 At-Risk';
                    atRisk++;
                }

                const barColor = pct >= 80 ? '#2a9d8f' : pct >= THRESHOLD ? '#fb8500' : '#ef233c';
                const noticeCell = s.notified_at
                    ? `<span class="notified-tag">✉️ Sent ${new Date(s.notified_at).toLocaleDateString('en-IN')}</span>`
                    : `<span style="color:#ccc; font-size:12px;">—</span>`;

                tableBody.innerHTML += `
                    <tr class="${rowClass}" data-name="${s.student_name.toLowerCase()} ${s.roll_number.toLowerCase()}">
                        <td><strong>${s.roll_number}</strong></td>
                        <td>${s.student_name}</td>
                        <td>${s.department}</td>
                        <td>${s.semester}</td>
                        <td>${total}</td>
                        <td style="color:#2a9d8f; font-weight:500;">${att}</td>
                        <td style="color:#ef233c; font-weight:500;">${abs}</td>
                        <td>
                            <span class="progress-bar-wrap">
                                <span class="progress-bar-fill" style="width:${Math.min(pct,100)}%; background:${barColor};"></span>
                            </span>
                            <strong style="color:${barColor};">${pct}%</strong>
                        </td>
                        <td><span class="badge ${badgeClass}">${statusLabel}</span></td>
                        <td>${noticeCell}</td>
                    </tr>
                `;
            });

            document.getElementById('totalCount').textContent   = students.length;
            document.getElementById('detainedCount').textContent = detained;
            document.getElementById('atRiskCount').textContent   = atRisk;
        }

        // ---- Search Filter ----
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase();
            tableBody.querySelectorAll('tr').forEach(row => {
                row.style.display = row.dataset.name && row.dataset.name.includes(q) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
