<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $type = $data['type'] ?? ''; 
    $content_id = $data['content_id'] ?? '';
    $user_id = $_SESSION['user_id'];

    if (empty($content_id) || empty($type)) {
        echo json_encode(["status" => "error", "message" => "Missing data"]);
        exit;
    }

    try {
        if ($type === 'view') {
            $stmt = $pdo->prepare("INSERT INTO views (user_id, content_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $content_id]);
        } elseif ($type === 'download') {
            $stmt = $pdo->prepare("INSERT INTO downloads (user_id, content_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $content_id]);
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid type"]);
            exit;
        }

        echo json_encode(["status" => "success"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
    }
}
