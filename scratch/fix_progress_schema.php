<?php
include 'api/db.php';
try {
    // Add generic_value if missing
    $pdo->exec("ALTER TABLE progress ADD COLUMN IF NOT EXISTS generic_value VARCHAR(255) NULL AFTER title");
    // If it was named content_type, rename it or keep both? 
    // To be safe, I'll ensure generic_value exists.
    
    // Also check if content_text should be metric_value? 
    // No, metric_value already exists according to DESCRIBE.
    
    echo "Schema updated successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
