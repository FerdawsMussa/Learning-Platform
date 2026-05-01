<?php
// c:\xampp\htdocs\lastfyp\forgot_password.php
session_start();
require_once 'api/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    try {
        $stmt = $pdo->prepare("SELECT id, full_name, role FROM `users` WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // 1. Generate Secure Random Token
            $token = bin2hex(random_bytes(32));

            // 2. Hash it to store safely in the database
            $hashed_token = password_hash($token, PASSWORD_DEFAULT);

            // Construct the recovery link dynamically based on the current host
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            
            // Get the directory path accurately
            $current_path = $_SERVER['PHP_SELF']; // e.g. /lastfyp/forgot_password.php
            $dir_path = dirname($current_path); // e.g. /lastfyp
            $dir_path = ($dir_path === '\\' || $dir_path === '/') ? '' : $dir_path;
            
            $link = "$protocol://$host$dir_path/reset_password.php?token=$token&role=" . urlencode($user['role']) . "&email=" . urlencode($email);

            // Diagnostic: Log the generated link to a file for troubleshooting
            if (!is_dir('scratch')) mkdir('scratch', 0777, true);
            file_put_contents('scratch/last_reset_link.txt', "[" . date('Y-m-d H:i:s') . "] Link: " . $link . PHP_EOL);

            // 3. Store in DB with exactly a 1 Hour expiry cutoff natively via SQL INTERVAL (MySQL)
            $upd = $pdo->prepare("UPDATE `users` SET recovery_token = ?, recovery_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
            $upd->execute([$hashed_token, $user['id']]);

            // 4. Dispatch Email securely using SMTP via PHPMailer
            $mail = new PHPMailer(true);
            try {
                // --- SMTP Configuration ---
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'julearn.office@gmail.com';
                // Project App Password
                $mail->Password   = 'jorq odmr ynfu tuly'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // --- SSL Bypass (For XAMPP Compatibility) ---
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    )
                );

                // --- Diagnostics ---
                $mail->SMTPDebug = 2; 
                $mail->Debugoutput = function($str, $level) {
                    if (!is_dir('scratch')) mkdir('scratch', 0777, true);
                    file_put_contents('scratch/smtp_debug.txt', "[" . date('Y-m-d H:i:s') . "] $str" . PHP_EOL, FILE_APPEND);
                };

                // --- Recipients ---
                $mail->setFrom('julearn.office@gmail.com', 'JU Learn System');
                $mail->addAddress($email, $user['full_name']);

                // --- Content ---
                $mail->isHTML(true);
                $mail->Subject = 'Password Recovery - JU Learn';
                $mail->Body    = "Hello {$user['full_name']},<br><br>We received a request to reset your password. Click the secure link below to do so:<br><br><b><a href='$link'>Reset Your Password Here</a></b><br><br>If you didn't request this action, you can safely ignore this email. This link will expire in 1 Hour.<br><br>- The JU Learn Automated System";
                $mail->AltBody = "Reset your password securely here: $link (This link expires in 1 Hour)";

                $mail->send();
                header("Location: forgot_password.php?success=sent");
                exit;
            } catch (Exception $e) {
                // Log final error to file
                if (!is_dir('scratch')) mkdir('scratch', 0777, true);
                file_put_contents('scratch/smtp_debug.txt', "[" . date('Y-m-d H:i:s') . "] FINAL ERROR: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
                header("Location: forgot_password.php?error=mailfail&debug=" . urlencode($e->getMessage()));
                exit;
            }
        } else {
            // Explicit red error shown on interface if it genuinely doesn't exist
            header("Location: forgot_password.php?error=notfound");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: forgot_password.php?error=sqlerror&debug=" . urlencode($e->getMessage()));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JU Learn - Recovery</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 40px;
            text-align: center;
            z-index: 2;
        }

        .login-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            outline: none;
            transition: border-color 0.2s;
            font-size: 0.95rem;
        }

        .login-input:focus {
            border-color: var(--accent-green);
            box-shadow: 0 0 10px rgba(0, 229, 153, 0.2);
        }

        .login-label {
            display: block;
            text-align: left;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .toast-notification {
            position: fixed;
            bottom: -100px;
            right: 20px;
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            z-index: 9999;
            transition: bottom 0.5s ease-in-out;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
        }

        .toast-notification.show {
            bottom: 20px;
        }

        .toast-notification.error {
            background: rgba(255, 99, 132, 0.1);
            border: 1px solid rgba(255, 99, 132, 0.4);
            color: #ffb3c1;
        }

        .toast-notification.success {
            background: rgba(0, 229, 153, 0.1);
            border: 1px solid rgba(0, 229, 153, 0.4);
            color: var(--accent-green);
        }
    </style>
</head>

<body>
    <div class="bg-gradient-spot glow-green float" style="animation-duration: 8s;"></div>
    <div class="bg-gradient-spot glow-blue float"
        style="bottom: 0; left: 0; top: auto; right: auto; animation-duration: 12s; animation-delay: 1s;"></div>

    <div class="login-container">
        <div class="glass-panel login-card animate-up">
            <h2 style="font-size: 1.8rem; margin-bottom: 15px;">Reset Password</h2>
            <p style="color: var(--text-secondary); margin-bottom: 30px; font-size: 0.9rem; line-height: 1.5;">Enter the
                email associated with your account and we'll send you a recovery link.</p>

            <form action="forgot_password.php" method="POST">
                <label class="login-label" for="email">Account Email</label>
                <input type="email" class="login-input" id="email" name="email" required autocomplete="off">


                <button type="submit" class="btn-neon w-100"
                    style="padding: 14px; font-size: 1rem; margin-top: 10px;">Send Recovery Link</button>
            </form>

            <div style="margin-top: 25px; font-size: 0.85rem;">
                <a href="login.php" style="color: var(--text-secondary); text-decoration: none;">← Back to Login</a>
            </div>
        </div>
    </div>

    <!-- Toast UI -->
    <div id="toast" class="toast-notification"></div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const errorType = urlParams.get('error');
        const successType = urlParams.get('success');
        const debugMsg = urlParams.get('debug');
        const toast = document.getElementById('toast');

        if (errorType || successType) {
            let msg = "";
            if (errorType) {
                toast.classList.add('error');
                if (errorType === 'empty') msg = "Please provide an email address.";
                else if (errorType === 'notfound') msg = "That email is not registered in our database.";
                else if (errorType === 'mailfail' && debugMsg) msg = "Mail Error: " + debugMsg;
                else if (errorType === 'sqlerror' && debugMsg) msg = "System error or Mail failed." + debugMsg;
                else msg = "System Error.";
            } else if (successType) {
                toast.classList.add('success');
                if (successType == 'sent') msg = "A recovery link has been dispatched to your email!";
                else msg = "Success!";
            }

            toast.innerText = msg;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 15000);

            window.history.replaceState({}, document.title, "forgot_password.php");
        }
    </script>
</body>

</html>