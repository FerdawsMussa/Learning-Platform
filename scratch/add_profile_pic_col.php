<?php
require_once __DIR__ . '/../api/db.php';

try {
    // Add profile_pic column if missing
    $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('profile_pic', $cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `profile_pic` VARCHAR(255) NULL AFTER `bio`");
        echo "Added profile_pic column.\n";
    } else {
        echo "profile_pic column already exists.\n";
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
