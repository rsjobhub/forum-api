<?php
 // cors setup

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'check_token.php';
require 'config/db.php'; // mysqli $conn setup




header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Get raw input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Validation
function validate($data) {
    $errors = [];

    if (empty($data['title'])) {
        $errors['title'] = 'Title is required';
    } elseif (mb_strlen($data['title']) > 200) {
        $errors['title'] = 'Title must not exceed 200 characters';
    }

    if (empty($data['user_id'])) {
        $errors['user_id'] = 'User ID is required';
    }
    if (empty($data['tag'])) {
        $errors['tag'] = 'tag is required';
    }

    if (empty($data['description'])) {
        $errors['description'] = 'Description is required';
    }

    return $errors;
}

$errors = validate($input);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

// Insert post
$stmt = $conn->prepare("INSERT INTO posts (title, description, user_id, tag, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$title = $input['title'];
$description = $input['description'];
$user_id = $input['user_id'];
$tag = $input['tag'] ?? null;

date_default_timezone_set('Africa/Lagos');
$now = date('Y-m-d H:i:s');

$stmt->bind_param('ssisss', $title, $description, $user_id, $tag, $now, $now);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
    exit;
}

$post_id = $stmt->insert_id;

// ✅ Insert post content into the comments table
$commentStmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
if (!$commentStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Comment insert failed: ' . $conn->error]);
    exit;
}
$commentStmt->bind_param('iisss', $post_id, $user_id, $description, $now, $now);
if (!$commentStmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to insert post as comment: ' . $commentStmt->error]);
    exit;
}

// Fetch post with author info
$postSql = "
    SELECT 
        posts.*,
        users.id AS author_id,
        users.first_name AS author_name,
        users.passport AS author_passport
    FROM posts
    JOIN users ON users.id = posts.user_id
    WHERE posts.id = ?
";

$postStmt = $conn->prepare($postSql);
$postStmt->bind_param('i', $post_id);
$postStmt->execute();
$postResult = $postStmt->get_result();

if ($postResult->num_rows === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve inserted post']);
    exit;
}
$post = $postResult->fetch_assoc();

// Fetch comments for the post (with full comment fields)
$commentsSql = "
    SELECT 
        comments.*,
        users.first_name AS commenter_name,
        users.passport AS commenter_passport
    FROM comments
    JOIN users ON users.id = comments.user_id
    WHERE comments.post_id = ?
    ORDER BY comments.created_at ASC
";

$commentsStmt = $conn->prepare($commentsSql);
$commentsStmt->bind_param('i', $post_id);
$commentsStmt->execute();
$commentsResult = $commentsStmt->get_result();

$comments = [];
while ($row = $commentsResult->fetch_assoc()) {
    $row['commenter'] = [
        'id' => $row['user_id'],
        'first_name' => $row['commenter_name'],
        'passport' => $row['commenter_passport'],
    ];
    unset($row['commenter_name'], $row['commenter_passport']);
    $comments[] = $row;
}

// Return full response
$response = [
    'message' => 'Post saved successfully',
    'post' => [
        'id' => $post['id'],
        'title' => $post['title'],
        'description' => $post['description'],
        'tag' => $post['tag'],
        'created_at' => $post['created_at'],
        'author' => [
            'id' => $post['author_id'],
            'name' => $post['author_name'],
            'passport' => $post['author_passport'],
        ],
        'comments' => $comments,
    ]
];

http_response_code(201);
echo json_encode($response);
