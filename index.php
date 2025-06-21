<?php
$baseFolder = '/forum'; // Your folder name with leading slash

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove the base folder from the URI if it exists
if (strpos($uri, $baseFolder) === 0) {
    $uri = substr($uri, strlen($baseFolder));
}

$uri = trim($uri, '/');

// Split the URI into segments
$segments = explode('/', $uri);
if ($uri === 'api/posts/add') {
    require 'routes/add_post.php';

} 
elseif ($uri === 'api/posts') {
    require 'routes/posts.php';

} 

 elseif ($uri === 'api/comment/add') {
    require 'routes/add_comment.php';

} 
 elseif ($uri === 'api/post/update') {
    require 'routes/update_post.php';

} 
 elseif ($uri === 'api/comment/update') {
    require 'routes/update_comment.php';

} 
 elseif ($uri === 'api/comment/like') {
    require 'routes/like_comment.php';

} 
 elseif ($uri === 'api/comment/unlike') {
    require 'routes/unlike_comment.php';

} 
 elseif ($uri === 'api/post/read') {
    require 'routes/read_post.php';

} 
 elseif ($uri === 'api/post/read/all') {
    require 'routes/all_read_post.php';

} 
 elseif ($uri === 'api/comment/reply') {
    require 'routes/reply_comments.php';

} 
 
 elseif ($uri === 'api/tags') {
    require 'routes/tags.php';

} 
 elseif ($uri === 'api/tags/add') {
    require 'routes/add_tag.php';

} 

// /api/posts/{tag} — when the 3rd segment is not numeric
elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'posts' && $segments[2] === 'tag' && is_numeric($segments[3])) {
    $tag_id = $segments[3];
    require 'routes/posts_tag_show.php';
}

elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'posts') {
    $postId = $segments[2]; // Dynamic param
    $userId = $segments[3]; // Dynamic param
     // Validate that the post ID is numeric
    if (!is_numeric($postId) || !is_numeric($userId)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid post ID OR user ID']);
        exit;
    }
    require 'routes/post_show.php';

} 
elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'post' && $segments[2]=== 'delete') {
    $postId = $segments[3]; // Dynamic param
     // Validate that the post ID is numeric
    if (!is_numeric($postId)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }
    require 'routes/post_delete.php';

} 
elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'comment' && $segments[2]=== 'delete') {
    $commentId = $segments[3]; // Dynamic param
     // Validate that the post ID is numeric
    if (!is_numeric($commentId)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }
    require 'routes/comment_delete.php';

} 
elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'auth' && $segments[2]==='token') {
    $userUin = $segments[3]; // Dynamic param
     // Validate that the post ID is numeric
    if (!isset($userUin) || !is_numeric($userUin)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid  user uin']);
        exit;
    }
    require 'routes/issue_token.php';

} 
elseif (count($segments) === 3 && $segments[0] === 'api' && $segments[1] === 'user') {
    $user_id = $segments[2]; // Dynamic param
     // Validate that the post ID is numeric
    if (!is_numeric($user_id)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid user ID']);
        exit;
    }
    require 'routes/get_user.php';

} 
elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'user' && $segments[2] === 'posts' ) {
    $userID = $segments[3]; // Dynamic param
     // Validate that the post ID is numeric
    if (!is_numeric($userID)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }
    require 'routes/user_posts.php';

} 
elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'user' && $segments[2] === 'comments' ) {
    $userID = $segments[3]; // Dynamic param
     // Validate that the post ID is numeric
    if (!is_numeric($userID)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid user ID']);
        exit;
    }
    require 'routes/user_comments.php';

} 
elseif (count($segments) === 4 && $segments[0] === 'api' && $segments[1] === 'user' && $segments[2] === 'likes' ) {
    $userID = $segments[3]; // Dynamic param
     // Validate that the post ID is numeric
    if (!is_numeric($userID)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid user ID']);
        exit;
    }
    require 'routes/user_like_comments.php';

} 



else {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
}

