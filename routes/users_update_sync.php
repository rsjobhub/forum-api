<?php
require 'config/db.php'; // VPS DB connection

header('Content-Type: application/json');

// Auth check (optional)
$headers = getallheaders();
if (!isset($headers['Authorization']) || $headers['Authorization'] !== 'Bearer YOUR_SECRET_KEY') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Parse input
$data = json_decode(file_get_contents('php://input'), true);
$uin = $data['uin'] ?? null;
$first_name = $data['first_name'] ?? null;
$last_name = $data['last_name'] ?? null;
$passport = $data['passport'] ?? null;

if (!$uin || !$first_name || !$last_name) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE users
    SET first_name = ?, last_name = ?, passport = ?
    WHERE uin = ?
");

$stmt->bind_param("ssss", $first_name, $last_name, $passport, $uin);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed', 'details' => $stmt->error]);
}

$stmt->close();
