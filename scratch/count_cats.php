<?php
require_once 'api/db.php';
echo "Categories: " . $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
