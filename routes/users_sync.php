<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config/db.php'; // Your VPS DB connection

header('Content-Type: application/json');

// 🔒 Optional: Auth token check
$headers = getallheaders();
if (!isset($headers['Authorization']) || $headers['Authorization'] !== 'Bearer YOUR_SECRET_KEY') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 📥 Parse incoming data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['users']) || !is_array($data['users'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing users array']);
    exit;
}

// ✅ Prepare SQL
$stmt = $conn->prepare("
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

// ✅ Loop through each user
foreach ($data['users'] as $user) {
    $uin = $user['uin'] ?? '';
    $first_name = $user['first_name'] ?? '';
    $last_name = $user['last_name'] ?? '';
    $email = $user['email'] ?? '';
    $password = $user['password'] ?? '';
    $coupon = $user['coupon'] ?? '';
    $status = $user['status'] ?? 'candidate';
    $date = $user['date'] ?? date('Y-m-d H:i:s');

    $stmt->bind_param(
        "ssssssss",
        $uin, $first_name, $last_name, $email,
        $password, $coupon, $status, $date
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'error' => 'SQL Error',
            'message' => $stmt->error
        ]);
        exit;
    }
}

$stmt->close();
echo json_encode(['status' => 'success']);
