<?php
// c:\xampp\htdocs\lastfyp\database\migrate_progress_lessons_content.php
require_once dirname(__DIR__) . '/api/db.php';

try {
    echo "Updating progress_lessons schema for dynamic content types...\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM `progress_lessons` LIKE 'type'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `progress_lessons` ADD COLUMN `type` ENUM('video', 'pdf', 'text') NOT NULL DEFAULT 'video' AFTER `title`");
    }

    $stmt2 = $pdo->query("SHOW COLUMNS FROM `progress_lessons` LIKE 'content'");
    if ($stmt2->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `progress_lessons` ADD COLUMN `content` TEXT NULL AFTER `type`");
    }

    // Make file_path nullable since text progress_lessons won't have files
    $pdo->exec("ALTER TABLE `progress_lessons` MODIFY `file_path` VARCHAR(255) NULL");

    echo "Lessons schema updated successfully!\n";
} catch(PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
