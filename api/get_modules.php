<?php
// c:\xampp\htdocs\lastfyp\api\get_modules.php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    echo json_encode([]);
    exit;
}

$course_id = intval($_GET['course_id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT id, title FROM modules WHERE course_id = ? ORDER BY order_number ASC, created_at ASC");
    $stmt->execute([$course_id]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($modules);
} catch (PDOException $e) {
    echo json_encode([]);
}
