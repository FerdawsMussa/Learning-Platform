<?php
require_once 'api/db.php';
$res = $pdo->query('SELECT DISTINCT role FROM users')->fetchAll(PDO::FETCH_COLUMN);
print_r($res);
