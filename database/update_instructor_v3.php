<?php
require_once __DIR__ . '/../api/db.php';

try {
    // 1. Update content_creators table for social links
    $pdo->exec("ALTER TABLE content_creators 
        ADD COLUMN IF NOT EXISTS linkedin_link VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS github_link VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS portfolio_link VARCHAR(255) DEFAULT NULL
    ");

    // 2. Update content_courses table
    $pdo->exec("ALTER TABLE content_courses 
        ADD COLUMN IF NOT EXISTS level ENUM('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Beginner',
        ADD COLUMN IF NOT EXISTS tags TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS is_resource TINYINT(1) DEFAULT 0
    ");

    // 3. Create progress_lessons table
    $pdo->exec("CREATE TABLE IF NOT EXISTS progress_lessons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        content_path VARCHAR(255),
        type ENUM('video', 'pdf', 'text') DEFAULT 'video',
        order_num INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (course_id),
        FOREIGN KEY (course_id) REFERENCES content_courses(id) ON DELETE CASCADE
    )");

    // 4. Create content_course_feedback table
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_course_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        student_id INT NOT NULL,
        message TEXT NOT NULL,
        reply TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (course_id),
        INDEX (student_id),
        FOREIGN KEY (course_id) REFERENCES content_courses(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )");

    // 5. Create notification_student table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_student (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT DEFAULT NULL, -- NULL means global notification
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (student_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )");

    echo "Migration v3 successful!";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>
