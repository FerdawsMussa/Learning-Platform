<?php
// c:\xampp\htdocs\lastfyp\api\delete_course.php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../dashboard.php");
    exit;
}

if (isset($_GET['id'])) {
    $course_id = intval($_GET['id']);
    $creator_id = $_SESSION['user_id'];

    try {
        // Soft-Delete: Instructor marks course as deleted
        $stmt = $pdo->prepare("UPDATE content SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$course_id, $creator_id]);

        if ($stmt->rowCount() > 0) {
            // Log the deletion for admin visibility & restoration window
            $logStmt = $pdo->prepare("INSERT INTO system_logs (event_type, user_id, user_role, description, target_id, target_type) VALUES ('delete_course', ?, 'instructor', ?, ?, 'course')");
            $logStmt->execute([$creator_id, "Instructor deleted course ID: $course_id", $course_id]);
            
            header("Location: ../instructor_dashboard.php?success=course_deleted");
        } else {
            // Either the course doesn't exist, is already deleted, or it doesn't belong to them
            header("Location: ../instructor_dashboard.php?error=unauthorized_delete");
        }
    } catch (PDOException $e) {
        header("Location: ../instructor_dashboard.php?error=sqlerror");
    }
} else {
    header("Location: ../instructor_dashboard.php");
}
exit;
