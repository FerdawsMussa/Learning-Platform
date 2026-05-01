<?php
require_once 'api/db.php';
try {
    $stmt = $pdo->query("DESCRIBE users");
    $fields = $stmt->fetchAll();
    echo json_encode($fields, JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo $e->getMessage();
}
