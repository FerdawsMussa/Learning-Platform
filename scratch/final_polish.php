<?php
// c:\xampp\htdocs\lastfyp\scratch\final_polish.php
$replacements = [
    // get_user_details.php fixes
    "json_decode(\$user['content_courses'] ?? '[]', true)" => "json_decode(\$user['courses'] ?? '[]', true)",
    "json_decode(\$user['content_resources'] ?? '[]', true)" => "json_decode(\$user['resources'] ?? '[]', true)",
    "\$user['content_courses_ids']" => "\$user['courses_ids']",
    "\$user['content_resources_ids']" => "\$user['resources_ids']",
    "FROM progress_lesson_progress WHERE student_id = ? AND status" => "FROM progress WHERE record_type = 'lesson_progress' AND user_id = ? AND metric_value",
    "FROM progress_streaks WHERE user_id = ?" => "FROM progress WHERE record_type = 'streak' AND user_id = ?",
    "streak_count" => "metric_value AS streak_count"
];

$files_to_check = [
    'api/get_user_details.php',
    'certificate_engine.php',
    'admin_actions.php',
    'admin_auth.php',
    'instructor_v2_actions.php'
];

foreach ($files_to_check as $filename) {
    $path = __DIR__ . '/../' . $filename;
    if (!file_exists($path)) continue;

    $content = file_get_contents($path);
    $original = $content;

    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    
    // Fix specific bug in get_user_details.php for progress alias
    if ($filename === 'api/get_user_details.php') {
        $content = preg_replace("/SELECT COUNT\(\*\) FROM progress_lesson_progress/", "SELECT COUNT(*) FROM progress WHERE record_type = 'lesson_progress'", $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Polished: $filename\n";
    }
}
echo "Final manual polish script done.\n";
?>
