<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW FULL COLUMNS FROM users");
foreach($stmt->fetchAll() as $row) {
    if($row['Field'] == 'is_approved') {
        print_r($row);
    }
}
