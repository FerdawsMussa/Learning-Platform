<?php
// Force-add recovery_token_expires to both tables, one at a time with per-table error handling
require_once 'api/db.php';

$tables = ['students', 'content_creators'];

foreach ($tables as $table) {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'recovery_token_expires'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✔ $table: recovery_token_expires already exists.<br>";
    } else {
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `recovery_token_expires` DATETIME NULL");
            echo "✔ $table: recovery_token_expires ADDED successfully!<br>";
        } catch (PDOException $e) {
            echo "✘ $table: Error - " . $e->getMessage() . "<br>";
        }
    }
}

// Verify
echo "<br><b>Verification:</b><br>";
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'recovery_token_expires'");
    $exists = $stmt->fetch();
    echo "$table → recovery_token_expires: " . ($exists ? "✔ CONFIRMED" : "✘ STILL MISSING") . "<br>";
}
?>
