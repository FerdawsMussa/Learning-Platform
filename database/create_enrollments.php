<?php
// c:\xampp\htdocs\lastfyp\database\create_enrollments.php
require_once dirname(__DIR__) . '/api/db.php';

echo "Setting up Enrollments Table...\n";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `enrollments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `course_id` INT NOT NULL,
            `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`course_id`) REFERENCES `content_courses`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `unique_enrollment` (`student_id`, `course_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "✔ Enrollments table created successfully.\n";

    // Add some dummy enrollments if students and content_courses exist
    $students = $pdo->query("SELECT id FROM students LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    $content_courses = $pdo->query("SELECT id FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($students) && !empty($content_courses)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
        foreach ($students as $sid) {
            foreach ($content_courses as $cid) {
                if (rand(0, 1)) { // Randomly enroll
                    $stmt->execute([$sid, $cid]);
                }
            }
        }
        echo "✔ Added sample enrollments.\n";
    }

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
