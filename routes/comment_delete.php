<?php

require 'config/db.php';
require 'check_token.php';

header('Content-Type: application/json');

// Get and validate input

if (!$commentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid comment_id']);
    exit;
}

// Optional: Check if the comment exists
$checkSql = "SELECT * FROM comments WHERE id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('i', $commentId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Comment not found']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // 1. Delete related likes
    $deleteLikesSql = "DELETE FROM comment_likes WHERE comment_id = ?";
    $stmt1 = $conn->prepare($deleteLikesSql);
    $stmt1->bind_param('i', $commentId);
    $stmt1->execute();

    // 2. Delete the comment itself
    $deleteCommentSql = "DELETE FROM comments WHERE id = ?";
    $stmt2 = $conn->prepare($deleteCommentSql);
    $stmt2->bind_param('i', $commentId);
    $stmt2->execute();

    // Commit transaction
    $conn->commit();

    http_response_code(200);
    echo json_encode(['message' => 'Comment and its likes deleted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Deletion failed: ' . $e->getMessage()]);
}
