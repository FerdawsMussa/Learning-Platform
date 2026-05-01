<?php
require_once 'api/db.php';
$stmt = $pdo->query("DESCRIBE admins");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
