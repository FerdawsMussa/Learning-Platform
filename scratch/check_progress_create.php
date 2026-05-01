<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE progress");
echo $stmt->fetch()[1];
