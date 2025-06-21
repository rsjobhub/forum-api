<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'check_token.php';
require 'config/db.php';

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
function validateUpdate($data) {
    $errors = [];

    if (empty($data['post_id'])) {
        $errors['post_id'] = 'Post ID is required';
    }

    if (empty($data['title'])) {
        $errors['title'] = 'Title is required';
    } elseif (mb_strlen($data['title']) > 200) {
        $errors['title'] = 'Title must not exceed 200 characters';
    }

    if (empty($data['user_id'])) {
        $errors['user_id'] = 'User ID is required';
    }

    return $errors;
}

$errors = validateUpdate($input);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

// Extract fields
$post_id = intval($input['post_id']);
$title = $input['title'];
$user_id = intval($input['user_id']);

date_default_timezone_set('Africa/Lagos');
$now = date('Y-m-d H:i:s');

// Check if post exists and belongs to user
$checkSql = "SELECT * FROM posts WHERE id = ? AND user_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('ii', $post_id, $user_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found or does not belong to user']);
    exit;
}

// Start update transaction
$conn->begin_transaction();

try {
    // Update post title only
    $updatePostSql = "
        UPDATE posts
        SET title = ?, updated_at = ?
        WHERE id = ?
    ";
    $stmt = $conn->prepare($updatePostSql);
    $stmt->bind_param('ssi', $title, $now, $post_id);
    $stmt->execute();

    $conn->commit();

    // Fetch updated post info
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
    $post = $postResult->fetch_assoc();

    // Fetch comments
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

    // Final response
    $response = [
        'message' => 'Post title updated successfully',
        'post' => [
            'id' => $post['id'],
            'title' => $post['title'],
            'tag' => $post['tag'],
            'created_at' => $post['created_at'],
            'updated_at' => $post['updated_at'],
            'author' => [
                'id' => $post['author_id'],
                'name' => $post['author_name'],
                'passport' => $post['author_passport'],
            ],
            'comments' => $comments,
        ]
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
}
