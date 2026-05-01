<?php
require_once dirname(__DIR__) . '/api/db.php';

echo "Running Migration for Instructor Module...\n";

try {
    // 1. Content Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `content` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `category` VARCHAR(100),
            `level` VARCHAR(50),
            `file_path` VARCHAR(255) NOT NULL,
            `uploaded_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            -- We avoid strict foreign key constraint for now to prevent breaking existing dummy data
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created `content` table.\n";

    // 2. Views Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `views` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `content_id` INT NOT NULL,
            `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created `views` table.\n";

    // 3. Downloads Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `downloads` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `content_id` INT NOT NULL,
            `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Created `downloads` table.\n";

    // Since users table doesn't formally exist as one unified table yet (auth uses students/content_creators),
    // the `uploaded_by` and `user_id` will loosely map to creator IDs and student IDs.

    echo "\nMigration Complete!\n";

} catch(PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
