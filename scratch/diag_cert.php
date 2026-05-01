<?php
require_once __DIR__ . '/../api/db.php';
$course_id = 15;
$student_id = 20;

$s_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
$s_stmt->execute([$student_id]);
$student = $s_stmt->fetch();

$c_stmt = $pdo->prepare("SELECT title FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at FROM content WHERE record_type = 'course') content_courses WHERE id = ?");
$c_stmt->execute([$course_id]);
$course = $c_stmt->fetch();

print_r($student);
print_r($course);
?>
