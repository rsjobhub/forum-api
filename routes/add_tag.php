<?php
 // cors setup

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// require 'check_token.php';
require 'config/db.php'; // mysqli $conn setup


header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Get raw input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Validation
function validate($data) {
    $errors = [];

   

    if (empty($data['name'])) {
        $errors['name'] = ' tag is required';
    }


    return $errors;
}

$errors = validate($input);
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

// Insert post
$stmt = $conn->prepare("INSERT INTO tags (name) VALUES (?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}


$tag = $input['name'];



$stmt->bind_param('s', $tag);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
    exit;
}

$tag_id = $stmt->insert_id;



http_response_code(201);
echo json_encode(['message' => 'Tag saved successfully'],
);
