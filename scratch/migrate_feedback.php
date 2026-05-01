<?php
// c:\xampp\htdocs\lastfyp\scratch\migrate_feedback.php
require_once __DIR__ . '/../api/db.php';

try {
    echo "1. Altering notifications table schema...\n";
    // Check if columns exist first to prevent crashes on re-run
    $cols = $pdo->query("SHOW COLUMNS FROM `notifications`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('type', $cols)) {
        $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `type` ENUM('alert', 'feedback') NOT NULL DEFAULT 'alert' AFTER `id`");
    }
    if (!in_array('meta', $cols)) {
        $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `meta` JSON NULL AFTER `message`");
    }

    echo "2. Fetching existing feedback from content table...\n";
    $stmt = $pdo->query("
        SELECT f.id, f.parent_id AS course_id, f.user_id AS student_id, f.generic_value AS rating, f.description AS comment, f.meta_text AS reply, f.created_at, c.user_id AS instructor_id
        FROM content f
        JOIN content c ON f.parent_id = c.id
        WHERE f.record_type = 'feedback'
    ");
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "3. Migrating " . count($feedbacks) . " records to notifications...\n";
    $pdo->beginTransaction();
    $insertReq = $pdo->prepare("
        INSERT INTO notifications (type, user_id, target_role, message, meta, is_read, created_at)
        VALUES ('feedback', ?, 'instructor', ?, ?, 0, ?)
    ");
    
    foreach ($feedbacks as $f) {
        $meta = json_encode([
            'course_id' => $f['course_id'],
            'student_id' => $f['student_id'],
            'rating' => $f['rating'],
            'reply' => $f['reply']
        ]);
        // Set message to the comment itself instead of string formatting, as Dashboard renders description directly.
        $message = $f['comment'] ?: 'No comment provided.';
        $insertReq->execute([$f['instructor_id'], $message, $meta, $f['created_at']]);
    }
    
    echo "4. Removing legacy feedback from content table...\n";
    $pdo->exec("DELETE FROM content WHERE record_type = 'feedback'");
    $pdo->commit();

    echo "Migration Complete.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
