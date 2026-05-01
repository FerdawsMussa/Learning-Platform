<?php
require 'api/db.php';
$stmt = $pdo->query("DESCRIBE progress");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
