<?php
require_once __DIR__ . '/../api/db.php';
$cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . "\n";
}
?>
