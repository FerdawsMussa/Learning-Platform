<?php
require_once dirname(__DIR__) . '/api/db.php';
$stmt = $pdo->query("DESCRIBE admin_notifications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
