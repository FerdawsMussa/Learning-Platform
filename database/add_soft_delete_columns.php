<?php
// c:\xampp\htdocs\lastfyp\database\add_soft_delete_columns.php
require_once dirname(__DIR__) . '/api/db.php';

echo "Updating database schema for Soft-Delete & logging...\n";

try {
    // 1. Update system_logs if columns are missing
    $q = $pdo->query("SHOW COLUMNS FROM `system_logs` LIKE 'user_id'");
    if (!$q->fetch()) {
        $pdo->exec("ALTER TABLE `system_logs` ADD COLUMN `user_id` INT DEFAULT NULL AFTER `id` ");
        echo "✔ Added 'user_id' to 'system_logs'.\n";
    }

    $q = $pdo->query("SHOW COLUMNS FROM `system_logs` LIKE 'target_id'");
    if (!$q->fetch()) {
        $pdo->exec("ALTER TABLE `system_logs` ADD COLUMN `target_id` INT DEFAULT NULL AFTER `user_id`, ADD COLUMN `target_type` VARCHAR(50) DEFAULT NULL AFTER `target_id` ");
        echo "✔ Added 'target_id' and 'target_type' to 'system_logs'.\n";
    }


    // 2. Add soft-delete columns to content_courses
    $q = $pdo->query("SHOW COLUMNS FROM `content_courses` LIKE 'is_deleted'");
    if (!$q->fetch()) {
        $pdo->exec("ALTER TABLE `content_courses` ADD COLUMN `is_deleted` TINYINT(1) DEFAULT 0, ADD COLUMN `deleted_at` DATETIME DEFAULT NULL");
        echo "✔ Added soft-delete columns to 'content_courses'.\n";
    }

    // 3. Add soft-delete columns to content_resources
    $q = $pdo->query("SHOW COLUMNS FROM `content_resources` LIKE 'is_deleted'");
    if (!$q->fetch()) {
        $pdo->exec("ALTER TABLE `content_resources` ADD COLUMN `is_deleted` TINYINT(1) DEFAULT 0, ADD COLUMN `deleted_at` DATETIME DEFAULT NULL");
        echo "✔ Added soft-delete columns to 'content_resources'.\n";
    }

    echo "✔ Schema update complete.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
