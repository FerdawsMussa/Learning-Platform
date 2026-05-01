<?php
// c:\xampp\htdocs\lastfyp\scratch\refactor_progress_code.php
$replacements = [
    // --- STREAKS ---
    "INSERT INTO progress_streaks (user_id)" => "INSERT INTO progress (record_type, user_id, metric_value) VALUES ('streak', ?, 0)",
    "INSERT INTO progress_streaks (user_id) VALUES (?)" => "INSERT INTO progress (record_type, user_id, metric_value) VALUES ('streak', ?, 0)",
    "INSERT INTO progress_streaks (user_id, streak_count, last_login_date)" => "INSERT INTO progress (record_type, user_id, metric_value, last_activity_at)",
    "UPDATE progress_streaks SET streak_count = ?" => "UPDATE progress SET metric_value = ? WHERE record_type = 'streak' AND user_id",
    "UPDATE progress_streaks SET last_login_date = ?" => "UPDATE progress SET last_activity_at = ? WHERE record_type = 'streak' AND user_id",
    "UPDATE progress_streaks SET streak_count = streak_count + 1" => "UPDATE progress SET metric_value = metric_value + 1",
    "FROM progress_streaks WHERE user_id =" => "FROM progress WHERE record_type = 'streak' AND user_id =",
    "SELECT streak_count FROM progress_streaks" => "SELECT metric_value AS streak_count FROM progress WHERE record_type = 'streak'",
    "SELECT * FROM progress_streaks" => "SELECT user_id, metric_value AS streak_count, last_activity_at AS last_login_date FROM progress WHERE record_type = 'streak'",
    "JOIN progress_streaks" => "JOIN progress ON (progress.user_id = users.id AND progress.record_type = 'streak')", // Check if this alias works
    "progress_streaks s" => "(SELECT user_id, metric_value AS streak_count, last_activity_at AS last_login_date FROM progress WHERE record_type = 'streak') s",
    "FROM progress_streaks" => "FROM (SELECT user_id, metric_value AS streak_count, last_activity_at AS last_login_date FROM progress WHERE record_type = 'streak') progress_streaks", // Fallback for simple selects

    // --- CERTIFICATES ---
    "INSERT INTO progress_certificates (student_id, course_id, cert_id)" => "INSERT INTO progress (record_type, user_id, course_id, metric_value)",
    "INSERT INTO progress_certificates (student_id, course_id, cert_id, issued_at)" => "INSERT INTO progress (record_type, user_id, course_id, metric_value, created_at)",
    "FROM progress_certificates WHERE cert_id" => "FROM progress WHERE record_type = 'certificate' AND metric_value",
    "FROM progress_certificates c" => "FROM (SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate') c",
    "FROM progress_certificates" => "FROM (SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate') progress_certificates",

    // --- LESSON PROGRESS ---
    "INSERT INTO progress_lesson_progress (student_id, lesson_id, status)" => "INSERT INTO progress (record_type, user_id, item_id, metric_value)",
    "UPDATE progress_lesson_progress SET status" => "UPDATE progress SET metric_value",
    "FROM progress_lesson_progress WHERE student_id" => "FROM progress WHERE record_type = 'lesson_progress' AND user_id",
    "FROM progress_lesson_progress lp" => "FROM (SELECT user_id AS student_id, item_id AS lesson_id, metric_value AS status, last_activity_at AS last_sync FROM progress WHERE record_type = 'lesson_progress') lp",
    "FROM progress_lesson_progress" => "FROM (SELECT user_id AS student_id, item_id AS lesson_id, metric_value AS status, last_activity_at AS last_sync FROM progress WHERE record_type = 'lesson_progress') progress_lesson_progress",

    // --- LESSONS ---
    "INSERT INTO progress_lessons (course_id, module_id, title, type, content, file_path, file_size, order_number)" => "INSERT INTO progress (record_type, course_id, item_id, title, content_type, content_text, file_path, file_size, order_number)",
    "INSERT INTO progress_lessons (course_id, title, content, file_path, type, order_number)" => "INSERT INTO progress (record_type, course_id, title, content_text, file_path, content_type, order_number)",
    "INSERT INTO progress_lessons (course_id, title, file_path, content, type, order_number)" => "INSERT INTO progress (record_type, course_id, title, file_path, content_text, content_type, order_number)",
    "UPDATE progress_lessons SET" => "UPDATE progress SET",
    "DELETE FROM progress_lessons" => "DELETE FROM progress", // Need to be careful here
    "FROM progress_lessons l" => "FROM (SELECT id, course_id, item_id AS module_id, title, content_type AS type, content_text AS content, file_path, file_size, order_number, created_at FROM progress WHERE record_type = 'lesson') l",
    "FROM progress_lessons" => "FROM (SELECT id, course_id, item_id AS module_id, title, content_type AS type, content_text AS content, file_path, file_size, order_number, created_at FROM progress WHERE record_type = 'lesson') progress_lessons",
];

// Special Manual Fixes:
// 1. In view_certificate.php: SELECT * FROM progress_certificates WHERE cert_id = ? -> SELECT * FROM progress WHERE record_type = 'certificate' AND metric_value = ?
// 2. In api/upload_lesson.php: INSERT target column mismatch requires manual fix
// 3. In student_viewer.php: complex joining logic requires manual fix

$files_to_fix = [
    'api/certificate_engine.php',
    'api/instructor_v2_actions.php',
    'api/upload_lesson.php',
    'dashboards/admin.php',
    'dashboards/student.php',
    'includes/dash_student.php',
    'student_viewer.php',
    'view_certificate.php',
    'signup.php',
    'dashboard.php'
];

foreach ($files_to_fix as $filename) {
    $path = __DIR__ . '/../' . $filename;
    if (!file_exists($path)) continue;

    $content = file_get_contents($path);
    $original = $content;

    // Apply generic replacements first
    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    
    // VERY SPECIFIC MANUAL FIXES

    // signup.php
    if ($filename === 'signup.php') {
        $content = str_replace(
            "\$stmt = \$pdo->prepare(\"INSERT INTO progress (record_type, user_id, metric_value) VALUES ('streak', ?, 0)\");\n        \$stmt->execute([\$user_id]);", // Fix double placeholder issue if happens
            "\$stmt = \$pdo->prepare(\"INSERT INTO progress (record_type, user_id, metric_value) VALUES ('streak', ?, 0)\");\n        \$stmt->execute([\$user_id]);",
            $content
        );
    }

    // student_viewer.php
    if ($filename === 'student_viewer.php') {
        // Fix lesson completion insert
        $content = str_replace(
            "INSERT INTO progress (record_type, user_id, item_id, metric_value) VALUES (?, ?, 'completed') ON DUPLICATE KEY UPDATE status = 'completed'",
            "INSERT INTO progress (record_type, user_id, item_id, metric_value) VALUES ('lesson_progress', ?, ?, 'completed') ON DUPLICATE KEY UPDATE metric_value = 'completed'",
            $content
        );
        // Fix the fetching of lessons
        // The regex replaces the subselect wrapper to make sure it doesn't break simple queries
        $content = str_replace("FROM (SELECT id, course_id, item_id AS module_id, title, content_type AS type, content_text AS content, file_path, file_size, order_number, created_at FROM progress WHERE record_type = 'lesson') progress_lessons WHERE course_id = ?",
                               "FROM progress WHERE record_type = 'lesson' AND course_id = ?",
                               $content);
        $content = preg_replace("/SELECT \* FROM \(SELECT.*?\) progress_lessons WHERE course_id/", "SELECT id, course_id, item_id AS module_id, title, content_type AS type, content_text AS content, file_path, file_size, order_number, created_at FROM progress WHERE record_type = 'lesson' AND course_id", $content);
    }

    // dashboard.php (streak logic)
    if ($filename === 'dashboard.php') {
        $content = str_replace(
            "\$stmt = \$pdo->prepare(\"UPDATE progress SET metric_value = ? WHERE record_type = 'streak' AND user_id = ?\");",
            "\$stmt = \$pdo->prepare(\"UPDATE progress SET metric_value = ? WHERE record_type = 'streak' AND user_id = ?\");",
            $content
        );
        $content = str_replace(
            "UPDATE progress SET last_activity_at = ? WHERE record_type = 'streak' AND user_id = ?",
            "UPDATE progress SET last_activity_at = ? WHERE record_type = 'streak' AND user_id = ?",
            $content
        );
    }
    
    // admin.php
    if ($filename === 'dashboards/admin.php') {
        $content = str_replace(
            "FROM users u \n        JOIN (SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate') c ON u.id = c.student_id \n        JOIN content_courses co ON c.course_id = co.id \n        ORDER BY c.issued_at DESC",
            "FROM users u JOIN progress c ON (u.id = c.user_id AND c.record_type = 'certificate') JOIN content_courses co ON c.course_id = co.id ORDER BY c.created_at DESC",
            $content
        );
        
        $content = str_replace(
            "SELECT c.*, u.full_name, co.title as course_title",
            "SELECT c.metric_value AS cert_id, c.created_at AS issued_at, u.full_name, co.title as course_title",
            $content
        );
    }
    
    // view_certificate.php
    if ($filename === 'view_certificate.php') {
        $content = str_replace(
            "SELECT * FROM (SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate') progress_certificates WHERE cert_id = ?",
            "SELECT id, user_id AS student_id, course_id, metric_value AS cert_id, created_at AS issued_at FROM progress WHERE record_type = 'certificate' AND metric_value = ?",
            $content
        );
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Refactored: $filename\n";
    }
}
echo "Targeted code refactoring finished.\n";
?>
