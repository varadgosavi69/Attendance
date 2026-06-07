<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JD College | Attendance Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Role tabs */
        .role-tabs {
            display: flex;
            background: rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 5px;
            margin-bottom: 26px;
            gap: 4px;
        }
        .role-tab {
            flex: 1;
            padding: 10px 0;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: rgba(255,255,255,0.55);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: all 0.25s ease;
        }
        .role-tab:hover { color: rgba(255,255,255,0.85); }
        .role-tab.active {
            color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.25);
        }
        .role-tab.faculty.active  { background: linear-gradient(135deg, #4361ee, #7209b7); }
        .role-tab.principal.active { background: linear-gradient(135deg, #7b2d8b, #c0392b); }

        /* Dynamic header subtitle */
        .login-header p { transition: opacity 0.2s ease; }

        /* Error msg */
        #errorMsg {
            display: none;
            color: #ffb3aa;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            background: rgba(239,35,60,0.18);
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 14px;
        }

        /* Button transition */
        .btn-login { transition: transform 0.2s, box-shadow 0.2s, background 0.35s ease; }
        .btn-faculty-style { background: linear-gradient(135deg, #4361ee, #7209b7); }
        .btn-principal-style { background: linear-gradient(135deg, #7b2d8b, #c0392b); }
    </style>
</head>

<body class="login-page">
    <div class="background-animate"></div>

    <div class="login-container">
        <div class="glass-card">

            <!-- Logo -->
            <div class="login-header">
                <div class="logo-icon">
                    <img src="assets/images/logo-new-150x150-1.png" alt="JD College Logo">
                </div>
                <h1 id="portalTitle">Welcome Back</h1>
                <p id="portalSubtitle">Sign in to access the attendance system</p>
            </div>

            <!-- Role Tabs -->
            <div class="role-tabs">
                <button class="role-tab faculty active" id="tabFaculty" onclick="switchTab('faculty')">
                    <i class="fa-solid fa-chalkboard-user"></i> Faculty / HOD
                </button>
                <button class="role-tab principal" id="tabPrincipal" onclick="switchTab('principal')">
                    <i class="fa-solid fa-shield-halved"></i> Principal
                </button>
            </div>

            <!-- Error -->
            <div id="errorMsg"></div>

            <!-- Login Form -->
            <form id="loginForm">
                <div class="input-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login btn-faculty-style" id="submitBtn">
                    <span id="btnText">Sign In</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        let currentRole = 'faculty';

        const config = {
            faculty: {
                title:    'Welcome Back',
                subtitle: 'Sign in to access the attendance system',
                btnText:  'Sign In',
                endpoint: 'api/login.php',
                btnClass: 'btn-faculty-style',
                placeholder: 'Enter your username',
            },
            principal: {
                title:    'Principal Login',
                subtitle: 'Restricted access — Authorised personnel only',
                btnText:  'Access Dashboard',
                endpoint: 'api/principal_api_login.php',
                btnClass: 'btn-principal-style',
                placeholder: 'Enter principal username',
            }
        };

        function switchTab(role) {
            currentRole = role;
            const c = config[role];

            // Tabs
            document.getElementById('tabFaculty').classList.toggle('active', role === 'faculty');
            document.getElementById('tabPrincipal').classList.toggle('active', role === 'principal');

            // Header
            document.getElementById('portalTitle').textContent    = c.title;
            document.getElementById('portalSubtitle').textContent = c.subtitle;

            // Button
            const btn = document.getElementById('submitBtn');
            btn.className = 'btn-login ' + c.btnClass;
            document.getElementById('btnText').textContent = c.btnText;

            // Placeholder
            document.getElementById('username').placeholder = c.placeholder;

            // Clear error & form
            document.getElementById('errorMsg').style.display = 'none';
            document.getElementById('loginForm').reset();
        }

        // Password toggle
        document.getElementById('togglePassword').addEventListener('click', function () {
            const pwd = document.getElementById('password');
            pwd.type = pwd.type === 'password' ? 'text' : 'password';
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form submit
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn    = document.getElementById('submitBtn');
            const errDiv = document.getElementById('errorMsg');
            const origHTML = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            errDiv.style.display = 'none';

            try {
                const res  = await fetch(config[currentRole].endpoint, {
                    method: 'POST',
                    body: new FormData(this)
                });
                const data = await res.json();

                if (data.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Redirecting...';
                    window.location.href = data.redirect;
                } else {
                    errDiv.textContent    = data.message;
                    errDiv.style.display  = 'block';
                    btn.disabled = false;
                    btn.innerHTML = origHTML;
                }
            } catch (err) {
                errDiv.textContent   = 'Connection error. Please try again.';
                errDiv.style.display = 'block';
                btn.disabled  = false;
                btn.innerHTML = origHTML;
            }
        });
    </script>
</body>
</html>