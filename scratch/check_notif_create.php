<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE notifications");
echo $stmt->fetch()[1];
