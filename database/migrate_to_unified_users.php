<?php
// c:\xampp\htdocs\lastfyp\database\migrate_to_unified_users.php
require_once dirname(__DIR__) . '/api/db.php';

echo "Starting Unified Users Migration...\n";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Create `users` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('student', 'instructor', 'admin') NOT NULL DEFAULT 'student',
            `bio` TEXT NULL,
            `current_field` VARCHAR(100) NULL,
            `is_approved` TINYINT(1) DEFAULT 1,
            `login_attempts` INT DEFAULT 0,
            `locked_until` DATETIME NULL,
            `recovery_token` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Clear old data from `users` if migrating again
    $pdo->exec("TRUNCATE TABLE `users`");

    $mappings = [
        'student' => [],
        'instructor' => [],
        'admin' => []
    ];

    // Migrate Students
    $stmt = $pdo->query("SELECT * FROM `students`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $insert = $pdo->prepare("INSERT INTO `users` (full_name, email, password, role, current_field, login_attempts, locked_until, recovery_token, created_at) VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?)");
        $insert->execute([
            $row['full_name'], $row['email'], $row['password'], $row['current_field'] ?? null, 
            $row['login_attempts'] ?? 0, $row['locked_until'] ?? null, $row['recovery_token'] ?? null, $row['created_at']
        ]);
        $mappings['student'][$row['id']] = $pdo->lastInsertId();
    }

    // Migrate Content Creators -> Instructors
    $stmt = $pdo->query("SELECT * FROM `content_creators`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $email = !empty($row['email']) ? $row['email'] : 'instructor'.$row['id'].'@lms.com';
        
        // Ensure uniqueness
        $check = $pdo->prepare("SELECT id FROM `users` WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $email = str_replace('@', '+instructor@', $email);
        }

        $insert = $pdo->prepare("INSERT INTO `users` (full_name, email, password, role, bio, is_approved, login_attempts, locked_until, recovery_token, created_at) VALUES (?, ?, ?, 'instructor', ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $row['full_name'], $email, $row['password'], $row['bio'] ?? null, $row['is_approved'] ?? 1, 
            $row['login_attempts'] ?? 0, $row['locked_until'] ?? null, $row['recovery_token'] ?? null, $row['created_at']
        ]);
        $mappings['instructor'][$row['id']] = $pdo->lastInsertId();
    }

    // Migrate Admins
    $stmt = $pdo->query("SELECT * FROM `admins`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $email = 'admin_'.$row['id'].'@lms.com'; // Admin table usually only had username
        $insert = $pdo->prepare("INSERT INTO `users` (full_name, email, password, role, login_attempts, locked_until) VALUES (?, ?, ?, 'admin', ?, ?)");
        $insert->execute([
            $row['username'], $email, $row['password'], $row['login_attempts'] ?? 0, $row['locked_until'] ?? null
        ]);
        $mappings['admin'][$row['id']] = $pdo->lastInsertId();
    }

    echo "Data migrated to `users` table.\n";

    // 3. Drop existing foreign keys and remap
    $remappingQueries = [
        ['table' => 'content_courses', 'column' => 'creator_id', 'fk_name' => 'content_courses_ibfk_1', 'map' => 'instructor'],
        ['table' => 'content_resources', 'column' => 'creator_id', 'fk_name' => 'content_resources_ibfk_1', 'map' => 'instructor'],
        ['table' => 'progress_lesson_progress', 'column' => 'student_id', 'fk_name' => 'progress_lesson_progress_ibfk_1', 'map' => 'student'],
        ['table' => 'rewards', 'column' => 'student_id', 'fk_name' => 'rewards_ibfk_1', 'map' => 'student']
    ];

    foreach ($remappingQueries as $update) {
        $table = $update['table'];
        $col = $update['column'];
        $fk = $update['fk_name'];
        $mapType = $update['map'];

        // Drop FK
        try {
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$fk`");
        } catch(PDOException $e) { /* Ignore if doesn't exist */ }

        // Update values
        foreach ($mappings[$mapType] as $old_id => $new_id) {
            $pdo->exec("UPDATE `$table` SET `$col` = $new_id WHERE `$col` = $old_id");
        }

        // Re-add FK mapped to `users`
        $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `{$fk}_new` FOREIGN KEY (`$col`) REFERENCES `users`(`id`) ON DELETE CASCADE");
    }

    // Optional Tables Mapping removed because they don't exist in LMS Schema V1.0

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "Foreign Keys remapped successfully.\n";
    echo "Migration to unified `users` table complete!\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
