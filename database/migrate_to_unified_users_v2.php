<?php
// c:\xampp\htdocs\lastfyp\database\migrate_to_unified_users_v2.php
require_once dirname(__DIR__) . '/api/db.php';

echo "Starting Unified Users Migration (V2)...\n";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Create `users` table
    $pdo->exec("DROP TABLE IF EXISTS `users` CASCADE;");
    $pdo->exec("
        CREATE TABLE `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('student', 'instructor', 'admin') NOT NULL,
            `content_courses` JSON NULL COMMENT 'Enrolled course IDs for students, Created course IDs for instructors',
            `content_resources` JSON NULL COMMENT 'Created resource IDs for instructors',
            `bio` TEXT NULL,
            `is_approved` TINYINT(1) DEFAULT 1,
            `login_attempts` INT DEFAULT 0,
            `recovery_token` VARCHAR(255) NULL,
            `last_login` DATETIME NULL,
            `meta` JSON NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $mappings = [
        'student' => [],
        'instructor' => [],
        'admin' => []
    ];

    // --- Migrate Admins ---
    echo "Migrating Admins...\n";
    $stmt = $pdo->query("SELECT * FROM `admins` ");
    while ($row = $stmt->fetch()) {
        $email = $row['username'] . '@admin.lms'; // Generate dummy email
        $insert = $pdo->prepare("INSERT INTO `users` (full_name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
        $insert->execute([$row['username'], $email, $row['password'], $row['created_at']]);
        $mappings['admin'][$row['id']] = $pdo->lastInsertId();
    }

    // --- Migrate Instructors (Content Creators) ---
    echo "Migrating Instructors...\n";
    $stmt = $pdo->query("SELECT * FROM `content_creators` ");
    while ($row = $stmt->fetch()) {
        // Collect content_courses created by this instructor
        $cStmt = $pdo->prepare("SELECT id FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE creator_id = ?");
        $cStmt->execute([$row['id']]);
        $courseIds = $cStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Collect content_resources created by this instructor
        $rStmt = $pdo->prepare("SELECT id FROM (SELECT id, user_id AS creator_id, title, file_path, generic_value AS file_type, category, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'resource') content_resources WHERE creator_id = ?");
        $rStmt->execute([$row['id']]);
        $resourceIds = $rStmt->fetchAll(PDO::FETCH_COLUMN);

        $insert = $pdo->prepare("INSERT INTO `users` (full_name, email, password, role, content_courses, content_resources, bio, is_approved, login_attempts, recovery_token, created_at) VALUES (?, ?, ?, 'instructor', ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $row['full_name'], $row['email'], $row['password'], 
            json_encode($courseIds), json_encode($resourceIds),
            $row['bio'] ?? null, $row['is_approved'] ?? 1, 
            $row['login_attempts'] ?? 0, $row['recovery_token'] ?? null, $row['created_at']
        ]);
        $mappings['instructor'][$row['id']] = $pdo->lastInsertId();
    }

    // --- Migrate Students ---
    echo "Migrating Students...\n";
    $stmt = $pdo->query("SELECT * FROM `students` ");
    while ($row = $stmt->fetch()) {
        // Collect content_courses enrolled by this student
        $eStmt = $pdo->prepare("SELECT course_id FROM enrollments WHERE student_id = ?");
        $eStmt->execute([$row['id']]);
        $enrolledIds = $eStmt->fetchAll(PDO::FETCH_COLUMN);

        $insert = $pdo->prepare("INSERT INTO `users` (full_name, email, password, role, content_courses, login_attempts, recovery_token, created_at) VALUES (?, ?, ?, 'student', ?, ?, ?, ?)");
        $insert->execute([
            $row['full_name'], $row['email'], $row['password'], 
            json_encode($enrolledIds),
            $row['login_attempts'] ?? 0, $row['recovery_token'] ?? null, $row['created_at']
        ]);
        $mappings['student'][$row['id']] = $pdo->lastInsertId();
    }

    // --- Update Foreign Keys in other tables ---
    echo "Updating Foreign Keys...\n";

    $foreignKeys = [
        ['table' => 'content_courses', 'col' => 'creator_id', 'map' => 'instructor'],
        ['table' => 'content_resources', 'col' => 'creator_id', 'map' => 'instructor'],
        ['table' => 'progress_lesson_progress', 'col' => 'student_id', 'map' => 'student'],
        ['table' => 'rewards', 'col' => 'student_id', 'map' => 'student'],
    ];

    foreach ($foreignKeys as $fk) {
        $table = $fk['table'];
        $col = $fk['col'];
        $mapType = $fk['map'];
        
        echo "Updating $table.$col...\n";
        
        // Push current IDs to a safe zone to avoid PK/Unique collisions during update
        $pdo->exec("UPDATE `$table` SET `$col` = `$col` + 1000000");

        foreach ($mappings[$mapType] as $oldId => $newId) {
            $pdo->prepare("UPDATE `$table` SET `$col` = ? WHERE `$col` = ?")->execute([$newId, $oldId + 1000000]);
        }
    }

    // --- Cleanup ---
    echo "Cleaning up old tables...\n";

    // Re-check and add constraints if needed (but since we are moving logic to JSON, some FKs might need to be dropped or modified)
    // For now, let's just restore FK checks and rename tables.

    try { $pdo->exec("DROP TABLE enrollments"); } catch(PDOException $e) { echo "Note: enrollments table already gone or error: ".$e->getMessage()."\n"; }
    
    // Rename old tables to backups
    $tablesToBackup = ['students', 'content_creators', 'admins'];
    foreach ($tablesToBackup as $table) {
        $backupName = $table . '_old_bak';
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$backupName` ");
            $pdo->exec("RENAME TABLE `$table` TO `$backupName` ");
        } catch(PDOException $e) {
            echo "Skipping backup of $table: " . $e->getMessage() . "\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "Migration Success!\n";

} catch (PDOException $e) {
    echo "Migration FAILED: " . $e->getMessage() . "\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}
?>
