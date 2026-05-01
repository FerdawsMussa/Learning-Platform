<?php
require_once 'api/session_helper.php';
start_role_session();
require_once 'api/db.php';

function handleFailedLogin($pdo, $id, $attempts) {
    // We removed locked_until as per user feedback, but we can still track attempts
    $stmt = $pdo->prepare("UPDATE `users` SET login_attempts = login_attempts + 1 WHERE id = ?");
    $stmt->execute([$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        header("Location: login.php?error=emptyfields");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, password, role, is_approved, login_attempts, banned_until FROM `users` WHERE email = ?");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if account is banned
            if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
                $banned_date = date('M d, Y', strtotime($user['banned_until']));
                header("Location: login.php?error=banned&until=" . urlencode($banned_date));
                exit;
            }
            if (password_verify($password, $user['password'])) {
                if ($user['role'] === 'instructor' && $user['is_approved'] == 0) {
                    header("Location: login.php?error=notapproved");
                    exit;
                }

                // Reset attempts
                $pdo->prepare("UPDATE `users` SET login_attempts = 0 WHERE id = ?")->execute([$user['id']]);
                
                // Track last login
                $pdo->prepare("UPDATE `users` SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                
                // --- CRITICAL SESSION SWITCH ---
                // We restart the session with the correct role-based name
                session_unset();
                session_destroy();
                start_role_session($user['role']);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role']; 
                $_SESSION['last_activity'] = time();

                // Log the login event
                $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, description, user_role) VALUES ('login', ?, ?)");
                $logStmt->execute(["User ($identifier) logged into the platform.", $user['role']]);

                // Role-based redirection
                if ($user['role'] === 'admin') {
                    header("Location: dashboards/admin.php");
                } elseif ($user['role'] === 'instructor') {
                    header("Location: instructor_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            } else {
                handleFailedLogin($pdo, $user['id'], $user['login_attempts']);
                header("Location: login.php?error=invalidcredentials");
                exit;
            }
        } else {
            header("Location: login.php?error=invalidcredentials");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: login.php?error=sqlerror");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; padding: 20px; }
        .login-card { width: 100%; max-width: 450px; padding: 40px; text-align: center; z-index: 2; }
        .login-input { width: 100%; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); color: var(--text-primary); padding: 14px 16px; border-radius: var(--radius-sm); margin-bottom: 20px; align-items: center; outline: none; transition: border-color 0.2s; font-size: 0.95rem; }
        .login-input:focus { border-color: var(--accent-green); }
        .login-label { display: block; text-align: left; margin-bottom: 8px; color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .toast-notification { position: fixed; bottom: -100px; right: 20px; border-radius: var(--radius-sm); font-size: 0.95rem; z-index: 9999; transition: bottom 0.5s ease-in-out; box-shadow: 0 5px 15px rgba(0,0,0,0.3); backdrop-filter: blur(10px); padding: 15px 25px; }
        .toast-notification.show { bottom: 20px; }
        .toast-notification.error { background: rgba(255, 99, 132, 0.1); border: 1px solid rgba(255, 99, 132, 0.4); color: #ffb3c1; }
        .toast-notification.success { background: rgba(0, 229, 153, 0.1); border: 1px solid rgba(0, 229, 153, 0.4); color: var(--accent-green); }
    </style>
</head>
<body>
    <div class="bg-gradient-spot glow-green"></div>
    <div class="bg-gradient-spot glow-blue" style="top: auto; bottom: 0;"></div>

    <div class="login-container">
        <div class="glass-panel login-card animate-up">
            <h2 style="font-size: 2rem; margin-bottom: 10px;">Welcome Back</h2>
            <p style="color: var(--text-secondary); margin-bottom: 30px;">Sign in to continue learning</p>

            <form action="login.php" method="POST">
                <label class="login-label" for="identifier">Email Address</label>
                <input type="email" class="login-input" id="identifier" name="identifier" autocomplete="off" required>
                
                <label class="login-label" for="password">Password</label>
                <div style="position: relative;">
                    <input type="password" class="login-input" id="password" name="password" required>
                    <span onclick="togglePassword('password', this)" style="position: absolute; right: 12px; top: 12px; cursor: pointer; color: var(--text-secondary); opacity: 0.7;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </span>
                </div>

                <div style="text-align: right; margin: 0px 0 20px;">
                    <a href="forgot_password.php" style="color: var(--accent-green); text-decoration: none; font-size: 0.85rem; font-weight: 500;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-neon w-100" style="padding: 14px; font-size: 1rem;">Sign In</button>
            </form>
            
            <div style="margin-top: 25px; font-size: 0.9rem; color: var(--text-secondary);">
                Don't have an account? <a href="signup.php" style="color: var(--accent-green); text-decoration: none; font-weight: 600;">Sign Up</a>
            </div>
            <div style="margin-top: 20px; font-size: 0.85rem; color: var(--text-secondary);">
                <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">← Back to Home</a>
            </div>
        </div>
    </div>

    <div id="toast" class="toast-notification"></div>

    <script>
        function togglePassword(id, el) {
            const input = document.getElementById(id);
            const isPass = input.type === "password";
            input.type = isPass ? "text" : "password";
            el.innerHTML = isPass 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }

        const urlParams = new URLSearchParams(window.location.search);
        const errorType = urlParams.get('error');
        const successType = urlParams.get('success');
        const toast = document.getElementById('toast');

        if (errorType || successType) {
            let msg = "";
            if (errorType) {
                toast.classList.add('error');
                if (errorType === 'emptyfields') msg = "Please fill in all fields.";
                else if (errorType === 'invalidcredentials') msg = "Invalid login credentials.";
                else if (errorType === 'notapproved') msg = "Your creator account is pending approval.";
                else if (errorType === 'sqlerror') msg = "System error. Please try again later.";
                else if (errorType === 'locked') msg = "Account temporarily locked. Try again in 15 mins.";
                else if (errorType === 'banned') {
                    const until = urlParams.get('until') || "3 months";
                    msg = `Account deactivated/banned by admin until ${until}. Access restricted.`;
                }
                else msg = "System Error.";
            } else if (successType) {
                toast.classList.add('success');
                if (successType == 'registered') msg = "Registration successful! Please log in.";
                else if (successType == 'registered_pending') msg = "Application submitted! Pending admin approval.";
                else if (successType == 'recovered') msg = "Password explicitly reset!"; 
                else msg = "Success!";
            }

            toast.innerText = msg;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 5000);
            
            window.history.replaceState({}, document.title, "login.php");
        }
    </script>
</body>
</html>