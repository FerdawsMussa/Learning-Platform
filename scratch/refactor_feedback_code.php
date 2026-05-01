<?php
// c:\xampp\htdocs\lastfyp\scratch\refactor_feedback_code.php

$replacements = [
    // The giant subquery used across the codebase for fetching feedbacks:
    "SELECT id, parent_id AS course_id, user_id AS student_id, generic_value AS rating, description AS comment, meta_text AS reply, created_at FROM content WHERE record_type = 'feedback'" => "SELECT id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) AS course_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.student_id')) AS student_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) AS rating, message AS comment, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reply')) AS reply, created_at FROM notifications WHERE type = 'feedback'",

    // student_viewer.php Check Feedback logic
    "SELECT id FROM (SELECT id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) AS course_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.student_id')) AS student_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) AS rating, message AS comment, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reply')) AS reply, created_at FROM notifications WHERE type = 'feedback') content_course_feedback WHERE course_id = ? AND student_id = ?" => "SELECT id FROM notifications WHERE type = 'feedback' AND JSON_EXTRACT(meta, '$.course_id') = ? AND JSON_EXTRACT(meta, '$.student_id') = ?"
];

$files = [
    'includes/dash_student.php',
    'student_viewer.php',
    'instructor_dashboard.php',
    'dashboards/admin.php',
    'api/instructor_v2_actions.php'
];

foreach ($files as $filename) {
    $path = __DIR__ . '/../' . $filename;
    
    if (!file_exists($path)) {
        continue;
    }

    $content = file_get_contents($path);
    $original = $content;

    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    
    // SPECIFIC COMPLEX FIXES
    
    // Fix student_viewer.php INSERT query (since submit feedback now inserts into notifications)
    if ($filename === 'student_viewer.php') {
        // Need to find creator of the course logically for user_id recipient.
        // Actually earlier in student_viewer, we have $content which has `creator_id`!
        // We will replace the insert statement.
        $old_insert = "INSERT INTO content (record_type, parent_id, user_id, generic_value, description) VALUES ('feedback', ?, ?, ?, ?)";
        // wait, we need to inject the meta json structure.
        // Instead of replacing the string in code, let's just do a manual replace of the whole block in student_viewer.php using multi_replace_file_content tool after this script runs, or just do it here:
        $new_insert = "INSERT INTO notifications (type, user_id, target_role, message, meta) VALUES ('feedback', (SELECT user_id FROM content WHERE id = ?), 'instructor', ?, JSON_OBJECT('course_id', ?, 'student_id', ?, 'rating', ?, 'reply', null))";
        // BUT wait, PDO placeholders in execute need to change order! Original: execute([$id, $user_id, $rating, $comment]) -> $id is course_id.
        // If we do: execute([$id, $comment, $id, $user_id, $rating]) -> parameter array changes.
        // Better to skip inserting here and do it natively via tool wrapper.
    }

    if ($filename === 'api/instructor_v2_actions.php') {
        // Find UPDATE reply query
        $str = "UPDATE content SET meta_text = ? WHERE id = ? AND record_type = 'feedback'";
        $new_str = "UPDATE notifications SET meta = JSON_SET(meta, '$.reply', ?) WHERE id = ? AND type = 'feedback'";
        $content = str_replace($str, $new_str, $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Refactored: $filename\n";
    }
}
echo "Automated SQL substitution complete.\n";
?>
