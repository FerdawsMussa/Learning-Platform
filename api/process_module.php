<?php
// c:\xampp\htdocs\lastfyp\api\process_module.php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creator_id = $_SESSION['user_id'];
    $course_id = $_POST['course_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    
    if (empty($course_id) || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Module title required.']);
        exit;
    }

    // Verify course ownership
    $checkOwner = $pdo->prepare("SELECT id FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ? AND creator_id = ?");
    $checkOwner->execute([$course_id, $creator_id]);
    if ($checkOwner->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit;
    }

    try {
        $orderStmt = $pdo->prepare("SELECT MAX(order_number) FROM modules WHERE course_id = ?");
        $orderStmt->execute([$course_id]);
        $max_order = $orderStmt->fetchColumn() ?? 0;

        $stmt = $pdo->prepare("INSERT INTO modules (course_id, title, order_number) VALUES (?, ?, ?)");
        $stmt->execute([$course_id, $title, $max_order + 1]);

        echo json_encode(['success' => true, 'message' => 'Module correctly created.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
}
