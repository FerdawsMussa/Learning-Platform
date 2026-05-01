<?php
require_once 'api/db.php';
try {
    $stmt = $pdo->query("DESCRIBE content_courses");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
