<?php
// Database credentials
$host = 'localhost';
$user = 'phpadmin';
$password = 'StrongPassword123!';
$dbname = 'forum';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'status' => 500,
        'error' => 'Database connection failed: ' . $conn->connect_error
    ]));
}


