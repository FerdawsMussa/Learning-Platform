<?php
require_once 'session_helper.php';
start_role_session();
require_once 'db.php';

// Only track students
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    exit;
}

try {
    // Increment total time spent by 1 minute
    $stmt = $pdo->prepare("UPDATE students SET total_time_spent = total_time_spent + 1 WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
}
