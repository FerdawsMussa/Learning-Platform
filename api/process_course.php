<?php
session_start();
require_once 'db.php';

// Only allow creators
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['instructor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creator_id = $_SESSION['user_id'];
    
    // Course Details
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $level = trim($_POST['level'] ?? 'Beginner');

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Course title is required.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Handle Thumbnail Upload (Optional)
        $thumbnail_path = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $uploadDir = '../uploads/thumbnails/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = uniqid('thumb_') . '.' . $ext;
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $fileName)) {
                    $thumbnail_path = 'uploads/thumbnails/' . $fileName;
                }
            }
        }

        // 2. Create Course
        $stmt = $pdo->prepare("INSERT INTO content (record_type, user_id, title, description, category, generic_value, file_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$creator_id, $title, $description, $category, $level, $thumbnail_path]);
        $course_id = $pdo->lastInsertId();

        // 3. Process Modules
        // Expected format: $_POST['module_titles'] as an array of strings
        $module_titles = $_POST['module_titles'] ?? [];
        $module_ids = [];

        $modStmt = $pdo->prepare("INSERT INTO modules (course_id, title, order_number) VALUES (?, ?, ?)");
        
        foreach ($module_titles as $index => $mod_title) {
            if (!empty(trim($mod_title))) {
                $modStmt->execute([$course_id, trim($mod_title), $index + 1]);
                $module_ids[$index] = $pdo->lastInsertId();
            }
        }

        // 4. Process Lessons
        // Expected format: 
        // $_POST['lesson_titles'] array
        // $_POST['lesson_module_indices'] array (mapping to module index)
        // $_FILES['lesson_files'] array
        
        if (isset($_FILES['lesson_files']) && !empty($_FILES['lesson_files']['name'][0])) {
            $lessonTitles = $_POST['lesson_titles'] ?? [];
            $lessonModuleIndices = $_POST['lesson_module_indices'] ?? [];
            
            $lessonStmt = $pdo->prepare("INSERT INTO progress_lessons (course_id, module_id, title, file_path, file_size, order_number) VALUES (?, ?, ?, ?, ?, ?)");
            $uploadDir = '../uploads/videos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $order_counter = 1;

            foreach ($_FILES['lesson_files']['name'] as $i => $name) {
                if ($_FILES['lesson_files']['error'][$i] == 0) {
                    $module_idx = $lessonModuleIndices[$i] ?? -1;
                    $actual_module_id = isset($module_ids[$module_idx]) ? $module_ids[$module_idx] : null;
                    
                    // Fallback to first module if no valid map, or null if no modules
                    if ($actual_module_id === null && count($module_ids) > 0) {
                        $actual_module_id = reset($module_ids); 
                    }

                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    // Basic file size calculation (in MB/KB for UI)
                    $bytes = $_FILES['lesson_files']['size'][$i];
                    $file_size = $bytes > 1024*1024 ? round($bytes / (1024*1024), 2) . ' MB' : round($bytes / 1024, 2) . ' KB';
                    
                    $fileName = uniqid('lesson_') . '.' . $ext;
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['lesson_files']['tmp_name'][$i], $targetPath)) {
                        $dbPath = 'uploads/videos/' . $fileName;
                        $l_title = !empty($lessonTitles[$i]) ? trim($lessonTitles[$i]) : "Lesson " . ($i + 1);
                        
                        $lessonStmt->execute([
                            $course_id,
                            $actual_module_id,
                            $l_title,
                            $dbPath,
                            $file_size,
                            $order_counter++
                        ]);
                    }
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Course created successfully!', 'course_id' => $course_id]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
