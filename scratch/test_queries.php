<?php
require 'api/db.php';
try {
    $stmt = $pdo->query("
        SELECT u.*, 
        (SELECT COUNT(*) FROM (SELECT user_id AS student_id, item_id AS lesson_id, metric_value AS status, last_activity_at AS last_sync FROM progress WHERE record_type = 'lesson_progress') lp WHERE lp.student_id = u.id AND lp.status = 'completed') as completed_progress_lessons
        FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC
    ");
    echo "Students: OK\n";
} catch (Exception $e) {
    echo "Students ERROR: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query("
        SELECT u.* FROM users u WHERE u.role = 'instructor' ORDER BY u.created_at DESC
    ");
    echo "Instructors: OK\n";
} catch (Exception $e) {
    echo "Instructors ERROR: " . $e->getMessage() . "\n";
}

try {
    $users_stmt = $pdo->query("SELECT id, role, full_name, email, created_at FROM users 
                               WHERE role != 'admin'
                               ORDER BY created_at DESC LIMIT 20");
    echo "System Users: OK\n";
} catch (Exception $e) {
    echo "System Users ERROR: " . $e->getMessage() . "\n";
}
