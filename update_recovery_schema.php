<?php
// c:\xampp\htdocs\lastfyp\update_recovery_schema.php
require_once 'api/db.php';

try {
    $tables = ['students', 'content_creators'];
    
    foreach ($tables as $table) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `recovery_token_expires` DATETIME NULL");
    }
    
    echo "Schema updated successfully with recovery_token_expires.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist. Schema is healthy.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
