<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW FULL COLUMNS FROM users");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Field: {$row['Field']} | Extra: {$row['Extra']}\n";
}
