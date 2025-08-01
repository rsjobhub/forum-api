<?php
require 'check_token.php';
require 'config/db.php'; // mysqli $conn setup

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

function validate($data) {
    $errors = [];

    if (empty($data['comment_id'])) {
        $errors['comment_id'] = 'Comment ID is required';
    }

    if (empty($data['user_id'])) {
        $errors['user_id'] = 'User ID is required';
    }

    if (empty($data['content'])) {
        $errors['content'] = 'Content is required';
    }

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
$content = $input['content'];

date_default_timezone_set('Africa/Lagos');
$now = date('Y-m-d H:i:s');

// ✅ Check user status
$userStmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$userData = $userResult->fetch_assoc();
$isAdmin = ($userData['status'] === 'admin');

// ✅ Check if comment exists and belongs to user or user is admin
if ($isAdmin) {
    $checkStmt = $conn->prepare("SELECT * FROM comments WHERE id = ?");
    $checkStmt->bind_param("i", $comment_id);
} else {
    $checkStmt = $conn->prepare("SELECT * FROM comments WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $comment_id, $user_id);
}
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not allowed to edit this comment']);
    exit;
}

// ✅ Proceed to update the comment
$updateStmt = $conn->prepare("UPDATE comments SET content = ?, updated_at = ? WHERE id = ?");
$updateStmt->bind_param("ssi", $content, $now, $comment_id);

if (!$updateStmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $updateStmt->error]);
    exit;
}

// ✅ Fetch and return the updated comment
$fetchStmt = $conn->prepare("SELECT * FROM comments WHERE id = ?");
$fetchStmt->bind_param("i", $comment_id);
$fetchStmt->execute();
$updatedResult = $fetchStmt->get_result();
$updatedComment = $updatedResult->fetch_assoc();

http_response_code(200);
echo json_encode([
    'message' => 'Comment updated successfully',
    'comment' => $updatedComment
]);
