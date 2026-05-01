<?php
require_once 'api/db.php';
$tables = ['content_creators', 'content_courses', 'progress_lessons', 'content_course_feedback', 'notification_student'];
$info = [];
foreach ($tables as $t) {
    try {
        $stmt = $pdo->query("DESCRIBE `$t` ");
        $info[$t] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $info[$t] = "Not found";
    }
}
echo json_encode($info, JSON_PRETTY_PRINT);
?>
