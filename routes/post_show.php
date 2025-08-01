<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'check_token.php';
require 'config/db.php';

header('Content-Type: application/json');

// Validate input
if (!$postId || !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid post_id or user_id']);
    exit;
}

// 1. Fetch post + author info
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
$postStmt->bind_param('i', $postId);
$postStmt->execute();
$postResult = $postStmt->get_result();

if ($postResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit;
}

$post = $postResult->fetch_assoc();

// 2. Fetch all comments + commenter info + replied-to user
$commentsSql = "
    SELECT 
        comments.*,
        u1.first_name AS commenter_name,
        u1.passport AS commenter_passport,
        u1.status AS commenter_status,
        u2.first_name AS replying_to_user_name
    FROM comments
    JOIN users u1 ON u1.id = comments.user_id
    LEFT JOIN users u2 ON u2.id = comments.replying_to_user_id
    WHERE comments.post_id = ?
    ORDER BY comments.created_at ASC
";
$commentsStmt = $conn->prepare($commentsSql);
$commentsStmt->bind_param('i', $postId);
$commentsStmt->execute();
$commentsResult = $commentsStmt->get_result();

$comments = [];
$lastCommentedBy = null;

// 3. Record post read
$readSql = "
    INSERT INTO post_reads (user_id, post_id, read_at)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE read_at = NOW()
";
$readStmt = $conn->prepare($readSql);
$readStmt->bind_param('ii', $userId, $postId);
$readStmt->execute();

while ($row = $commentsResult->fetch_assoc()) {
    $comments[] = [
        'id' => $row['id'],
        'content' => $row['content'],
        'post_id' => $row['post_id'],
        'user_id' => $row['user_id'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'is_reply' => (int)$row['is_reply'],
        'replying_to_comment_id' => $row['replying_to_comment_id'],
        'replying_to_user_id' => $row['replying_to_user_id'],
        'replying_to_user_name' => $row['replying_to_user_name'],
        'commenter_name' => $row['commenter_name'],
        'commenter_passport' => $row['commenter_passport'],
        'commenter_status' => $row['commenter_status'],
        'commenter_id' => (string)$row['user_id'],
    ];

    $lastCommentedBy = $row['commenter_name'];
}

// 4. Final response
$commentsCount = count($comments);
$response = [
    'id' => $post['id'],
    'title' => $post['title'],
    'description' => $post['description'],
    'tag' => $post['tag'],
    'created_at' => $post['created_at'],
    'updated_at' => $post['updated_at'],
    'comments_count' => $commentsCount,
    'last_commented_by' => $lastCommentedBy,
    'comments' => $comments,
    'author' => [
        'id' => $post['author_id'],
        'name' => $post['author_name'],
        'passport' => $post['author_passport'],
    ]
];

http_response_code(200);
echo json_encode($response);
