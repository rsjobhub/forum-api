<?php
require 'check_token.php';
require 'config/db.php';

header('Content-Type: application/json');

// Fetch all liked comments by this user, along with comment + user details
$commentsSql = "
    SELECT 
        comment_likes.id AS like_id,
        comments.id AS comment_id,
        comments.created_at,
        comments.updated_at,
        comments.user_id,
        comment_likes.user_id AS liked_user_id,
        comments.post_id,
        comments.content,
        comments.replying_to_comment_id,
        comments.replying_to_user_id,
        comments.is_reply,
        u1.first_name AS commenter_name,
        u1.passport AS commenter_passport,
        u2.first_name AS replying_to_user_name
    FROM comment_likes
    JOIN comments ON comments.id = comment_likes.comment_id
    JOIN users u1 ON u1.id = comments.user_id
    LEFT JOIN users u2 ON u2.id = comments.replying_to_user_id
    WHERE comment_likes.user_id = ?
    ORDER BY comment_likes.created_at DESC
";

$commentsStmt = $conn->prepare($commentsSql);
$commentsStmt->bind_param('i', $userID);
$commentsStmt->execute();
$commentsResult = $commentsStmt->get_result();

$comments = [];

while ($row = $commentsResult->fetch_assoc()) {
    $comments[] = [
        'id' => $row['comment_id'],                  // ID of the comment liked
        'liked_id' => $row['like_id'],               // ID of the like itself
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'user_id' => $row['user_id'],
        'liked_user_id' => $row['liked_user_id'],
        'post_id' => $row['post_id'],
        'content' => $row['content'],
        'replying_to_comment_id' => $row['replying_to_comment_id'],
        'replying_to_user_id' => $row['replying_to_user_id'],
        'replying_to_user_name' => $row['replying_to_user_name'],  // ✅ added
        'is_reply' => (int)$row['is_reply'],
        'commenter_name' => $row['commenter_name'],
        'commenter_passport' => $row['commenter_passport']
    ];
}

http_response_code(200);
echo json_encode($comments);
