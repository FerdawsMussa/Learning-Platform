<?php
// c:\xampp\htdocs\lastfyp\scratch\migrate_progress.php
require_once __DIR__ . '/../api/db.php';

try {
    echo "Creating unified 'progress' table...\n";
    $pdo->exec("DROP TABLE IF EXISTS `progress`");
    
    $pdo->exec("CREATE TABLE `progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `record_type` ENUM('lesson', 'lesson_progress', 'certificate', 'streak') NOT NULL,
        `user_id` INT NULL,
        `course_id` INT NULL,
        `item_id` INT NULL COMMENT 'For lesson_progress this is the lesson id',
        `title` VARCHAR(255) NULL,
        `content_type` VARCHAR(50) NULL,
        `content_text` TEXT NULL,
        `file_path` VARCHAR(255) NULL,
        `file_size` INT NULL,
        `metric_value` VARCHAR(255) NULL COMMENT 'Used for streak_count, status, cert_id',
        `order_number` INT NULL,
        `last_activity_at` DATETIME NULL COMMENT 'Used for last_sync, last_login',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    )");

    echo "Migrating lessons (preserving IDs)...\n";
    if ($pdo->query("SHOW TABLES LIKE 'progress_lessons'")->rowCount() > 0) {
        $cols = $pdo->query("DESCRIBE progress_lessons")->fetchAll(PDO::FETCH_COLUMN);
        $has_module = in_array('module_id', $cols);
        $module_col = $has_module ? 'module_id' : 'NULL';

        $pdo->exec("INSERT INTO progress (id, record_type, course_id, item_id, title, content_type, content_text, file_path, file_size, order_number, created_at) 
                    SELECT id, 'lesson', course_id, $module_col, title, type, content, file_path, file_size, order_number, created_at FROM progress_lessons");
    }

    echo "Migrating lesson_progress...\n";
    if ($pdo->query("SHOW TABLES LIKE 'progress_lesson_progress'")->rowCount() > 0) {
        $pdo->exec("INSERT INTO progress (record_type, user_id, item_id, metric_value, last_activity_at) 
                    SELECT 'lesson_progress', student_id, lesson_id, status, last_sync 
                    FROM progress_lesson_progress
                    WHERE student_id IN (SELECT id FROM users)");
    }

    echo "Migrating certificates...\n";
    if ($pdo->query("SHOW TABLES LIKE 'progress_certificates'")->rowCount() > 0) {
        $pdo->exec("INSERT INTO progress (record_type, user_id, course_id, metric_value, created_at) 
                    SELECT 'certificate', student_id, course_id, cert_id, issued_at 
                    FROM progress_certificates
                    WHERE student_id IN (SELECT id FROM users)");
    }

    echo "Migrating streaks...\n";
    if ($pdo->query("SHOW TABLES LIKE 'progress_streaks'")->rowCount() > 0) {
        $pdo->exec("INSERT INTO progress (record_type, user_id, metric_value, last_activity_at) 
                    SELECT 'streak', user_id, streak_count, last_login_date 
                    FROM progress_streaks
                    WHERE user_id IN (SELECT id FROM users)");
    }

    echo "Data migration complete.\n";
} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
