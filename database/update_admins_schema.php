<?php
// c:\xampp\htdocs\lastfyp\database\update_admins_schema.php
require_once dirname(__DIR__) . '/api/db.php';

echo "Updating Admins Table Schema...\n";

try {
    // 1. Add email column
    $pdo->exec("ALTER TABLE `admins` ADD COLUMN IF NOT EXISTS `email` VARCHAR(100) NOT NULL UNIQUE AFTER `username` ");
    echo "✔ Added email column.\n";

    // 2. Add role column to admins
    $pdo->exec("ALTER TABLE `admins` ADD COLUMN IF NOT EXISTS `role` ENUM('main', 'secondary') DEFAULT 'secondary' AFTER `password` ");
    echo "✔ Added role column to admins.\n";

    // 3. Add social links to content_creators
    $pdo->exec("ALTER TABLE `content_creators` ADD COLUMN IF NOT EXISTS `linkedin_link` VARCHAR(255) AFTER `bio` ");
    $pdo->exec("ALTER TABLE `content_creators` ADD COLUMN IF NOT EXISTS `github_link` VARCHAR(255) AFTER `linkedin_link` ");
    $pdo->exec("ALTER TABLE `content_creators` ADD COLUMN IF NOT EXISTS `portfolio_link` VARCHAR(255) AFTER `github_link` ");
    echo "✔ Added social links to content_creators.\n";

    echo "\n✔ Admin Schema Update Complete!\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
