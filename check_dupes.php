<?php
require_once 'api/db.php';
echo "--- ALL USERS ---\n";
$all = $pdo->query("SELECT id, full_name, email, role FROM users ORDER BY email")->fetchAll();
foreach ($all as $u) echo "ID: {$u['id']} | Name: {$u['full_name']} | Email: {$u['email']} | Role: {$u['role']}\n";

echo "\n--- DEDUPLICATED USERS (STUDENTS) ---\n";
$dedup = $pdo->query("
    SELECT u.id, u.full_name, u.email 
    FROM users u
    INNER JOIN (SELECT MIN(id) as min_id FROM users WHERE role = 'student' GROUP BY TRIM(LOWER(email))) d
    ON u.id = d.min_id
")->fetchAll();
foreach ($dedup as $u) echo "ID: {$u['id']} | Name: {$u['full_name']} | Email: {$u['email']}\n";
?>
