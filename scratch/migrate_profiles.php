<?php
// scratch/migrate_profiles.php
require_once __DIR__ . '/../api/db.php';

$tables = ['students', 'content_creators', 'admins'];

foreach ($tables as $table) {
    echo "Processing table: $table... ";
    try {
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'profile_pic'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
            echo "Added profile_pic column.\n";
        } else {
            echo "Column already exists.\n";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Create uploads directory if not exists
$dir = __DIR__ . '/../assets/uploads/profile_pics';
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
    echo "Created directory: $dir\n";
}
echo "Done.\n";
?>
