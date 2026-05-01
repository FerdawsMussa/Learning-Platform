<?php
require_once __DIR__ . '/../api/db.php';

try {
    $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('last_name', $cols)) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `last_name` VARCHAR(100) NULL AFTER `full_name`");
        echo "Added last_name column.\n";
    } else {
        echo "last_name column already exists.\n";
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
