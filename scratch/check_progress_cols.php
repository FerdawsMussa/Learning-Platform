<?php
require_once 'api/db.php';
try {
    $stmt = $pdo->query('DESCRIBE progress');
    foreach($stmt->fetchAll() as $row) echo $row['Field'] . "\n";
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
