<?php
// /scratch/inspect_progress.php
require_once __DIR__ . '/../api/db.php';
$tables = ['progress_certificates', 'progress_lessons', 'progress_lesson_progress', 'progress_streaks'];
foreach ($tables as $t) {
    echo "Columns for $t:\n";
    try {
        $stmt = $pdo->query("DESCRIBE `$t` ");
        foreach ($stmt->fetchAll() as $col) {
            echo "- " . $col['Field'] . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
?>
