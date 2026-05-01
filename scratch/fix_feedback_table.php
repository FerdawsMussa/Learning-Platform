<?php
require_once 'api/db.php';

try {
    // 1. Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        student_id INT NOT NULL,
        rating INT DEFAULT 5,
        comment TEXT,
        reply TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Double check if rating column exists (if table existed before but was basic)
    $columns = $pdo->query("DESCRIBE course_feedback")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('rating', $columns)) {
        $pdo->exec("ALTER TABLE course_feedback ADD COLUMN rating INT DEFAULT 5 AFTER student_id");
        echo "Added rating column.\n";
    }
    
    if (!in_array('comment', $columns)) {
        $pdo->exec("ALTER TABLE course_feedback ADD COLUMN comment TEXT AFTER rating");
        echo "Added comment column.\n";
    }

    echo "Database schema verified successfully.";
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}
?>
