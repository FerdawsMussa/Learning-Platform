<?php
require_once 'api/db.php';
$stmt = $pdo->query("DESCRIBE progress_lessons");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) { echo $c['Field'] . " | " . $c['Type'] . "\n"; }
