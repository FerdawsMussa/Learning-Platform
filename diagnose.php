<?php
// TEMPORARY DIAGNOSTIC SCRIPT - DELETE AFTER USE
require_once 'api/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'includes/PHPMailer/src/Exception.php';
require 'includes/PHPMailer/src/PHPMailer.php';
require 'includes/PHPMailer/src/SMTP.php';

echo "<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:30px;} .ok{color:#00e599;} .fail{color:#ff6384;} .warn{color:#fbbf24;} h2{color:#94a3b8;border-bottom:1px solid #334155;padding-bottom:8px;}</style>";
echo "<h1 style='color:#00e599'>🔍 JU Learn - Full Diagnostic Report</h1>";

// ─── 1. DATABASE COLUMN CHECK ───────────────────────────────────────────────
echo "<h2>1. Database Schema Check</h2>";
$tables = ['students', 'content_creators'];
$required_cols = ['recovery_token', 'recovery_token_expires', 'password'];

foreach ($tables as $table) {
    echo "<b>Table: <span style='color:#93c5fd'>$table</span></b><ul>";
    try {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $cols = array_column($stmt->fetchAll(), 'Field');
        foreach ($required_cols as $col) {
            if (in_array($col, $cols)) {
                echo "<li class='ok'>✔ Column '$col' EXISTS</li>";
            } else {
                echo "<li class='fail'>✘ Column '$col' MISSING — run update_recovery_schema.php!</li>";
            }
        }
        // Also show full column list
        echo "<li class='warn'>All columns: " . implode(', ', $cols) . "</li>";
    } catch (Exception $e) {
        echo "<li class='fail'>✘ Table '$table' error: " . $e->getMessage() . "</li>";
    }
    echo "</ul>";
}

// ─── 2. SAMPLE ROW CHECK (does any student/creator exist?) ──────────────────
echo "<h2>2. User Records Check</h2>";
foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT id, full_name, email FROM `$table` LIMIT 3");
    $rows = $stmt->fetchAll();
    if (count($rows) > 0) {
        echo "<span class='ok'>✔ $table has " . count($rows) . " record(s). Sample email: <b>" . htmlspecialchars($rows[0]['email']) . "</b></span><br>";
    } else {
        echo "<span class='warn'>⚠ No records found in $table.</span><br>";
    }
}

// ─── 3. PHPMAILER SMTP TEST ──────────────────────────────────────────────────
echo "<h2>3. PHPMailer SMTP Authentication Test</h2>";
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'officialjulearn@gmail.com';
    $mail->Password = 'belw cnfi nrhw ycpk'; // App password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPDebug = 0; // Set to 2 for full SMTP transcript
    $mail->Timeout = 10;

    $mail->setFrom('officialjulearn@gmail.com', 'JU Learn System');
    $mail->addAddress('officialjulearn@gmail.com', 'Test'); // Send to self
    $mail->isHTML(true);
    $mail->Subject = 'JU Learn - SMTP Diagnostic Test';
    $mail->Body = 'This is a diagnostic test email. If you receive this, SMTP is working correctly!';
    $mail->AltBody = 'Diagnostic test email from JU Learn.';

    $mail->send();
    echo "<span class='ok'>✔ SMTP SUCCESS — Test email sent to officialjulearn@gmail.com! Check your inbox.</span>";
} catch (Exception $e) {
    echo "<span class='fail'>✘ SMTP FAILURE: <b>" . htmlspecialchars($e->getMessage()) . "</b></span>";
    echo "<br><br><span class='warn'>PHPMailer debug info:</span><pre style='color:#fbbf24'>" . htmlspecialchars($mail->ErrorInfo) . "</pre>";
}

// ─── 4. SSL/TLS CHECK ───────────────────────────────────────────────────────
echo "<h2>4. PHP OpenSSL Extension Check</h2>";
if (extension_loaded('openssl')) {
    echo "<span class='ok'>✔ OpenSSL is loaded — TLS connections are supported.</span><br>";
    echo "<span class='ok'>✔ OpenSSL Version: " . OPENSSL_VERSION_TEXT . "</span>";
} else {
    echo "<span class='fail'>✘ OpenSSL is NOT loaded! SMTP over TLS will fail. Enable extension=openssl in php.ini</span>";
}

// ─── 5. ALLOW_URL_FOPEN CHECK ───────────────────────────────────────────────
echo "<h2>5. PHP Configuration</h2>";
echo "<span class='ok'>✔ PHP Version: " . phpversion() . "</span><br>";
$sm = ini_get('sendmail_path');
echo "<span class='warn'>sendmail_path: " . ($sm ?: 'not set (expected for SMTP)') . "</span><br>";
?>