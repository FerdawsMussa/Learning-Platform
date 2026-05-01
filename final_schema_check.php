<?php
require_once 'api/db.php';
try {
    echo "--- COURSES TABLE ---\n";
    $stmt = $pdo->query("DESCRIBE content_courses");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n--- LESSONS TABLE ---\n";
    $stmt = $pdo->query("DESCRIBE progress_lessons");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
