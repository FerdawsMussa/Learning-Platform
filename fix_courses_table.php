<?php
require_once 'api/db.php';

try {
    // Check if columns exist
    $stmt = $pdo->query("DESCRIBE content_courses");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $needed_columns = [
        'category' => "VARCHAR(100) AFTER description",
        'level' => "ENUM('Beginner', 'Intermediate', 'Advanced') AFTER category",
        'thumbnail_path' => "VARCHAR(255) AFTER level"
    ];

    foreach ($needed_columns as $col => $definition) {
        if (!in_array($col, $columns)) {
            echo "Adding column '$col'...\n";
            $pdo->exec("ALTER TABLE content_courses ADD COLUMN $col $definition");
            echo "Column '$col' added.\n";
        } else {
            echo "Column '$col' already exists.\n";
        }
    }
    echo "\nDatabase fix completed!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
