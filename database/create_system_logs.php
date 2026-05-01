<?php
// c:\xampp\htdocs\lastfyp\database\create_system_logs.php
require_once dirname(__DIR__) . '/api/db.php';

echo "Initializing System Logs Table...\n";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_type` VARCHAR(50) NOT NULL COMMENT 'login, sync, upload, delete, approve',
        `description` TEXT NOT NULL,
        `user_role` VARCHAR(50) DEFAULT 'system',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✔ Table 'system_logs' is ready.\n";

    // Add some initial placeholder logs
    $pdo->exec("INSERT INTO `system_logs` (`event_type`, `description`, `user_role`) VALUES 
        ('system', 'Logging system initialized', 'admin'),
        ('system', 'Database schema updated to v1.2', 'admin')
    ");
    echo "✔ Placeholder logs added.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
