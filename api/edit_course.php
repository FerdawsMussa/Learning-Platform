<?php
// c:\xampp\htdocs\lastfyp\api\edit_course.php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creator_id = $_SESSION['user_id'];
    $course_id = intval($_POST['course_id'] ?? 0);
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $level = trim($_POST['level'] ?? '');

    if (empty($course_id) || empty($title) || empty($description)) {
        header("Location: ../instructor_dashboard.php?error=emptyfields");
        exit;
    }

    try {
        // Enforce: Instructor must NOT edit/delete other instructors' content_courses
        $stmt = $pdo->prepare("UPDATE content SET title = ?, description = ?, category = ?, level = ? WHERE id = ? AND creator_id = ?");
        $stmt->execute([$title, $description, $category, $level, $course_id, $creator_id]);

        if ($stmt->rowCount() > 0) {
            header("Location: ../instructor_dashboard.php?success=course_updated");
        } else {
            // Course doesn't exist, isn't theirs, or no new changes were made
            header("Location: ../instructor_dashboard.php?success=no_changes_or_unauthorized");
        }
    } catch (PDOException $e) {
        header("Location: ../instructor_dashboard.php?error=sqlerror");
    }
} else {
    header("Location: ../instructor_dashboard.php");
}
exit;
