<?php
// ─── api/sync_progress.php ────────────────────────────────────────────────────
// Receives queued progress + feedback from the offline manager and syncs them.
require_once 'session_helper.php';
start_role_session('student');
require_once 'db.php';
require_once 'certificate_engine.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || !is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$progress_items = $body['progress'] ?? [];
$feedback_items = $body['feedback'] ?? [];
$synced_progress = 0;
$synced_feedback = 0;
$errors = [];
$affected_courses = [];

// ─── SYNC PROGRESS ─────────────────────────────────────────────────────────────
foreach ($progress_items as $item) {
    $lesson_id   = (int)($item['lesson_id'] ?? 0);
    $course_id   = (int)($item['course_id'] ?? 0);
    $raw_date    = $item['completed_at'] ?? null;

    // Sanitise the ISO date from JS
    if ($raw_date && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $raw_date)) {
        $completed_at = date('Y-m-d H:i:s', strtotime($raw_date));
    } else {
        $completed_at = date('Y-m-d H:i:s');
    }

    if (!$lesson_id) continue;

    try {
        // UPSERT: try update first, then insert
        $upd = $pdo->prepare("
            UPDATE progress
            SET metric_value = 'completed', last_activity_at = ?
            WHERE record_type = 'lesson_progress' AND user_id = ? AND item_id = ?
        ");
        $upd->execute([$completed_at, $user_id, $lesson_id]);

        if ($upd->rowCount() === 0) {
            $ins = $pdo->prepare("
                INSERT INTO progress (record_type, user_id, item_id, metric_value, last_activity_at)
                VALUES ('lesson_progress', ?, ?, 'completed', ?)
            ");
            $ins->execute([$user_id, $lesson_id, $completed_at]);
        }

        $synced_progress++;
        if ($course_id && !in_array($course_id, $affected_courses)) {
            $affected_courses[] = $course_id;
        }
    } catch (Exception $e) {
        $errors[] = 'Progress sync error (lesson ' . $lesson_id . '): ' . $e->getMessage();
    }
}

// ─── AUTO-ISSUE CERTIFICATES ─────────────────────────────────────────────────
foreach ($affected_courses as $cid) {
    try {
        issue_course_certificate($user_id, $cid);
    } catch (Exception $e) {
        $errors[] = 'Certificate check error (course ' . $cid . '): ' . $e->getMessage();
    }
}

// ─── SYNC FEEDBACK ─────────────────────────────────────────────────────────────
foreach ($feedback_items as $item) {
    $course_id = (int)($item['course_id'] ?? 0);
    $rating    = (int)($item['rating'] ?? 0);
    $comment   = trim($item['comment'] ?? '');

    if (!$course_id || $rating < 1 || $rating > 5) continue;

    try {
        // Skip if feedback already exists
        $check = $pdo->prepare("
            SELECT id FROM notifications
            WHERE type = 'feedback'
              AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.course_id')) = ?
              AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.student_id')) = ?
        ");
        $check->execute([$course_id, $user_id]);
        if ($check->fetch()) continue;

        // Find the instructor who owns this course
        $stmt = $pdo->prepare("
            INSERT INTO notifications (type, user_id, target_role, message, meta)
            VALUES (
                'feedback',
                (SELECT user_id FROM content WHERE id = ? AND record_type = 'course' LIMIT 1),
                'instructor',
                ?,
                JSON_OBJECT('course_id', ?, 'student_id', ?, 'rating', ?, 'reply', null)
            )
        ");
        $stmt->execute([$course_id, $comment, $course_id, $user_id, $rating]);
        $synced_feedback++;
    } catch (Exception $e) {
        $errors[] = 'Feedback sync error (course ' . $course_id . '): ' . $e->getMessage();
    }
}

echo json_encode([
    'success'          => true,
    'synced_progress'  => $synced_progress,
    'synced_feedback'  => $synced_feedback,
    'errors'           => $errors
]);
?>
