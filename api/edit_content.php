<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'creator') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content_id = $_POST['content_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if (empty($content_id) || empty($title) || empty($category)) {
        die("Missing required fileds.");
    }

    try {
        // Only allow updating if this resource belongs to the current creator
        $stmt = $pdo->prepare("UPDATE content SET title = ?, category = ? WHERE id = ? AND creator_id = ?");
        $stmt->execute([$title, $category, $content_id, $_SESSION['user_id']]);
        
        header("Location: ../dashboard.php?success=content_updated");
        exit;
    } catch (PDOException $e) {
        die("Error updating content.");
    }
}
header("Location: ../dashboard.php");
exit;
