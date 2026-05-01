<?php
// /api/db.php
$host = 'localhost';
$dbname = 'offline_lms_db';
// Default XAMPP settings
$user = 'root'; 
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    // Set error mode to Exceptions to catch any SQL issues
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Return associative arrays by default for simpler fetching
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // In a production environment, don't output $e->getMessage() directly to the user
    die("Database connection failed: " . $e->getMessage());
}
?>
