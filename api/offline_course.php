<?php
require_once 'session_helper.php';
require_once 'db.php';
header('Content-Type: application/json');

start_role_session('student');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = (int)($_GET['id'] ?? 0);

if (!$course_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing course ID']);
    exit;
}

// Verify enrollment
$enroll = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$enroll->execute([$user_id, $course_id]);
if (!$enroll->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Not enrolled in this course']);
    exit;
}

// Fetch course info
$course_stmt = $pdo->prepare("SELECT id, title, description, category FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ? AND is_deleted = 0");
$course_stmt->execute([$course_id]);
$course = $course_stmt->fetch();

if (!$course) {
    http_response_code(404);
    echo json_encode(['error' => 'Course not found']);
    exit;
}

// Fetch all progress_lessons
$progress_lessons_stmt = $pdo->prepare("SELECT id, title, type, file_path, content, order_number FROM progress_lessons WHERE course_id = ? ORDER BY order_number ASC");
$progress_lessons_stmt->execute([$course_id]);
$progress_lessons = $progress_lessons_stmt->fetchAll();

// Build full file URLs for caching
$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$base_path = dirname(dirname($_SERVER['SCRIPT_NAME'])); // e.g. /lastfyp
if ($base_path == '\\' || $base_path == '/') $base_path = '';

$file_urls = [];
foreach ($progress_lessons as &$lesson) {
    if (!empty($lesson['file_path'])) {
        $full_url = $base_url . $base_path . '/' . ltrim($lesson['file_path'], '/');
        $lesson['file_url'] = $full_url;
        $file_urls[] = $full_url;
    } else {
        $lesson['file_url'] = null;
    }
}

echo json_encode([
    'success' => true,
    'course'  => $course,
    'progress_lessons' => $progress_lessons,
    'file_urls' => $file_urls,
    'cached_at' => date('c')
]);
