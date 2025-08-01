<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'check_token.php';
require 'config/db.php';



header('Content-Type: application/json');

// Ensure $userId is defined 
if (!isset($user_id) || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit;
}

// 1. Get the user info
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

$get_subscribedStmtSql = "SELECT * FROM subscriptions WHERE user_uin = ?";
$get_subscribedStmt = $conn->prepare($get_subscribedStmtSql);

$get_subscribedStmt->bind_param('i', $user['uin']);
$get_subscribedStmt->execute();
$get_subscribed_result = $get_subscribedStmt->get_result();
$subscribed_result = $get_subscribed_result->fetch_assoc();

$is_subscribed = false;
if ($get_subscribed_result->num_rows > 0 && $subscribed_result['status'] == 'subscribed') {
    $is_subscribed = true;

}







// 2. Construct and return the full response
$response = [
    'id' => $user['id'],
    'uin' => $user['uin'],
    'first_name' => $user['first_name'],
    'last_name'=> $user['last_name'],
    'passport' => $user['passport'],
    'status' => $user['status'],
    'is_subscribed' => $is_subscribed
   
];
http_response_code(200);
echo json_encode($response);
