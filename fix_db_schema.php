<?php
require_once 'api/db.php';

try {
    // FIX COURSES TABLE
    echo "Checking 'content_courses' table...\n";
    $stmt = $pdo->query("DESCRIBE content_courses");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $needed_content_courses = [
        'category' => "VARCHAR(100) AFTER description",
        'level' => "ENUM('Beginner', 'Intermediate', 'Advanced') AFTER category",
        'thumbnail_path' => "VARCHAR(255) AFTER level"
    ];

    foreach ($needed_content_courses as $col => $definition) {
        if (!in_array($col, $columns)) {
            echo "Adding column '$col' to 'content_courses'...\n";
            $pdo->exec("ALTER TABLE content_courses ADD COLUMN $col $definition");
        }
    }

    // FIX LESSONS TABLE
    echo "\nChecking 'progress_lessons' table...\n";
    $stmt = $pdo->query("DESCRIBE progress_lessons");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $needed_progress_lessons = [
        'type' => "ENUM('video', 'pdf', 'text') DEFAULT 'video' AFTER title",
        'content' => "TEXT AFTER type"
    ];

    foreach ($needed_progress_lessons as $col => $definition) {
        if (!in_array($col, $columns)) {
            echo "Adding column '$col' to 'progress_lessons'...\n";
            $pdo->exec("ALTER TABLE progress_lessons ADD COLUMN $col $definition");
        }
    }

    echo "\nDatabase fix completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
