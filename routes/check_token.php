<?php
 // cors setup

include __DIR__ . '/../vendor/autoload.php';

include 'config/ssk.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function checkAuthorization() {
    $headers = apache_request_headers();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header missing']);
        exit;
    }

    $authHeader = $headers['Authorization'];

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(400);
        echo json_encode(['error' => 'Malformed Authorization header']);
        exit;
    }

    $jwt = $matches[1];
    $secretKey =JWT_SECRET;

    try {
        $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

        // You can return the decoded payload if you want to use it in your route
        return $decoded;

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token', 'details' => $e->getMessage()]);
        exit;
    }
}


$data = checkAuthorization();
