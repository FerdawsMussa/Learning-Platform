<?php
require 'api/db.php';
$res = $pdo->query("SELECT email, full_name, role FROM users WHERE role = 'instructor' ORDER BY id DESC LIMIT 1")->fetch();
print_r($res);
