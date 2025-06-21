<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'check_token.php';
require 'config/db.php';



header('Content-Type: application/json');

// Ensure $postId is defined before this file is included
if (!isset($user_id) || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit;
}

// 1. Get the post with author info
$user_sql = "
   SELECT * FROM users WHERE id = ?
";

$userStmt = $conn->prepare($user_sql);
$userStmt->bind_param('i', $user_id);
$userStmt->execute();
$result = $userStmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();




// 2. Construct and return the full response
$response = [
    'id' => $user['id'],
    'uin' => $user['uin'],
    'first_name' => $user['first_name'],
    'last_name'=> $user['last_name'],
    'passport' => $user['passport'],
   
];
http_response_code(200);
echo json_encode($response);
