<?php
require_once 'api/db.php';

try {
    // Array of tables to alter
    $tables = ['students', 'content_creators', 'admins'];
    
    foreach ($tables as $table) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `login_attempts` INT DEFAULT 0");
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `locked_until` DATETIME NULL");
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `recovery_token` VARCHAR(255) NULL");
    }
    
    echo "Schema updated successfully for lockouts and tokens.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist. Schema is healthy.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
