<?php
require 'config/db.php';
require 'check_token.php';
header('Content-Type: application/json');

$userId = $data->id;

// ✅ Step 1: Fetch all posts with author info
$postsSql = "
    SELECT 
        posts.*,
        users.id AS author_id,
        users.first_name AS author_name,
        users.passport AS author_passport
    FROM posts
    JOIN users ON users.id = posts.user_id
    ORDER BY posts.created_at DESC
";
$postsResult = $conn->query($postsSql);
if (!$postsResult) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch posts']);
    exit;
}

$postsRaw = [];
$postIds = [];

while ($row = $postsResult->fetch_assoc()) {
    $postsRaw[$row['id']] = $row;
    $postIds[] = (int)$row['id'];
}

// ✅ Step 2: Fetch all comments count in one query
$commentsCount = [];
if ($postIds) {
    $inClause = implode(',', $postIds);
    $countSql = "SELECT post_id, COUNT(*) AS count FROM comments WHERE post_id IN ($inClause) GROUP BY post_id";
    $countResult = $conn->query($countSql);
    while ($r = $countResult->fetch_assoc()) {
        $commentsCount[(int)$r['post_id']] = (int)$r['count'];
    }
}

// ✅ Step 3: Fetch last commenter info for each post
$lastComments = [];
if ($postIds) {
    $lastCommentSql = "
        SELECT c.post_id, u.first_name AS commenter_name, c.created_at AS commented_at
        FROM comments c
        JOIN users u ON u.id = c.user_id
        INNER JOIN (
            SELECT post_id, MAX(created_at) AS max_created_at
            FROM comments
            WHERE post_id IN ($inClause)
            GROUP BY post_id
        ) last ON c.post_id = last.post_id AND c.created_at = last.max_created_at
    ";
    $lastResult = $conn->query($lastCommentSql);
    while ($r = $lastResult->fetch_assoc()) {
        $lastComments[(int)$r['post_id']] = [
            'name' => $r['commenter_name'],
            'time' => $r['commented_at'],
        ];
    }
}

// ✅ Step 4: Fetch all read post IDs for this user
$readPostIds = [];
if ($postIds) {
    $readInClause = implode(',', array_fill(0, count($postIds), '?'));
    $readQuery = "SELECT post_id FROM post_reads WHERE user_id = ? AND post_id IN ($readInClause)";
    $readStmt = $conn->prepare($readQuery);
    $types = str_repeat('i', count($postIds) + 1);
    $params = array_merge([$userId], $postIds);
    $readStmt->bind_param($types, ...$params);
    $readStmt->execute();
    $readResult = $readStmt->get_result();
    while ($r = $readResult->fetch_assoc()) {
        $readPostIds[] = (int)$r['post_id'];
    }
    $readStmt->close();
}

// ✅ Step 5: Combine all data into the final post array
$finalPosts = [];

foreach ($postsRaw as $postId => $post) {
    $finalPosts[] = [
        'id' => $post['id'],
        'title' => $post['title'],
        'description' => $post['description'],
        'tag' => $post['tag'],
        'created_at' => $post['created_at'],
        'updated_at' => $post['updated_at'],
        'author' => [
            'id' => $post['author_id'],
            'name' => $post['author_name'],
            'passport' => $post['author_passport'],
        ],
        'comments_count' => $commentsCount[$postId] ?? 0,
        'last_commented_by' => $lastComments[$postId]['name'] ?? null,
        'last_commented_at' => $lastComments[$postId]['time'] ?? null,
        'is_read' => in_array($postId, $readPostIds) ? 1 : 0,
    ];
}

// ✅ Output the response
http_response_code(200);
echo json_encode($finalPosts);
