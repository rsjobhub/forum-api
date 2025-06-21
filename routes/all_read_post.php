<?php
require 'check_token.php';
require 'config/db.php';
header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() === JSON_ERROR_NONE) {
    $userId = isset($input['user_id']) ? (int) $input['user_id'] : null;
} else {
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : null;
}

// Validate input
if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid user_id']);
    exit;
}

// Step 1: Fetch all post IDs
$postQuery = "SELECT id FROM posts";
$postResult = $conn->query($postQuery);

if (!$postResult || $postResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'No posts found']);
    exit;
}

// Step 2: Insert into post_reads (bulk)
$now = date('Y-m-d H:i:s');
$values = [];
$types = '';
$params = [];

while ($row = $postResult->fetch_assoc()) {
    $values[] = "(?, ?, ?)";
    $types .= 'iis'; // user_id, post_id, read_at
    $params[] = $userId;
    $params[] = $row['id'];
    $params[] = $now;
}

$sql = "
    INSERT INTO post_reads (user_id, post_id, read_at)
    VALUES " . implode(", ", $values) . "
    ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// Bind dynamically
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'All posts marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Execution failed: ' . $stmt->error]);
}
