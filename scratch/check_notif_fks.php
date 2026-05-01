<?php
require_once 'api/db.php';
$stmt = $pdo->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = 'offline_lms_db' AND TABLE_NAME = 'notifications'");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    print_r($row);
}
