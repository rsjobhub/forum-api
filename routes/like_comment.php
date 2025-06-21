<?php
 // cors setup

require 'check_token.php';

require 'config/db.php';

header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    if (empty($data['user_id'])) $errors['user_id'] = 'Your User ID is required';
    return $errors;
}

$errors = validate($input);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

$comment_id = (int) $input['comment_id'];
$new_user_id = (int) $input['user_id'];

date_default_timezone_set('Africa/Lagos');
$now = date('Y-m-d H:i:s');

// ✅ Fetch the original comment
$getCommentStmt = $conn->prepare("
    SELECT post_id, content, replying_to_comment_id, replying_to_user_id, is_reply 
    FROM comments 
    WHERE id = ?
");
$getCommentStmt->bind_param("i", $comment_id);
$getCommentStmt->execute();
$getCommentStmt->bind_result($post_id, $content, $replying_to_comment_id, $replying_to_user_id, $is_reply);

if (!$getCommentStmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Original comment not found']);
    exit;
}
$getCommentStmt->close();

// ✅ Insert into comment_likes using current timestamp
$insertStmt = $conn->prepare("
    INSERT INTO comment_likes 
    (post_id, content, user_id, replying_to_comment_id, replying_to_user_id, created_at, updated_at, is_reply,comment_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)
");

if (!$insertStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$insertStmt->bind_param(
    'isiiissii',
    $post_id,
    $content,
    $new_user_id,
    $replying_to_comment_id,
    $replying_to_user_id,
    $now,   // ✅ current timestamp
    $now,   // ✅ current timestamp
    $is_reply,
    $comment_id
);

if (!$insertStmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Insert failed: ' . $insertStmt->error]);
    exit;
}

$inserted_id = $insertStmt->insert_id;
$insertStmt->close();

// ✅ Return the inserted like
$result = $conn->query("SELECT * FROM comment_likes WHERE id = $inserted_id");
$like = $result->fetch_assoc();

http_response_code(201);
echo json_encode([
    'message' => 'Comment liked successfully',
    'like' => $like
]);
