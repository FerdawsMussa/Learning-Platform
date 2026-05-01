<?php
require_once 'session_helper.php';
start_role_session();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false]));
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE target_role = 'admin' AND is_read = 0");
    $unread_count = $stmt->fetchColumn();

    $latest_stmt = $pdo->query("SELECT message FROM notifications WHERE target_role = 'admin' ORDER BY created_at DESC LIMIT 1");
    $latest_msg = $latest_stmt->fetchColumn() ?: "No new notifications";

    echo json_encode([
        'success' => true,
        'unread_count' => (int)$unread_count,
        'latest_msg' => $latest_msg
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
