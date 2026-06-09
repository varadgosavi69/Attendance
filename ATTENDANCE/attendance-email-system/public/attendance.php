<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';

$auth = new Auth();
$auth->requireRole(['teacher', 'admin']);
$user = $auth->getUser();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal | Attendance</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .attendance-filters {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-light);
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 15px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            background: white;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--primary-color);
        }

        .student-list-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            padding: 25px;
            display: none;
            /* Shown after selection */
        }

        .attendance-actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .status-toggle {
            display: flex;
            gap: 10px;
        }

        .status-toggle label {
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #e0e0e0;
            transition: all 0.2s;
        }

        .status-toggle input {
            display: none;
        }

        .status-toggle input:checked+.label-present {
            background: rgba(42, 157, 143, 0.1);
            color: #2a9d8f;
            border-color: #2a9d8f;
        }

        .status-toggle input:checked+.label-absent {
            background: rgba(239, 35, 60, 0.1);
            color: #ef233c;
            border-color: #ef233c;
        }

        .loader {
            display: none;
            text-align: center;
            padding: 40px;
        }
    </style>
</head>

<body class="dashboard-body">
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="assets/images/dashboard-logo.png" alt="JD College Logo" class="sidebar-logo">
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fa-solid fa-grid-2"></i>
                <span>Dashboard</span>
            </a>
            <a href="attendance.php" class="nav-item active">
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
            <a href="subjects.php" class="nav-item">
                <i class="fa-solid fa-book"></i>
                <span>Subjects</span>
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
                <h1>Mark Attendance</h1>
                <p>Select subject and date to record attendance</p>
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

        <div class="content-card mb-4" style="margin-bottom: 25px;">
            <div class="attendance-filters">
                <div class="filter-group">
                    <label>Branch</label>
                    <select id="branchSelect">
                        <option value="">Select Branch</option>
                        <option value="CSE">CSE (Computer Science)</option>
                        <option value="ME">ME (Mechanical)</option>
                        <option value="EE">EE (Electrical)</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Year</label>
                    <select id="yearSelect">
                        <option value="">Select Year</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Semester</label>
                    <select id="semesterSelect">
                        <option value="">Select Semester</option>
                        <!-- Populated based on Year -->
                    </select>
                </div>
                <div class="filter-group">
                    <label>Subject</label>
                    <select id="subjectSelect">
                        <option value="">Choose a subject...</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date</label>
                    <input type="date" id="attendanceDate" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filter-group" style="flex: 0 0 150px; display: flex; align-items: flex-end;">
                    <button id="loadStudents" class="btn-primary" style="width: 100%; padding: 12px;">Load
                        Students</button>
                </div>
            </div>
        </div>

        <div id="loader" class="loader">
            <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="color: var(--primary-color);"></i>
            <p style="margin-top: 10px; color: var(--text-light);">Loading student list...</p>
        </div>

        <div id="studentListCard" class="student-list-card">
            <div class="card-header">
                <h3>Student List</h3>
                <div class="bulk-actions">
                    <button class="btn-secondary btn-sm" onclick="markAll('Present')">All Present</button>
                    <button class="btn-secondary btn-sm" onclick="markAll('Absent')">All Absent</button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Roll No.</th>
                            <th>Student Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <!-- Loaded dynamically -->
                    </tbody>
                </table>
            </div>
            <div class="attendance-actions">
                <button id="saveAttendance" class="btn-primary">Save Attendance</button>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const branchSelect = document.getElementById('branchSelect');
            const yearSelect = document.getElementById('yearSelect');
            const semesterSelect = document.getElementById('semesterSelect');
            const subjectSelect = document.getElementById('subjectSelect');
            const loadBtn = document.getElementById('loadStudents');
            const saveBtn = document.getElementById('saveAttendance');
            const loader = document.getElementById('loader');
            const studentCard = document.getElementById('studentListCard');
            const tableBody = document.getElementById('studentTableBody');

            let allSubjects = [];

            // Load Subjects initially
            fetch('api/get_subjects.php')
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        allSubjects = res.data;
                    }
                });

            // Handle Year -> Semester mapping
            yearSelect.addEventListener('change', () => {
                const year = yearSelect.value;
                semesterSelect.innerHTML = '<option value="">Select Semester</option>';
                if (year) {
                    const sem1 = (year * 2) - 1;
                    const sem2 = year * 2;
                    semesterSelect.innerHTML += `<option value="${sem1}">Semester ${sem1}</option>`;
                    semesterSelect.innerHTML += `<option value="${sem2}">Semester ${sem2}</option>`;
                }
                updateSubjectFilter();
            });

            branchSelect.addEventListener('change', updateSubjectFilter);
            semesterSelect.addEventListener('change', updateSubjectFilter);

            function updateSubjectFilter() {
                const branch = branchSelect.value;
                const sem = semesterSelect.value;

                subjectSelect.innerHTML = '<option value="">Choose a subject...</option>';

                if (branch && sem) {
                    const filtered = allSubjects.filter(s => s.department === branch && s.semester == sem);
                    filtered.forEach(sub => {
                        const opt = document.createElement('option');
                        opt.value = sub.subject_id;
                        opt.textContent = `${sub.subject_name} (${sub.subject_code})`;
                        subjectSelect.appendChild(opt);
                    });
                }
            }

            loadBtn.addEventListener('click', () => {
                const branch = branchSelect.value;
                const sem = semesterSelect.value;
                const subId = subjectSelect.value;

                if (!branch || !sem || !subId) {
                    return alert('Please select Branch, Semester, and Subject');
                }

                loader.style.display = 'block';
                studentCard.style.display = 'none';

                fetch(`api/get_attendance_students.php?branch=${branch}&semester=${sem}`)
                    .then(r => r.json())
                    .then(res => {
                        loader.style.display = 'none';
                        if (res.success) {
                            tableBody.innerHTML = '';
                            if (res.data.length === 0) {
                                tableBody.innerHTML = '<tr><td colspan="3" style="text-align:center">No students found for this selection</td></tr>';
                            } else {
                                res.data.forEach(s => {
                                    const row = `
                                        <tr>
                                            <td>${s.roll_number}</td>
                                            <td>${s.student_name}</td>
                                            <td>
                                                <div class="status-toggle">
                                                    <input type="radio" name="status[${s.student_id}]" value="Present" id="p${s.student_id}" checked>
                                                    <label for="p${s.student_id}" class="label-present">Present</label>
                                                    <input type="radio" name="status[${s.student_id}]" value="Absent" id="a${s.student_id}">
                                                    <label for="a${s.student_id}" class="label-absent">Absent</label>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                    tableBody.innerHTML += row;
                                });
                            }
                            studentCard.style.display = 'block';
                        }
                    });
            });

            saveBtn.addEventListener('click', () => {
                const subId = subjectSelect.value;
                const date = document.getElementById('attendanceDate').value;
                const records = {};

                document.querySelectorAll('.status-toggle input:checked').forEach(input => {
                    const studentId = input.name.match(/\[(\d+)\]/)[1];
                    records[studentId] = input.value;
                });

                if (Object.keys(records).length === 0) return alert('No students to mark');

                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

                fetch('api/mark_attendance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subject_id: subId, date: date, records: records })
                })
                    .then(r => r.json())
                    .then(res => {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Attendance';
                        if (res.success) {
                            alert('Attendance saved successfully!');
                        } else {
                            alert('Error: ' + res.message);
                        }
                    });
            });
        });

        function markAll(status) {
            document.querySelectorAll(`.status-toggle input[value="${status}"]`).forEach(input => {
                input.checked = true;
            });
        }
    </script>
</body>

</html>