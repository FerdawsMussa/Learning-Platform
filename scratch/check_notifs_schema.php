<?php
require_once '../api/db.php';
$stmt = $pdo->query("DESCRIBE admin_notifications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
