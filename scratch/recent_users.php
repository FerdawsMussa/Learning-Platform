<?php
require_once 'api/db.php';
$stmt = $pdo->query('SELECT id, full_name, email, role, is_approved FROM users ORDER BY id DESC LIMIT 5');
foreach($stmt->fetchAll() as $r) print_r($r);
