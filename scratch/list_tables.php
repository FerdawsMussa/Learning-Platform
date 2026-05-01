<?php
require_once 'api/db.php';
foreach($pdo->query('SHOW TABLES')->fetchAll() as $r) echo $r[0] . "\n";
