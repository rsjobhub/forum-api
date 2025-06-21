<?php
 // cors setup

require 'check_token.php';
require 'config/db.php'; // mysqli $conn setup

header('Content-Type: application/json');

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

    if (empty($data['post_id'])) {
        $errors['post_id'] = 'Post ID is required';
    }
    if (empty($data['user_id'])) {
        $errors['user_id'] = 'Your User ID is required';
    }
    if (empty($data['comment_id'])) {
        $errors['comment_id'] = 'Comment ID (being replied to) is required';
    }
    if (empty($data['content'])) {
        $errors['content'] = 'Reply content is required';
    }

    return $errors;
}

$errors = validate($input);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

$post_id = (int) $input['post_id'];
$user_id = (int) $input['user_id'];         // The person replying
$comment_id = (int) $input['comment_id'];   // The comment being replied to
$content = trim($input['content']);
$is_reply = 1;

date_default_timezone_set('Africa/Lagos');
$now = date('Y-m-d H:i:s');

// ✅ Ensure the post exists
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

// ✅ Get the user who originally posted the comment
$getCommentStmt = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
$getCommentStmt->bind_param("i", $comment_id);
$getCommentStmt->execute();
$getCommentStmt->store_result();

if ($getCommentStmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Original comment not found']);
    exit;
}

$getCommentStmt->bind_result($original_user_id);
$getCommentStmt->fetch();
$getCommentStmt->close();

// ✅ Insert reply
$replyStmt = $conn->prepare("
    INSERT INTO comments (post_id, content, user_id, replying_to_comment_id, replying_to_user_id, created_at, updated_at, is_reply)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$replyStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$replyStmt->bind_param('isiiissi', $post_id, $content, $user_id, $comment_id, $original_user_id, $now, $now, $is_reply);

if (!$replyStmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $replyStmt->error]);
    exit;
}

$inserted_id = $replyStmt->insert_id;
$replyStmt->close();

// ✅ Fetch the inserted reply
$result = $conn->query("SELECT * FROM comments WHERE id = $inserted_id");
$reply = $result->fetch_assoc();

// ✅ Get the name of the user being replied to (original commenter)
$userStmt = $conn->prepare("SELECT first_name FROM users WHERE id = ?");
$userStmt->bind_param("i", $original_user_id);
$userStmt->execute();
$userStmt->bind_result($replying_to_user_name);
$userStmt->fetch();
$userStmt->close();

// ✅ Add replying_to_user_name into the reply array
$reply['replying_to_user_name'] = $replying_to_user_name;

http_response_code(201);
echo json_encode([
    'message' => 'Reply posted successfully',
    'reply' => $reply
]);


