<?php
require_once 'api/db.php';
try {
    $stmt = $pdo->query("DESCRIBE `admins` ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo "Admins table error: " . $e->getMessage();
}
?>
