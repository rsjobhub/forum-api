<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config/db.php';
header('Content-Type: application/json');

// Parse JSON input
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['users']) || !is_array($data['users'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing users array']);
    exit;
}

// Prepare user insert statement
$userStmt = $conn->prepare("
    INSERT INTO users (
        uin, first_name, last_name, email, password,
        coupon, status, date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name),
        last_name = VALUES(last_name),
        email = VALUES(email),
        password = VALUES(password),
        coupon = VALUES(coupon),
        status = VALUES(status),
        date = VALUES(date)
");

// Prepare candidate profile insert (or update) statement
$profileStmt = $conn->prepare("
    INSERT INTO candidate_profile (uin, country)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE country = VALUES(country)
");

if (!$userStmt || !$profileStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed', 'details' => $conn->error]);
    exit;
}

foreach ($data['users'] as $user) {
    // Extract user fields
    $uin = $user['uin'] ?? '';
    $first_name = $user['first_name'] ?? '';
    $last_name = $user['last_name'] ?? '';
    $email = $user['email'] ?? '';
    $password = $user['password'] ?? '';
    $coupon = $user['coupon'] ?? '';
    $status = $user['status'] ?? 'candidate';
    $date = $user['date'] ?? date('Y-m-d H:i:s');
    $country = $user['country'] ?? null;

    // Insert user into users table
    $userStmt->bind_param(
        "ssssssss",
        $uin, $first_name, $last_name, $email,
        $password, $coupon, $status, $date
    );

    if (!$userStmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'error' => 'User insert failed',
            'message' => $userStmt->error
        ]);
        exit;
    }

    // Insert into candidate_profile if country is provided
    if ($country) {
        $profileStmt->bind_param("ss", $uin, $country);
        if (!$profileStmt->execute()) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Profile insert failed',
                'message' => $profileStmt->error
            ]);
            exit;
        }
    }
}

// Close statements
$userStmt->close();
$profileStmt->close();

echo json_encode(['status' => 'success']);
