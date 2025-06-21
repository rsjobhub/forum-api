<?php
 // cors setup

require 'config/db.php';
require 'check_token.php';

header('Content-Type: application/json');



if (!$postId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid post_id']);
    exit;
}

// Optional: Check if the post exists and belongs to the user (if needed)
$checkSql = "SELECT * FROM posts WHERE id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('i', $postId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Delete comment likes (joined via comments)
    $deleteCommentLikesSql = "
        DELETE cl FROM comment_likes cl
        JOIN comments c ON cl.id = c.id
        WHERE c.post_id = ?
    ";
    $stmt1 = $conn->prepare($deleteCommentLikesSql);
    $stmt1->bind_param('i', $postId);
    $stmt1->execute();

    // Delete comments
    $deleteCommentsSql = "DELETE FROM comments WHERE post_id = ?";
    $stmt2 = $conn->prepare($deleteCommentsSql);
    $stmt2->bind_param('i', $postId);
    $stmt2->execute();

    // Delete post reads
    $deleteReadsSql = "DELETE FROM post_reads WHERE post_id = ?";
    $stmt3 = $conn->prepare($deleteReadsSql);
    $stmt3->bind_param('i', $postId);
    $stmt3->execute();

    // Delete the post
    $deletePostSql = "DELETE FROM posts WHERE id = ?";
    $stmt4 = $conn->prepare($deletePostSql);
    $stmt4->bind_param('i', $postId);
    $stmt4->execute();

    // Commit transaction
    $conn->commit();

    http_response_code(200);
    echo json_encode(['message' => 'Post and related data deleted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Deletion failed: ' . $e->getMessage()]);
}
