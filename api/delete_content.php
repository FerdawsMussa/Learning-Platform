<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'creator') {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['id'])) {
    $content_id = $_GET['id'];
    $creator_id = $_SESSION['user_id'];

    try {
        // First verify ownership and retrieve the file path
        $stmt = $pdo->prepare("SELECT file_path FROM (SELECT id, user_id AS creator_id, title, file_path, generic_value AS file_type, category, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'resource') content_resources WHERE id = ? AND creator_id = ?");
        $stmt->execute([$content_id, $creator_id]);
        $content = $stmt->fetch();

        if ($content) {
            // Delete actual file securely
            $physical_path = '../' . $content['file_path'];
            if (file_exists($physical_path)) {
                unlink($physical_path);
            }

            // Delete database row from categorized table
            $delStmt = $pdo->prepare("DELETE FROM content WHERE id = ?");
            $delStmt->execute([$content_id]);

            header("Location: ../dashboard.php?success=content_deleted");
            exit;
        } else {
            die("Unauthorized or content not found.");
        }
    } catch (PDOException $e) {
         die("Error deleting content.");
    }
}
header("Location: ../dashboard.php");
exit;
