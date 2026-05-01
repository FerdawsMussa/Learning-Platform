<?php
$dir = __DIR__ . '/../';
$it = new RecursiveDirectoryIterator($dir);
$legacy_terms = [
    'content_courses', 'content_resources', 'content_course_feedback',
    'progress_lessons', 'progress_certificates', 'progress_lesson_progress', 'progress_streaks',
    'notification_admin', 'notification_student', 'notification_instructor',
    'thumbnail_path', 'streak_count', 'cert_id', 'module_id'
];

$found = [];
foreach (new RecursiveIteratorIterator($it) as $file) {
    if ($file->getExtension() === 'php' && strpos($file->getRealPath(), 'scratch') === false) {
        $content = file_get_contents($file->getRealPath());
        foreach ($legacy_terms as $term) {
            // regex to catch SELECT col FROM / UPDATE table etc, ignoring if it's already properly aliased like 'foo AS thumbnail_path'
            if (preg_match("/\b$term\b/i", $content, $matches, PREG_OFFSET_CAPTURE)) {
                
                // Let's print out the context around the match to manually analyze
                $pos = $matches[0][1];
                $context = substr($content, max(0, $pos - 30), 60);
                $found[] = "Found '$term' in " . $file->getFilename() . " | Context: " . str_replace("\n", " ", $context);
            }
        }
    }
}
file_put_contents(__DIR__ . '/diagnostics.txt', implode("\n", $found));
echo "Found " . count($found) . " issues.\n";
?>
