<?php
require_once 'api/db.php';
$table = 'content_creators_old_bak';
$stmt = $pdo->query("DESCRIBE $table");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
