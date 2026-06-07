<?php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

$auth = new Auth();
$auth->requireRole(['teacher', 'admin']);
$user = $auth->getUser();

$db = Database::getInstance()->getConnection();

// Fetch initial students (limit 50 for performance)
$stmt = $db->query("SELECT * FROM students ORDER BY roll_number ASC LIMIT 50");
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | Teacher Portal</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .search-box input {
            width: 100%;
            padding: 8px 15px 8px 35px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
        }

        .card-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .upload-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: none;
        }

        .upload-card.active {
            display: block;
        }

        .file-input-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        input[type="file"] {
            padding: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px dashed rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            color: #2b2d42;
            flex-grow: 1;
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
            <a href="students.php" class="nav-item active">
                <i class="fa-solid fa-users"></i>
                <span>Students</span>
            </a>
            <a href="subjects.php" class="nav-item">
                <i class="fa-solid fa-book"></i>
                <span>Subjects</span>
            </a>
            <a href="logs.php" class="nav-item">
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
                <h1>Student Management</h1>
                <p>Add and manage your student database</p>
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

        <!-- CSV Bulk Upload Section -->
        <div id="uploadCard" class="upload-card">
            <h3>Bulk Upload via CSV</h3>
            <p style="font-size: 13px; color: #ccc; margin-bottom: 15px;">
                CSV Format: <code>Roll_No, Name, Email, Branch, Semester</code>
            </p>
            <form id="uploadForm" enctype="multipart/form-data" class="file-input-wrapper">
                <input type="file" name="student_csv" accept=".csv" required>
                <button type="submit" class="btn-primary">Process File</button>
            </form>
            <div id="uploadStatus" style="margin-top: 10px; font-weight: 500;"></div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Students List</h3>
                <div class="card-actions">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" placeholder="Search students..." id="searchInput">
                    </div>
                    <button id="downloadTemplateBtn" class="btn-primary"
                        style="background: rgba(255,255,255,0.1); color: var(--text-color); border: 1px solid #e0e0e0;">
                        <i class="fa-solid fa-file-csv"></i> Template
                    </button>
                    <button id="toggleUploadBtn" class="btn-primary">
                        <i class="fa-solid fa-upload"></i> Bulk Upload
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Branch</th>
                            <th>Semester</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="studentTableBody">
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><strong>
                                        <?php echo htmlspecialchars($student['roll_number']); ?>
                                    </strong></td>
                                <td>
                                    <div class="student-cell">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['student_name']); ?>&background=random"
                                            alt="">
                                        <span>
                                            <?php echo htmlspecialchars($student['student_name']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </td>
                                <td><span class="badge" style="background: rgba(255,255,255,0.1);">
                                        <?php echo htmlspecialchars($student['department']); ?>
                                    </span></td>
                                <td>Sem
                                    <?php echo htmlspecialchars($student['semester']); ?>
                                </td>
                                <td><span class="badge status-present">Active</span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 40px;">No students found. Use Bulk Upload
                                    to add students.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const toggleUploadBtn = document.getElementById('toggleUploadBtn');
        const uploadCard = document.getElementById('uploadCard');
        const uploadForm = document.getElementById('uploadForm');
        const uploadStatus = document.getElementById('uploadStatus');

        toggleUploadBtn.addEventListener('click', () => {
            uploadCard.classList.toggle('active');
        });

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(uploadForm);

            uploadStatus.textContent = 'Uploading...';
            uploadStatus.className = '';

            try {
                const response = await fetch('api/upload_students.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    uploadStatus.innerHTML = `<span style="color: #2a9d8f;">✅ Success: ${result.added} students processed.</span>`;
                    setTimeout(() => location.reload(), 1500);
                } else {
                    uploadStatus.innerHTML = `<span style="color: #ef233c;">❌ Error: ${result.message}</span>`;
                }
            } catch (error) {
                uploadStatus.innerHTML = `<span style="color: #ef233c;">❌ Connection Error</span>`;
            }
        });
    </script>
</body>

</html>