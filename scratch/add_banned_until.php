<?php
require_once 'api/db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN banned_until DATETIME DEFAULT NULL");
    echo "Column banned_until added successfully.";
} catch (PDOException $e) {
    echo $e->getMessage();
}
