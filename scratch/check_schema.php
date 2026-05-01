<?php
include 'api/db.php';
try {
    $stmt = $pdo->query("DESCRIBE progress");
    echo "COLUMNS IN progress:\n";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . "\n";
    }
    echo "\nCOLUMNS IN content:\n";
    $stmt = $pdo->query("DESCRIBE content");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
