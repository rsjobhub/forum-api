<?php
 // cors setup

require 'check_token.php';
require 'config/db.php'; // mysqli $conn setup



header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    header('Allow: POST');
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Get raw input (assuming JSON POST body)
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // Try to get input from $_POST as fallback
    $input = $_POST;
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Validation function
function validate($data) {
    $errors = [];

    if (empty($data['post_id'])) {
        $errors['post_id'] = 'Post ID is required';
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
    http_response_code(422); // Unprocessable Entity
    echo json_encode(['errors' => $errors]);
    exit;
}

$post_id = (int) $input['post_id'];
$content = $input['content'];
$user_id = (int) $input['user_id'];

date_default_timezone_set('Africa/Lagos');
$now = date('Y-m-d H:i:s');

// ✅ Check if the post actually exists
$checkPostStmt = $conn->prepare("SELECT id FROM posts WHERE id = ?");
$checkPostStmt->bind_param("i", $post_id);
$checkPostStmt->execute();
$checkPostStmt->store_result();

if ($checkPostStmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit;
}
$checkPostStmt->close();

// ✅ Prepare and bind the insert statement
$stmt = $conn->prepare("INSERT INTO comments (post_id, content, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('isiss', $post_id, $content, $user_id, $now, $now);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
    exit;
}

// ✅ Fetch and return the inserted comment
$comment_id = $stmt->insert_id;
$result = $conn->query("SELECT * FROM comments WHERE id = $comment_id");
$comment = $result->fetch_assoc();

http_response_code(201);
echo json_encode([
    'message' => 'Comment saved successfully',
    'comment' => $comment
]);
