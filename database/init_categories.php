<?php
require_once '../api/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $default_categories = [
        'Programming & Development',
        'Web Development',
        'UI/UX & Design',
        'Data & Analytics',
        'AI & Machine Learning',
        'Cybersecurity',
        'Mobile App Development',
        'Soft Skills',
        'Business & Career Skills',
        'Tools & Technologies'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    foreach ($default_categories as $cat) {
        $stmt->execute([$cat]);
    }

    echo "Categories table initialized and seeded successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
