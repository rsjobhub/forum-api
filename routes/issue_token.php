<?php
 // cors setup

header('Content-Type: application/json');

include __DIR__ . '/../vendor/autoload.php';

include 'config/ssk.php'; // Load your secret key
include 'config/db.php'; // Load your secret key


use Firebase\JWT\JWT;

//Use cookie 'user_uin' as the main user identifier
if (!isset($userUin)) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized: user_uin  not set'
    ]);
    ob_end_flush();
    exit;
}


$get_id = "SELECT * FROM users WHERE uin = '$userUin'";
$get_id = $conn->query($get_id);
$row = mysqli_fetch_assoc($get_id);
$id = $row['id'];
$status = $row['status'];


$payload = [
    'iss' => 'rsjobhub.com',
    'iat' => time(),
    'exp' => time() + 3600, // 1 hour expiry
    'sub' => $userUin,
    'id' => $id
];

$jwt = JWT::encode($payload, JWT_SECRET, 'HS256');




echo json_encode([
    'token' => $jwt,
    'user_uin' => $userUin,
    'user_id'=> $id,
    'status'=> $status
 
]);

// ob_end_flush();
