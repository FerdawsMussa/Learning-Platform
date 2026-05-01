<?php
// c:\xampp\htdocs\lastfyp\db_revert.php
require_once 'api/db.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Drop constraints pointing to `users`
    $tables = ['content_courses' => 'creator_id', 'content_resources' => 'creator_id', 'progress_lesson_progress' => 'student_id', 'rewards' => 'student_id'];
    
    foreach ($tables as $table => $column) {
        $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = 'offline_lms_db' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' AND REFERENCED_TABLE_NAME = 'users'");
        $fks = $stmt->fetchAll();
        foreach($fks as $fk) {
            $fk_name = $fk['CONSTRAINT_NAME'];
            try { $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$fk_name`"); } catch(Exception $e) {}
        }
    }

    // 2. Re-point constraints mapping directly to root tables
    $pdo->exec("ALTER TABLE `content_courses` ADD CONSTRAINT `fk_content_courses_creators_new` FOREIGN KEY (`creator_id`) REFERENCES `content_creators`(`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `content_resources` ADD CONSTRAINT `fk_content_resources_creators_new` FOREIGN KEY (`creator_id`) REFERENCES `content_creators`(`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `progress_lesson_progress` ADD CONSTRAINT `fk_lp_students_new` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE `rewards` ADD CONSTRAINT `fk_rewards_students_new` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE");

    // 3. Drop `users`
    $pdo->exec("DROP TABLE IF EXISTS `users`");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Database explicitly split successfully. Users discarded.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
