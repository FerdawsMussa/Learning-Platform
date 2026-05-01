<?php
header('Content-Type: application/json');
require_once 'session_helper.php';
start_role_session('admin');
require_once 'db.php';

// Protect Route
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? null;
$role = $_GET['role'] ?? null; // 'student' or 'creator'

if (!$id || !$role) {
    echo json_encode(['success' => false, 'message' => 'Missing ID or Role']);
    exit;
}

try {
    if ($role === 'course' || $role === 'resource') {
        $stmt = $pdo->prepare("
            SELECT 
                c.id, c.user_id AS creator_id, c.title, c.description, c.file_path AS thumbnail_path, 
                c.category, c.generic_value AS level, c.meta_text AS tags, c.created_at,
                u.full_name as instructor_name, u.email as instructor_email, u.bio as instructor_bio, u.meta as instructor_meta
            FROM content c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ? AND c.record_type = ?
        ");
        $stmt->execute([$id, $role]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$content) {
            echo json_encode(['success' => false, 'message' => 'Content not found']);
            exit;
        }

        $content['instructor_meta'] = json_decode($content['instructor_meta'] ?? '{}', true);

        echo json_encode(['success' => true, 'data' => $content]);
        exit;
    }

    $role_map = ($role === 'creator') ? 'instructor' : 'student';

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE id = ? AND role = ?");
    $stmt->execute([$id, $role_map]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Decode JSON arrays
    $user['courses_ids'] = json_decode($user['courses'] ?? '[]', true);
    $user['resources_ids'] = json_decode($user['resources'] ?? '[]', true);

    if ($role_map === 'student') {
        // Stats for Student
        $user['enroll_count'] = count($user['courses_ids']);
        
        $progStmt = $pdo->prepare("SELECT COUNT(*) FROM progress WHERE record_type = 'lesson_progress' AND user_id = ? AND metric_value = 'completed'");
        $progStmt->execute([$id]);
        $user['completed_progress_lessons'] = $progStmt->fetchColumn();

        // Fetch enrolled courses titles
        if (!empty($user['courses_ids'])) {
            $placeholders = implode(',', array_fill(0, count($user['courses_ids']), '?'));
            $cStmt = $pdo->prepare("SELECT title FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id IN ($placeholders)");
            $cStmt->execute($user['courses_ids']);
            $user['content_courses'] = $cStmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $user['content_courses'] = [];
        }
        
    } elseif ($role_map === 'instructor') {
        // Stats for Instructor
        $user['course_count'] = count($user['courses_ids']);
        $user['resource_count'] = count($user['resources_ids']);
        $user['meta'] = json_decode($user['meta'] ?? '{}', true);

        // Build combined categories list
        $cats = [];
        if (!empty($user['meta']['category'])) $cats[] = $user['meta']['category'];
        if (!empty($user['meta']['approved_categories'])) {
            $cats = array_unique(array_merge($cats, (array)$user['meta']['approved_categories']));
        }
        $user['all_categories'] = $cats;
    }

    echo json_encode(['success' => true, 'data' => $user]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
