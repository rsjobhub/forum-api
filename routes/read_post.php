<?php
require 'check_token.php';

require 'config/db.php';

header('Content-Type: application/json');

// Get input from JSON or POST
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() === JSON_ERROR_NONE) {
    $postId = isset($input['post_id']) ? (int) $input['post_id'] : null;
    $userId = isset($input['user_id']) ? (int) $input['user_id'] : null;
} else {
    $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : null;
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : null;
}

// Validate input
if (!$postId || !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid post_id or user_id']);
    exit;
}

// Mark post as read
$readSql = "
    INSERT INTO post_reads (user_id, post_id, read_at)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE read_at = NOW()
";
$readStmt = $conn->prepare($readSql);
if (!$readStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$readStmt->bind_param('ii', $userId, $postId);
if ($readStmt->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'Post has been marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark post as read']);
}
