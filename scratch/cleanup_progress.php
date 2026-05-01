<?php
require_once __DIR__ . '/../api/db.php';
$pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
$pdo->exec("DROP TABLE IF EXISTS progress_lesson_progress, progress_lessons, progress_certificates, progress_streaks;");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
echo "Progress legacy tables dropped.\n";
?>
