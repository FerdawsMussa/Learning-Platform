<?php
require_once dirname(__DIR__) . '/api/db.php';

echo "Running Migration...\n";

try {
    // Check if category column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `content_courses` LIKE 'category'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `content_courses` ADD `category` VARCHAR(100) DEFAULT 'web programming' AFTER `description`");
        echo "Added 'category' column to content_courses table.\n";
    } else {
        echo "'category' column already exists.\n";
    }

    // Check if level column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `content_courses` LIKE 'level'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `content_courses` ADD `level` VARCHAR(50) DEFAULT 'Beginner' AFTER `category`");
        echo "Added 'level' column to content_courses table.\n";
    } else {
        echo "'level' column already exists.\n";
    }
    
    echo "Migration Complete.\n";
} catch(PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
