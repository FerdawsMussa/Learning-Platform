<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW FULL COLUMNS FROM notifications");
foreach($stmt->fetchAll() as $row) {
    if($row['Field'] == 'user_id') {
        print_r($row);
    }
}
