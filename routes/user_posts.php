<?php
 // cors setup

require 'check_token.php';
require 'config/db.php';

header('Content-Type: application/json');

// 1. Fetch all posts by the user, including passport
$postSql = "
    SELECT 
        posts.*,
        users.first_name AS author_name,
        users.passport AS author_passport
    FROM posts
    JOIN users ON users.id = posts.user_id
    WHERE posts.user_id = ?
    ORDER BY posts.created_at DESC
";

$postStmt = $conn->prepare($postSql);
$postStmt->bind_param('i', $userID);
$postStmt->execute();
$postResult = $postStmt->get_result();

$posts = [];

while ($post = $postResult->fetch_assoc()) {
    $postId = $post['id'];

    // 2. Count comments
    $commentCountSql = "
        SELECT COUNT(*) as comment_count
        FROM comments
        WHERE post_id = ?
    ";
    $commentCountStmt = $conn->prepare($commentCountSql);
    $commentCountStmt->bind_param('i', $postId);
    $commentCountStmt->execute();
    $commentCountResult = $commentCountStmt->get_result();

    $commentCount = 0;
    if ($row = $commentCountResult->fetch_assoc()) {
        $commentCount = (int)$row['comment_count'];
    }

    // 3. Last commenter and time
    $lastCommentSql = "
        SELECT users.first_name AS commenter_name, comments.created_at AS commented_at
        FROM comments
        JOIN users ON users.id = comments.user_id
        WHERE comments.post_id = ?
        ORDER BY comments.created_at DESC
        LIMIT 1
    ";
    $lastCommentStmt = $conn->prepare($lastCommentSql);
    $lastCommentStmt->bind_param('i', $postId);
    $lastCommentStmt->execute();
    $lastCommentResult = $lastCommentStmt->get_result();

    $lastCommentedBy = null;
    $lastCommentedAt = null;
    if ($lastRow = $lastCommentResult->fetch_assoc()) {
        $lastCommentedBy = $lastRow['commenter_name'];
        $lastCommentedAt = $lastRow['commented_at'];
    }
//Check if post is read
    $readSql = "SELECT 1 FROM post_reads WHERE user_id = ? AND post_id = ? LIMIT 1";
    $readStmt = $conn->prepare($readSql);
    $readStmt->bind_param('ii', $data->id, $post['id']);
    $readStmt->execute();
    $readResult = $readStmt->get_result();
    $isRead = $readResult->num_rows > 0 ? 1 : 0;
    // 4. Structure the post data
    $posts[] = [
        'id' => $post['id'],
        'title' => $post['title'],
        'content' => $post['description'],
        'tag' => $post['tag'],
        'created_at' => $post['created_at'],
        'author' => [
            'id' => $post['user_id'],
            'name' => $post['author_name'],
            'passport' => $post['author_passport'],
        ],
        'comments_count' => $commentCount,
        'last_commented_by' => $lastCommentedBy,
        'last_commented_at' => $lastCommentedAt,
        'is_read' => $isRead,
    ];
}

http_response_code(200);
echo json_encode($posts);
