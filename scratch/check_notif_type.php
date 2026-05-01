<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'type'");
$row = $stmt->fetch();
print_r($row);
