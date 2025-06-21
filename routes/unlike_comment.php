<?php
 // cors setup

require 'check_token.php';
require 'config/db.php';

header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Validate input
function validate($data) {
    $errors = [];
    if (empty($data['comment_id'])) $errors['comment_id'] = 'Comment ID is required';
    if (empty($data['user_id'])) $errors['user_id'] = 'User ID is required';
    return $errors;
}

$errors = validate($input);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

$comment_id = (int) $input['comment_id'];
$user_id = (int) $input['user_id'];

// Delete the like entry
$deleteStmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
$deleteStmt->bind_param("ii", $comment_id, $user_id);

if (!$deleteStmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to unlike comment: ' . $deleteStmt->error]);
    exit;
}

if ($deleteStmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Like not found or already removed']);
    exit;
}

$deleteStmt->close();

http_response_code(200);
echo json_encode(['message' => 'Comment unliked successfully']);
