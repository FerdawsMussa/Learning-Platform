<?php
require_once 'api/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        cert_id VARCHAR(50) UNIQUE NOT NULL,
        issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(student_id),
        INDEX(course_id)
    )");
    echo "Certificates table created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
