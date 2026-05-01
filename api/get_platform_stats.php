<?php
require_once 'db.php';
header('Content-Type: application/json');

try {
    $courses = $pdo->query("SELECT COUNT(*) FROM content WHERE record_type = 'course' AND is_deleted = 0")->fetchColumn();
    $students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $resources = $pdo->query("SELECT COUNT(*) FROM content WHERE record_type = 'resource' AND is_deleted = 0")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'courses' => (int)$courses,
            'students' => (int)$students,
            'resources' => (int)$resources
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
