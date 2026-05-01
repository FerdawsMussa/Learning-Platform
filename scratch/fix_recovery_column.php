<?php
require_once '../api/db.php';

try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `recovery_token_expires` DATETIME NULL AFTER `recovery_token` ");
    echo "Successfully added 'recovery_token_expires' to 'users' table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
