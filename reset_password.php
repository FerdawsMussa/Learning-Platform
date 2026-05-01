<?php
// c:\xampp\htdocs\lastfyp\reset_password.php
session_start();
require_once 'api/db.php';

$error = '';
$success = false;

$token = $_GET['token'] ?? '';
$role = $_GET['role'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($role) || empty($email)) {
    die("<div style='color: white; font-family: sans-serif; text-align: center; padding: 50px;'><h2>Invalid or Broken Link.</h2><p>Please request a new password reset from the login page.</p></div>");
}

// Table logic removed, all users in 'users'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'] ?? '';
    $conf_pass = $_POST['confirm_password'] ?? '';

    if (empty($new_pass) || $new_pass !== $conf_pass) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_pass) < 8 || !preg_match('/[^a-zA-Z\d]/', $new_pass)) {
        $error = "Password must be at least 8 characters and contain 1 special character.";
    } else {
        // Authenticate token dynamically
        try {
            $stmt = $pdo->prepare("SELECT id, recovery_token FROM `users` WHERE email = ? AND recovery_token_expires > NOW()");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($token, $user['recovery_token'])) {
                // Token matches securely and isn't expired! Approve password!
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                
                // Erase token fields permanently to enforce one-time-use constraint securely
                $upd = $pdo->prepare("UPDATE `users` SET password = ?, recovery_token = NULL, recovery_token_expires = NULL WHERE id = ?");
                $upd->execute([$hashed, $user['id']]);
                
                $success = true;
            } else {
                $error = "This recovery link has expired inherently after 1 Hour or is invalid.";
            }
        } catch (PDOException $e) {
            $error = "System Database Error.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn - Reset Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; padding: 20px; }
        .login-card { width: 100%; max-width: 480px; padding: 40px; text-align: center; z-index: 2; }
        .login-input { width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-light); color: var(--text-primary); padding: 14px 16px; border-radius: var(--radius-sm); margin-bottom: 20px; outline: none; transition: border-color 0.2s; font-size: 0.95rem; }
        .login-input:focus { border-color: var(--accent-green); box-shadow: 0 0 10px rgba(0, 229, 153, 0.2); }
        .login-label { display: block; text-align: left; margin-bottom: 8px; color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .toast-notification { position: fixed; bottom: -100px; right: 20px; border-radius: var(--radius-sm); font-size: 0.95rem; z-index: 9999; transition: bottom 0.5s ease-in-out; box-shadow: 0 5px 15px rgba(0,0,0,0.3); backdrop-filter: blur(10px); padding: 15px 25px; }
        .toast-notification.show { bottom: 20px; }
        .toast-notification.error { background: rgba(255, 99, 132, 0.1); border: 1px solid rgba(255, 99, 132, 0.4); color: #ffb3c1; }
        .form-error-msg { color: #ffb3c1; font-size: 0.8rem; text-align: left; margin-top: -15px; margin-bottom: 15px; display: none; }
    </style>
</head>
<body style="background: var(--bg-dark);">
    <div class="bg-gradient-spot glow-green float" style="animation-duration: 8s;"></div>
    <div class="bg-gradient-spot glow-blue float" style="bottom: 0; right: 0; top: auto; left: auto; animation-duration: 12s; animation-delay: 1s;"></div>

    <div class="login-container">
        <div class="glass-panel login-card animate-up">
            <h2 style="font-size: 1.8rem; margin-bottom: 15px;">Update Password</h2>
            
            <?php if($success): ?>
                <div style="background: rgba(0, 229, 153, 0.1); border: 1px solid rgba(0, 229, 153, 0.4); color: var(--accent-green); padding: 20px; border-radius: var(--radius-sm); margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0;">Success!</h4>
                    <p style="margin: 0; font-size: 0.9rem;">Your password has been securely reset.</p>
                </div>
                <a href="login.php?success=recovered" class="btn-neon w-100" style="padding: 14px; font-size: 1rem; text-decoration: none; display: inline-block;">Proceed to Log In</a>
            <?php else: ?>
                <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 0.9rem; line-height: 1.5;">Pick a new strong password for your account.</p>
                
                <form action="reset_password.php?token=<?= urlencode($token) ?>&role=<?= urlencode($role) ?>&email=<?= urlencode($email) ?>" method="POST" id="resetForm">
                    
                    <label class="login-label" for="new_password">New Password</label>
                    <div style="position: relative;">
                        <input type="password" class="login-input" id="new_password" name="new_password" required>
                        <span onclick="togglePassword('new_password', this)" style="position: absolute; right: 12px; top: 12px; cursor: pointer; color: var(--text-secondary); opacity: 0.7;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                    <div id="password_hint" style="text-align: left; margin-bottom: 20px; margin-top: -15px; display: none;">
                        <small style="color: #ffb3c1; font-size: 0.75rem;">Hint: Min 8 characters and at least one special character required.</small>
                    </div>
                    <div class="form-error-msg" id="password_err">Must be at least 8 chars & contain 1 special char.</div>

                    <label class="login-label" for="confirm_password">Confirm New Password</label>
                    <div style="position: relative;">
                        <input type="password" class="login-input" id="confirm_password" name="confirm_password" required>
                        <span onclick="togglePassword('confirm_password', this)" style="position: absolute; right: 12px; top: 12px; cursor: pointer; color: var(--text-secondary); opacity: 0.7;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </span>
                    </div>
                    <div class="form-error-msg" id="confirm_password_err">Passwords do not match.</div>

                    <button type="submit" class="btn-neon w-100" style="padding: 14px; font-size: 1rem; margin-top: 10px;">Enforce New Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast UI -->
    <div id="toast" class="toast-notification error">
        <?php if(!empty($error)) echo htmlspecialchars($error); ?>
    </div>

    <script>
        function togglePassword(id, el) {
            const input = document.getElementById(id);
            const isPass = input.type === "password";
            input.type = isPass ? "text" : "password";
            el.innerHTML = isPass 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }

        const pass = document.getElementById('new_password');
        const confPass = document.getElementById('confirm_password');
        const passErr = document.getElementById('password_err');
        const confErr = document.getElementById('confirm_password_err');
        const form = document.getElementById('resetForm');
        
        <?php if(!empty($error)): ?>
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 5000);
        <?php endif; ?>

        if(form) {
            const passHint = document.getElementById('password_hint');
            function checkPassword() {
                let pVal = pass.value;
                let regex = /[^a-zA-Z\d]/;
                const isWeak = pVal.length > 0 && (pVal.length < 8 || !regex.test(pVal));
                
                if (passHint) passHint.style.display = isWeak ? 'block' : 'none';
                
                if(isWeak) {
                    passErr.style.display = 'block';
                    return false;
                } else {
                    passErr.style.display = 'none';
                    return true;
                }
            }

            function checkConfirm() {
                if(confPass.value.length > 0 && confPass.value !== pass.value) {
                    confErr.style.display = 'block';
                    return false;
                } else {
                    confErr.style.display = 'none';
                    return true;
                }
            }

            pass.addEventListener('input', () => { checkPassword(); checkConfirm(); });
            confPass.addEventListener('input', checkConfirm);

            form.addEventListener('submit', (e) => {
                if(!checkPassword() || !checkConfirm() || pass.value !== confPass.value) {
                    e.preventDefault();
                }
            });
        }
    </script>
</body>
</html>
