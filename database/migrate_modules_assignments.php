<?php
require_once dirname(__DIR__) . '/api/db.php';

echo "Running Migration for Modules and Assignments...\n";

try {
    // 1. Modules Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `modules` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `order_number` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`course_id`) REFERENCES `content_courses`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created `modules` table.\n";

    // 2. Alter Lessons Table to Add Module ID
    // We add it conditionally to avoid errors if it already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `progress_lessons` LIKE 'module_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `progress_lessons` ADD `module_id` INT NULL AFTER `course_id`");
        $pdo->exec("ALTER TABLE `progress_lessons` ADD FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE SET NULL");
        echo "Added 'module_id' column to `progress_lessons` table.\n";
    } else {
        echo "'module_id' column already exists in `progress_lessons` table.\n";
    }

    // 3. Assignments Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assignments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT NOT NULL,
            `module_id` INT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `due_date` DATETIME,
            `file_path` VARCHAR(255),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`course_id`) REFERENCES `content_courses`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created `assignments` table.\n";

    echo "\nMigration Complete!\n";

} catch(PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
