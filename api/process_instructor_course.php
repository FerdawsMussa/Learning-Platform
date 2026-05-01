<?php
// c:\xampp\htdocs\lastfyp\api\process_instructor_course.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creator_id = $_SESSION['user_id'];
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $level = trim($_POST['level'] ?? '');

    if (empty($title) || empty($description)) {
        die("Missing required course fields.");
    }

    $thumbnail_path = null;
    
    // Process Image Upload
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        $fileSize = $_FILES['thumbnail']['size'];
        
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            die("Invalid thumbnail format. Only JPG, PNG, WEBP allowed.");
        }
        
        if ($fileSize > 5 * 1024 * 1024) {
            die("Thumbnail exceeds 5MB limit.");
        }
        
        $uploadDir = '../uploads/thumbnails/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid('thumb_') . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $targetPath)) {
            $thumbnail_path = 'uploads/thumbnails/' . $fileName;
        } else {
            die("Failed to upload thumbnail.");
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO content (record_type, user_id, title, description, category, generic_value, file_path) VALUES ('course', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$creator_id, $title, $description, $category, $level, $thumbnail_path]);
        $course_id = $pdo->lastInsertId();

        // 📡 Notification Category: Admin Alert
        $notifAdmin = $pdo->prepare("INSERT INTO notifications (target_role, message) VALUES ('admin', ?) VALUES (?)");
        $notifAdmin->execute(["Instructor {$_SESSION['username']} created a new course: $title"]);

        // 📡 Notification Category: Student Alert (Broadcast)
        // Note: In a production app, this would be a single record or filtered by tags/category.
        // For now, we notify all student IDs.
        $students = $pdo->query("SELECT id FROM users WHERE role = 'student'")->fetchAll(PDO::FETCH_COLUMN);
        $notifStudent = $pdo->prepare("INSERT INTO notifications (user_id, target_role, message) VALUES (?, 'student', ?)");
        foreach ($students as $s_id) {
            $notifStudent->execute([$s_id, "New course available: $title in $category category."]);
        }
        
        header("Location: ../instructor_dashboard.php?success=course_created");
        exit;
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
