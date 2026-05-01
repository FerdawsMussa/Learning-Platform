<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creator_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $level = trim($_POST['level'] ?? '');
    
    if (empty($title) || empty($category) || empty($level)) {
        die("Missing required fields.");
    }
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $fileSize = $_FILES['file']['size'];
        
        // Allowed extensions: pdf, docx, ppt
        if (!in_array($ext, ['pdf', 'docx', 'ppt'])) {
            die("Invalid file type. Only PDF, DOCX, and PPT are allowed.");
        }
        
        // Size Limit: 20MB
        if ($fileSize > 20 * 1024 * 1024) {
            die("File exceeds the 20MB size limit.");
        }
        
        $uploadDir = '../uploads/content/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        
        if(move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            $dbPath = 'uploads/content/' . $fileName;
            // Use the new categorized table 'content_resources' instead of legacy 'content'
            $stmt = $pdo->prepare("INSERT INTO content (record_type, title, file_path, category, user_id) VALUES ('resource', ?, ?, ?, ?)");
            $stmt->execute([$title, $dbPath, $category, $creator_id]);
            
            // Trigger Notification Category: Admin Alert
            $notif = $pdo->prepare("INSERT INTO notifications (target_role, message) VALUES ('admin', ?) VALUES (?)");
            $notif->execute(["Instructor {$_SESSION['username']} uploaded a new resource: $title"]);

            header("Location: ../dashboard.php?success=content_uploaded");
            exit;
        } else {
            die("Failed to move uploaded file.");
        }
    } else {
        die("Please attach a file to upload.");
    }
}
header("Location: ../dashboard.php");
exit;
