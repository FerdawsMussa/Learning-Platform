<?php
require_once 'api/db.php';
try {
    // Approve all existing courses so nothing breaks
    $affected = $pdo->exec("UPDATE content SET is_approved = 1 WHERE record_type IN ('course','resource') AND is_approved = 0");
    echo "Done: $affected existing courses approved. Column already existed.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
