<?php
 // cors setup

require 'check_token.php';
require 'config/db.php';

header('Content-Type: application/json');

// Fetch all comments made by this user, including name and passport
$commentsSql = "
    SELECT 
        comments.*,
        u1.first_name AS commenter_name,
        u1.passport AS commenter_passport,
        u2.first_name AS replying_to_user_name
    FROM comments
    JOIN users u1 ON u1.id = comments.user_id
    LEFT JOIN users u2 ON u2.id = comments.replying_to_user_id
    WHERE comments.user_id = ?
    ORDER BY comments.created_at DESC
";


$commentsStmt = $conn->prepare($commentsSql);
$commentsStmt->bind_param('i', $userID);
$commentsStmt->execute();
$commentsResult = $commentsStmt->get_result();

$comments = [];

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
        'commenter_id' => (string)$row['user_id'],
    ];
}


http_response_code(200);
echo json_encode($comments);
