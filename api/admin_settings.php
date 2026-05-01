<?php
require_once 'session_helper.php';
start_role_session();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$admin_id = $_SESSION['user_id'];
$key = $_POST['key'] ?? '';
$value = $_POST['value'] ?? '';

$allowed_keys = ['theme_mode', 'notifications_enabled', 'full_name'];

if (!in_array($key, $allowed_keys)) {
    die(json_encode(['success' => false, 'message' => 'Invalid key']));
}

try {
    if ($key === 'full_name') {
        $stmt = $pdo->prepare("UPDATE admins SET full_name = ? WHERE id = ?");
        $stmt->execute([$value, $admin_id]);
        $_SESSION['username'] = $value; // Update name in session
    } else {
        $stmt = $pdo->prepare("UPDATE admins SET $key = ? WHERE id = ?");
        $stmt->execute([$value, $admin_id]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
