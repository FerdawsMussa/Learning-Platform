<?php
// c:\xampp\htdocs\lastfyp\api\upload_lesson.php
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
    $module_id = !empty($_POST['module_id']) ? $_POST['module_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'video';
    $content = trim($_POST['content'] ?? ''); // text or url
    
    if (empty($course_id) || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Course ID and Title are required.']);
        exit;
    }

    // Verify course ownership
    $checkOwner = $pdo->prepare("SELECT id FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ? AND creator_id = ?");
    $checkOwner->execute([$course_id, $creator_id]);
    if ($checkOwner->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to modify this course.']);
        exit;
    }

    try {
        $file_path = null;
        $file_size = null;

        if (in_array($type, ['video', 'pdf'])) {
            if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
                // Determine limits & dirs
                $uploadDir = '../uploads/' . ($type === 'pdf' ? 'pdfs/' : 'videos/');
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                $bytes = $_FILES['file']['size'];
                
                // Allow specific types
                $allowed = ($type === 'pdf') ? ['pdf'] : ['mp4', 'webm', 'ogg'];
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file format.']);
                    exit;
                }

                $file_size = $bytes > 1024*1024 ? round($bytes / (1024*1024), 2) . ' MB' : round($bytes / 1024, 2) . ' KB';
                $fileName = uniqid($type . '_') . '.' . $ext;
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                    $file_path = 'uploads/' . ($type === 'pdf' ? 'pdfs/' : 'videos/') . $fileName;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
                    exit;
                }
            } else if (empty($content) && $type === 'video') {
                // Wait, if it's a video and no file, maybe it's a Youtube URL which is inside $content
                echo json_encode(['success' => false, 'message' => 'Upload a file or provide a video URL.']);
                exit;
            }
        } elseif ($type === 'text') {
            if (empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Text content is required.']);
                exit;
            }
        }

        // Get max order
        $orderStmt = $pdo->prepare("SELECT MAX(order_number) FROM (SELECT id, course_id, item_id AS module_id, title, content_type AS type, content_text AS content, file_path, file_size, order_number, created_at FROM progress WHERE record_type = 'lesson') progress_lessons WHERE course_id = ?");
        $orderStmt->execute([$course_id]);
        $max_order = $orderStmt->fetchColumn() ?? 0;

        $stmt = $pdo->prepare("INSERT INTO progress (record_type, course_id, item_id, title, content_type, content_text, file_path, file_size, order_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $module_id, $title, $type, $content, $file_path, $file_size, $max_order + 1]);

        echo json_encode(['success' => true, 'message' => 'Lesson successfully added.']);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
