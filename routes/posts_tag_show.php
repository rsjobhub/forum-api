<?php
// CORS + dependencies
require 'check_token.php';
require 'config/db.php';

http_response_code(200);
header('Content-Type: application/json');

// 🟡 Ensure $tag_id and $data->id (user ID) are defined
if (!isset($tag_id) || !isset($data->id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tag ID and user ID are required']);
    exit;
}

// ✅ Get tag name from tag_id
$tagStmt = $conn->prepare("SELECT name FROM tags WHERE id = ?");
$tagStmt->bind_param("i", $tag_id);
$tagStmt->execute();
$tagResult = $tagStmt->get_result();

if ($tagResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Tag not found']);
    exit;
}
$tagRow = $tagResult->fetch_assoc();
$tag = $tagRow['name'];
$tagStmt->close();

// ✅ Get all posts with this tag
$sql = "
SELECT 
    posts.*,
    users.id AS author_id,
    users.first_name AS author_name,
    users.passport AS author_passport,
    COALESCE(comment_counts.comments_count, 0) AS comments_count,
    last_commenters.first_name AS last_commented_by,
    latest_comments.created_at AS last_commented_at
FROM posts

JOIN users ON users.id = posts.user_id

LEFT JOIN (
    SELECT post_id, COUNT(*) AS comments_count
    FROM comments
    GROUP BY post_id
) AS comment_counts ON posts.id = comment_counts.post_id

LEFT JOIN (
    SELECT c1.post_id, c1.user_id, c1.created_at
    FROM comments c1
    INNER JOIN (
        SELECT post_id, MAX(created_at) AS max_created_at
        FROM comments
        GROUP BY post_id
    ) c2 ON c1.post_id = c2.post_id AND c1.created_at = c2.max_created_at
) AS latest_comments ON posts.id = latest_comments.post_id

LEFT JOIN users AS last_commenters ON latest_comments.user_id = last_commenters.id

WHERE posts.tag = ?
ORDER BY posts.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tag);
$stmt->execute();
$result = $stmt->get_result();

// 🧠 Build post array and collect all post IDs
$posts = [];
$postIds = [];

while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
    $postIds[] = (int) $row['id'];
}
$stmt->close();

// ✅ Fetch all read post IDs for this user
$readPostIds = [];
if (!empty($postIds)) {
    $inClause = implode(',', array_fill(0, count($postIds), '?'));
    $types = str_repeat('i', count($postIds) + 1); // user_id + post_ids

    $query = "SELECT post_id FROM post_reads WHERE user_id = ? AND post_id IN ($inClause)";
    $readStmt = $conn->prepare($query);

    $params = array_merge([$data->id], $postIds);
    $readStmt->bind_param($types, ...$params);
    $readStmt->execute();
    $readResult = $readStmt->get_result();

    while ($r = $readResult->fetch_assoc()) {
        $readPostIds[] = (int) $r['post_id'];
    }
    $readStmt->close();
}

// ✅ Build final response
$finalPosts = [];
foreach ($posts as $row) {
    $isRead = in_array((int)$row['id'], $readPostIds) ? 1 : 0;

    $finalPosts[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'tag' => $row['tag'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'author' => [
            'id' => $row['author_id'],
            'name' => $row['author_name'],
            'passport' => $row['author_passport'],
        ],
        'comments_count' => (int)$row['comments_count'],
        'last_commented_by' => $row['last_commented_by'],
        'last_commented_at' => $row['last_commented_at'],
        'is_read' => $isRead,
    ];
}

// ✅ Output
echo json_encode($finalPosts);
