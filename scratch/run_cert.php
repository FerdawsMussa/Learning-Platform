<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/certificate_engine.php';

try {
    $cert = issue_course_certificate(20, 15);
    echo "Successfully generated: " . $cert;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
}
?>
