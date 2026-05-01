<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW FULL COLUMNS FROM users");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if($row['Field'] == 'courses' || $row['Field'] == 'resources') {
        print_r($row);
    }
}
