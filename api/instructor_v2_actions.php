<?php
require_once 'session_helper.php';
start_role_session();
require_once 'db.php';

// Detect if POST data was lost due to post_max_size limit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'The uploaded file is too large for the server to process. Please try a smaller file or increase post_max_size in your server config.'
    ]);
    exit;
}

// Ensure instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function detectFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $video = ['mp4', 'webm', 'mkv', 'avi', 'mov'];
    $image = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    $pdf = ['pdf'];
    $doc = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
    $archive = ['zip', 'rar', '7z', 'tar', 'gz'];

    if (in_array($ext, $video)) return 'video';
    if (in_array($ext, $image)) return 'image';
    if (in_array($ext, $pdf)) return 'pdf';
    if (in_array($ext, $doc)) return 'document';
    if (in_array($ext, $archive)) return 'archive';
    return 'file';
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) { $bytes = number_format($bytes / 1073741824, 2) . ' GB'; }
    elseif ($bytes >= 1048576) { $bytes = number_format($bytes / 1048576, 2) . ' MB'; }
    elseif ($bytes >= 1024) { $bytes = number_format($bytes / 1024, 2) . ' KB'; }
    elseif ($bytes > 1) { $bytes = $bytes . ' bytes'; }
    elseif ($bytes == 1) { $bytes = $bytes . ' byte'; }
    else { $bytes = '0 bytes'; }
    return $bytes;
}

function processLessonFiles($pdo, $course_id, $lesson_id, $file_input_name) {
    if (!isset($_FILES[$file_input_name]) || !is_array($_FILES[$file_input_name]['name'])) return;
    
    $files = $_FILES[$file_input_name];
    $count = count($files['name']);
    
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === 0) {
            $name = $files['name'][$i];
            $size = formatSize($files['size'][$i]);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $filename = uniqid('res_') . '.' . $ext;
            $type = detectFileType($name);
            
            if (!is_dir('../uploads/lessons/')) mkdir('../uploads/lessons/', 0777, true);
            if (move_uploaded_file($files['tmp_name'][$i], '../uploads/lessons/' . $filename)) {
                $path = 'uploads/lessons/' . $filename;
                $stmt = $pdo->prepare("INSERT INTO progress (record_type, course_id, item_id, title, file_path, file_size, generic_value, order_number) VALUES ('lesson_resource', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$course_id, $lesson_id, $name, $path, $size, $type, $i + 1]);
            }
        }
    }
}

function processResourceFiles($pdo, $resource_id, $file_input_name) {
    if (!isset($_FILES[$file_input_name]) || !is_array($_FILES[$file_input_name]['name'])) return;
    
    $files = $_FILES[$file_input_name];
    $count = count($files['name']);
    
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === 0) {
            $name = $files['name'][$i];
            $size = formatSize($files['size'][$i]);
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $filename = uniqid('res_file_') . '.' . $ext;
            $type = detectFileType($name);
            
            if (!is_dir('../uploads/resources/')) mkdir('../uploads/resources/', 0777, true);
            if (move_uploaded_file($files['tmp_name'][$i], '../uploads/resources/' . $filename)) {
                $path = 'uploads/resources/' . $filename;
                $stmt = $pdo->prepare("INSERT INTO content (record_type, parent_id, title, file_path, file_size, generic_value, user_id) VALUES ('resource_file', ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$resource_id, $name, $path, $size, $type, $_SESSION['user_id']]);
            }
        }
    }
}

switch ($action) {
    case 'create_course_dynamic':
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $level = $_POST['level'] ?? '';
        $tags = trim($_POST['tags'] ?? '');

        if (empty($title) || empty($category)) {
            echo json_encode(['success' => false, 'message' => 'Title and category are required.']);
            exit;
        }

        try {
            // Start Transaction
            $pdo->beginTransaction();

            // Handle Thumbnail
            $thumbnail_path = null;
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
                $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('thumb_') . '.' . $ext;
                $destination = '../uploads/thumbnails/' . $filename;
                if (!is_dir('../uploads/thumbnails/')) mkdir('../uploads/thumbnails/', 0777, true);
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $destination)) {
                    $thumbnail_path = 'uploads/thumbnails/' . $filename;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO content (record_type, title, description, category, user_id, file_path, generic_value, meta_text, is_resource, is_approved) VALUES ('course', ?, ?, ?, ?, ?, ?, ?, 0, 0)");
            $stmt->execute([$title, $description, $category, $user_id, $thumbnail_path, $level, $tags]);
            $course_id = $pdo->lastInsertId();

            // Update instructor's courses array
            $get_u = $pdo->prepare("SELECT courses FROM users WHERE id = ?");
            $get_u->execute([$user_id]);
            $c_arr = json_decode($get_u->fetchColumn() ?: '[]', true);
            if (!in_array($course_id, $c_arr)) {
                $c_arr[] = (int)$course_id;
                $upd_u = $pdo->prepare("UPDATE users SET courses = ? WHERE id = ?");
                $upd_u->execute([json_encode($c_arr), $user_id]);
            }

            if (isset($_POST['progress_lessons']) && is_array($_POST['progress_lessons'])) {
                $order = 1;
                foreach ($_POST['progress_lessons'] as $idx => $lesson) {
                    $l_title = trim($lesson['title'] ?? '');
                    $l_type = $lesson['type'] ?? 'video';
                    $l_content = trim($lesson['content'] ?? '');
                    
                    if (empty($l_title)) continue;

                    $stmtL = $pdo->prepare("INSERT INTO progress (record_type, course_id, title, content_text, content_type, order_number) VALUES ('lesson', ?, ?, ?, ?, ?)");
                    $stmtL->execute([$course_id, $l_title, $l_content, $l_type, $order++]);
                    $lesson_id = $pdo->lastInsertId();

                    // Handle Multiple Files
                    $file_key = "lesson_files_" . $idx;
                    processLessonFiles($pdo, $course_id, $lesson_id, $file_key);
                }
            }

            $pdo->commit();

            // Notify admin for course approval
            $instructor_name = $_SESSION['user_name'] ?? 'An instructor';
            $notifMeta = json_encode(['course_id' => $course_id, 'instructor_id' => $user_id]);
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, target_role, type, message, meta) VALUES (?, 'admin', 'course_approval', ?, ?)");
            $notif->execute([$user_id, "$instructor_name submitted a new course for review: \"$title\"", $notifMeta]);

            echo json_encode(['success' => true, 'course_id' => $course_id, 'pending' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        break;

    case 'create_resource_dynamic':
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $level = $_POST['level'] ?? 'Beginner';
        
        if (empty($title) || empty($category)) {
            echo json_encode(['success' => false, 'message' => 'Title and category required.']);
            exit;
        }

        try {
            $pdo->beginTransaction();



            $stmt = $pdo->prepare("INSERT INTO content (record_type, user_id, title, file_path, generic_value, category, is_resource, is_approved) VALUES ('resource', ?, ?, 'MULTIPLE', ?, ?, 1, 0)");
            $stmt->execute([$user_id, $title, $level, $category]);
            $resource_id = $pdo->lastInsertId();

            // Process Multiple Files
            processResourceFiles($pdo, $resource_id, 'resource_files');

            // Update instructor's resources array
            $get_u = $pdo->prepare("SELECT resources FROM users WHERE id = ?");
            $get_u->execute([$user_id]);
            $r_arr = json_decode($get_u->fetchColumn() ?: '[]', true);
            if (!in_array($resource_id, $r_arr)) {
                $r_arr[] = (int)$resource_id;
                $upd_u = $pdo->prepare("UPDATE users SET resources = ? WHERE id = ?");
                $upd_u->execute([json_encode($r_arr), $user_id]);
            }

            $pdo->commit();

            // Notify admin for resource approval
            $instructor_name = $_SESSION['user_name'] ?? 'An instructor';
            $notifMeta = json_encode(['course_id' => $resource_id, 'instructor_id' => $user_id, 'is_resource' => true]);
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, target_role, type, message, meta) VALUES (?, 'admin', 'course_approval', ?, ?)");
            $notif->execute([$user_id, "$instructor_name submitted a new resource for review: \"$title\"", $notifMeta]);

            echo json_encode(['success' => true, 'resource_id' => $resource_id, 'pending' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        break;

    case 'get_resource_files':
        $resource_id = $_GET['resource_id'] ?? '';
        if (empty($resource_id)) {
            echo json_encode(['success' => false, 'message' => 'Resource ID missing.']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, title, generic_value as type FROM content WHERE record_type = 'resource_file' AND parent_id = ?");
        $stmt->execute([$resource_id]);
        echo json_encode(['success' => true, 'files' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'update_resource':
        $id = $_POST['resource_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $level = $_POST['level'] ?? 'Beginner';
        if (empty($id) || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'ID and Title required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE content SET title = ?, generic_value = ? WHERE id = ? AND user_id = ? AND record_type = 'resource'");
            $stmt->execute([$title, $level, $id, $user_id]);
            processResourceFiles($pdo, $id, 'resource_files');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
        }
        break;

    case 'delete_resource_file':
        $file_id = $_POST['file_id'] ?? '';
        if (empty($file_id)) {
            echo json_encode(['success' => false, 'message' => 'File ID missing.']);
            exit;
        }
        try {
            // First get the path to delete the physical file
            $stmt = $pdo->prepare("SELECT file_path FROM content WHERE id = ? AND record_type = 'resource_file' AND user_id = ?");
            $stmt->execute([$file_id, $user_id]);
            $file = $stmt->fetch();
            if ($file) {
                $full_path = '../' . $file['file_path'];
                if (file_exists($full_path)) unlink($full_path);
                
                $stmt = $pdo->prepare("DELETE FROM content WHERE id = ? AND record_type = 'resource_file' AND user_id = ?");
                $stmt->execute([$file_id, $user_id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'File not found.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        break;

    case 'request_new_category':
        $requested = trim($_POST['requested_category'] ?? '');
        if (empty($requested)) {
            echo json_encode(['success' => false, 'message' => 'Please select a category.']);
            exit;
        }

        try {
            // Check if a request already exists to prevent spam
            $check = $pdo->prepare("SELECT id FROM notifications WHERE type = 'category_request' AND user_id = ? AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.category')) = ?");
            $check->execute([$user_id, $requested]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You already have a pending request for this category.']);
                exit;
            }

            $meta = json_encode([
                'instructor_id' => $user_id,
                'instructor_name' => $_SESSION['user_name'] ?? 'Instructor',
                'category' => $requested,
                'status' => 'pending'
            ]);

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, target_role, type, message, meta) VALUES (?, 'admin', 'category_request', ?, ?)");
            $stmt->execute([$user_id, "Requested to teach: $requested", $meta]);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Request failed: ' . $e->getMessage()]);
        }
        break;

    case 'add_lesson':
        $course_id = $_POST['course_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content_val = trim($_POST['content_url'] ?? '');
        $type = $_POST['type'] ?? 'video';

        if (empty($course_id) || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Course ID and Title required.']);
            exit;
        }

        // Validate ownership
        $check = $pdo->prepare("SELECT id FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ? AND creator_id = ?");
        $check->execute([$course_id, $user_id]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized access to course.']);
            exit;
        }

        $file_path = null;
        $lesson_content = null;

        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('lesson_') . '.' . $ext;
            $destination = '../uploads/progress_lessons/' . $filename;
            if (!is_dir('../uploads/progress_lessons/'))
                mkdir('../uploads/progress_lessons/', 0777, true);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
                $file_path = 'uploads/progress_lessons/' . $filename;
            }
        } elseif (!empty($content_val)) {
            // No file uploaded, check if we have an external link or text content
            if ($type === 'text') {
                $lesson_content = $content_val;
            } else {
                // For video/pdf/other, store the link in file_path
                $file_path = $content_val;
            }
        }

        try {
            // Get next order number
            $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(order_number), 0) FROM progress WHERE record_type = 'lesson' AND course_id = ?");
            $order_stmt->execute([$course_id]);
            $new_order = $order_stmt->fetchColumn() + 1;

            $stmt = $pdo->prepare("INSERT INTO progress (record_type, course_id, title, content_text, content_type, order_number) VALUES ('lesson', ?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $title, $content_val, $type, $new_order]);
            $lesson_id = $pdo->lastInsertId();

            // Process Multiple Files
            processLessonFiles($pdo, $course_id, $lesson_id, 'files');

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        break;

    case 'reply_feedback':
        $feedback_id = $_POST['feedback_id'] ?? '';
        $reply = trim($_POST['reply'] ?? '');

        if (empty($feedback_id) || empty($reply)) {
            echo json_encode(['success' => false, 'message' => 'Incomplete data.']);
            exit;
        }

        try {
            // Check ownership via course -> feedback
            $stmt = $pdo->prepare("SELECT f.id FROM (SELECT id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) AS course_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.student_id')) AS student_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) AS rating, message AS comment, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reply')) AS reply, created_at FROM notifications WHERE type = 'feedback') f JOIN content c ON f.course_id = c.id WHERE f.id = ? AND c.user_id = ? AND c.record_type = 'course'");
            $stmt->execute([$feedback_id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
                exit;
            }

            $pdo->prepare("UPDATE notifications SET meta = JSON_SET(meta, '$.reply', ?) WHERE id = ? AND type = 'feedback'")->execute([$reply, $feedback_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error.']);
        }
        break;

    case 'get_progress_lessons':
        $course_id = $_GET['course_id'] ?? '';
        try {
            // Fetch both lessons and their resources
            $stmt = $pdo->prepare("SELECT id, record_type, item_id, title, content_type as type, content_text as content, file_path, file_size, order_number FROM progress WHERE course_id = ? AND record_type IN ('lesson', 'lesson_resource') ORDER BY order_number ASC");
            $stmt->execute([$course_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $lessons = [];
            foreach ($rows as $r) {
                if ($r['record_type'] === 'lesson') {
                    $r['resources'] = [];
                    $lessons[$r['id']] = $r;
                }
            }
            foreach ($rows as $r) {
                if ($r['record_type'] === 'lesson_resource' && isset($lessons[$r['item_id']])) {
                    $lessons[$r['item_id']]['resources'][] = $r;
                }
            }
            echo json_encode(array_values($lessons));
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'delete_content':
        $id = $_POST['id'] ?? '';
        $type = $_POST['type'] ?? 'course';
        try {
            if ($type === 'resource') {
                $stmt = $pdo->prepare("DELETE FROM content WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE content SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_lesson':
        $id = $_POST['id'] ?? '';
        try {
            // Check ownership
            $stmt = $pdo->prepare("SELECT l.id FROM (SELECT id, course_id, item_id AS module_id, title, content_type AS type, content_text AS content, file_path, file_size, order_number, created_at FROM progress WHERE record_type = 'lesson') l JOIN content c ON l.course_id = c.id WHERE l.id = ? AND c.user_id = ? AND c.record_type = 'course'");
            $stmt->execute([$id, $user_id]);
            if ($stmt->fetch()) {
                $pdo->prepare("DELETE FROM progress WHERE id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM progress WHERE record_type = 'lesson_resource' AND item_id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error.']);
        }
        break;



    case 'get_roster':
        try {
            // 1. Get instructor's course IDs
            $stmt_i = $pdo->prepare("SELECT courses FROM users WHERE id = ?");
            $stmt_i->execute([$user_id]);
            $i_content_courses = json_decode($stmt_i->fetchColumn() ?: '[]', true);

            if (empty($i_content_courses)) {
                echo json_encode([]);
                exit;
            }

            // 2. Fetch students who have any of these content_courses
            // This is a bit complex in SQL to do correctly without enrollments.
            // We'll fetch relevant students and cross-reference.
            $roster = [];
            $all_students = $pdo->query("SELECT id, full_name, email, courses, created_at FROM users WHERE role = 'student'")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($all_students as $s) {
                $s_content_courses = json_decode($s['courses'] ?? '[]', true);
                $common = array_intersect($s_content_courses, $i_content_courses);
                foreach ($common as $cid) {
                    // Fetch course title (caching would be better)
                    $ct = $pdo->prepare("SELECT title FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ?");
                    $ct->execute([$cid]);
                    $title = $ct->fetchColumn();
                    
                    $roster[] = [
                        'student_id' => $s['id'],
                        'student_name' => $s['full_name'],
                        'email' => $s['email'],
                        'course_id' => $cid,
                        'course_title' => $title,
                        'enrolled_at' => $s['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            echo json_encode($roster);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'unenroll_student':
        $student_id = $_POST['student_id'] ?? '';
        $course_id = $_POST['course_id'] ?? '';
        
        if (empty($student_id) || empty($course_id)) {
            echo json_encode(['success' => false, 'message' => 'Missing data.']);
            exit;
        }

        try {
            // Verify the instructor owns this course
            $check = $pdo->prepare("SELECT id FROM content WHERE id = ? AND user_id = ? AND record_type = 'course'");
            $check->execute([$course_id, $user_id]);
            if (!$check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
                exit;
            }

            // Remove course_id from student's courses array
            $stmt = $pdo->prepare("SELECT courses FROM users WHERE id = ? AND role = 'student'");
            $stmt->execute([$student_id]);
            $student_data = $stmt->fetch();
            
            if ($student_data) {
                $courses = json_decode($student_data['courses'] ?: '[]', true);
                if (($key = array_search((int)$course_id, $courses)) !== false || ($key = array_search((string)$course_id, $courses)) !== false) {
                    unset($courses[$key]);
                    $courses = array_values($courses); // Re-index array
                    
                    $update = $pdo->prepare("UPDATE users SET courses = ? WHERE id = ?");
                    $update->execute([json_encode($courses), $student_id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student is not enrolled in this course.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Student not found.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        break;

    case 'get_feedback':
        try {
            $stmt = $pdo->prepare("
                SELECT f.id, f.course_id, f.student_id, f.rating, f.comment, f.reply, f.created_at, 
                       u.full_name as student_name, c.title as course_title 
                FROM (SELECT id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) AS course_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.student_id')) AS student_id, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.rating')) AS rating, message AS comment, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reply')) AS reply, created_at FROM notifications WHERE type = 'feedback') f
                JOIN users u ON f.student_id = u.id
                JOIN content c ON f.course_id = c.id
                WHERE c.user_id = ? AND c.record_type = 'course'
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$user_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'update_settings':
        $bio = trim($_POST['bio'] ?? '');
        $linkedin = trim($_POST['linkedin'] ?? '');
        $github = trim($_POST['github'] ?? '');
        $portfolio = trim($_POST['portfolio'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $current_password = $_POST['current_password'] ?? '';

        try {
            $params = [];
            $update_parts = [];

            // 1. Password Verification (If changing password)
            if (!empty($new_password)) {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $curr = $stmt->fetch();

                if (!$curr || !password_verify($current_password, $curr['password'])) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect!']);
                    exit;
                }
                if ($new_password !== $confirm_password) {
                    echo json_encode(['success' => false, 'message' => 'New passwords do not match!']);
                    exit;
                }
                $update_parts[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            // 2. Bio & Meta (Socials)
            $meta_json = json_encode([
                'linkedin' => $linkedin,
                'github' => $github,
                'facebook' => trim($_POST['facebook'] ?? ''),
                'twitter' => trim($_POST['twitter'] ?? ''),
                'portfolio' => $portfolio
            ]);
            $update_parts[] = "bio = ?, meta = ?";
            $params = array_merge($params, [$bio, $meta_json]);

            // 3. Profile Picture
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $filename = time() . '_inst_' . $user_id . '.' . $ext;
                $upload_dir = '../assets/uploads/profile_pics/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $filename)) {
                    $update_parts[] = "profile_pic = ?";
                    $params[] = 'assets/uploads/profile_pics/' . $filename;
                }
            }

            $query = "UPDATE users SET " . implode(", ", $update_parts) . " WHERE id = ? AND role = 'instructor'";
            $params[] = $user_id;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        break;

    case 'set_category':
        $category = trim($_POST['category'] ?? '');
        
        // Fetch dynamic categories
        try {
            $allowed = $pdo->query("SELECT name FROM categories")->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $allowed = []; // Fallback
        }

        if (!in_array($category, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid category selected.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT meta FROM users WHERE id = ? AND role = 'instructor'");
            $stmt->execute([$user_id]);
            $meta = json_decode($stmt->fetchColumn() ?: '{}', true);
            
            $meta['category'] = $category;
            
            $update = $pdo->prepare("UPDATE users SET meta = ? WHERE id = ?");
            $update->execute([json_encode($meta), $user_id]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_instructor_account':
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'instructor'");
            $stmt->execute([$user_id]);
            session_destroy();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        break;

    case 'send_student_notif':
        // Helper to notify students via email/db when course is published
        // (Invoked internally or via AJAX if needed)
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}
?>