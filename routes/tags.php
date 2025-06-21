<?php



require 'config/db.php';

header('Content-Type: application/json');

$sql = "SELECT * FROM tags";
$result = $conn->query($sql);

$tags = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tagName = $row['name'];

        // Prepare a query to get post count, latest title and date for each tag
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) AS post_count,
                MAX(created_at) AS last_post_date,
                (
                    SELECT title 
                    FROM posts 
                    WHERE tag = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ) AS last_post_title
            FROM posts
            WHERE tag = ?
        ");
        $stmt->bind_param('ss', $tagName, $tagName);
        $stmt->execute();
        $statsResult = $stmt->get_result();
        $stats = $statsResult->fetch_assoc();

        $row['discussionCount'] = (int) $stats['post_count'];
        $row['lastDiscussionTitle'] = $stats['last_post_title'];
        $row['lastDiscussionCreatedAt'] = $stats['last_post_date'];

        $tags[] = $row;
    }

    $result->free();
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'error' => 'Database query failed: ' . $conn->error
    ]);
    exit;
}

http_response_code(200);
echo json_encode($tags);
