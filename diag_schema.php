<?php
require_once 'api/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM content");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
