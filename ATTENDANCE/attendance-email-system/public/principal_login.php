<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Portal | JD College</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .principal-login {
            background: linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)),
                url('assets/images/login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
        }
        .principal-login .btn-login {
            background: linear-gradient(135deg, #7b2d8b, #c0392b);
        }
        .principal-login .btn-login:hover {
            box-shadow: 0 6px 20px rgba(192, 57, 43, 0.4);
        }
        .portal-badge {
            display: inline-block;
            background: linear-gradient(135deg, #7b2d8b, #c0392b);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 20px;
            margin-bottom: 14px;
        }
    </style>
</head>
<body class="login-page principal-login">
    <div class="background-animate"></div>
    <div class="login-container">
        <div class="glass-card">
            <div class="login-header">
                <div class="logo-icon">
                    <img src="assets/images/logo-new-150x150-1.png" alt="JD College Logo">
                </div>
                <div class="portal-badge"><i class="fa-solid fa-shield-halved"></i> &nbsp;Principal Portal</div>
                <h1>Principal Login</h1>
                <p>Restricted access — Principal use only</p>
            </div>

            <form id="principalLoginForm" action="api/principal_api_login.php" method="POST">
                <div class="input-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Principal username" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Your password" required>
                        <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <div id="errorMsg" style="display:none; color:#ef233c; font-size:13px; margin-bottom:15px; text-align:center;"></div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <span>Access Dashboard</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="login-footer">
                <p>Faculty login? <a href="index.php">Faculty Portal →</a></p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const pwd = document.getElementById('password');
            pwd.type = pwd.type === 'password' ? 'text' : 'password';
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // AJAX form submit
        document.getElementById('principalLoginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const errDiv = document.getElementById('errorMsg');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            errDiv.style.display = 'none';

            try {
                const res = await fetch('api/principal_api_login.php', {
                    method: 'POST',
                    body: new FormData(this)
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errDiv.textContent = data.message;
                    errDiv.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = '<span>Access Dashboard</span><i class="fa-solid fa-arrow-right"></i>';
                }
            } catch(err) {
                errDiv.textContent = 'Connection error. Please try again.';
                errDiv.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span>Access Dashboard</span><i class="fa-solid fa-arrow-right"></i>';
            }
        });
    </script>
</body>
</html>
