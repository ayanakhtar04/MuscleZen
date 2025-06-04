<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

require_once 'db_config.php';

try {
    $user_id = $_SESSION['user_id'];
    $post_id = $_POST['post_id'];
    $content = trim($_POST['content']);

    if (empty($content)) {
        throw new Exception("Comment cannot be empty");
    }

    $stmt = $conn->prepare("
        INSERT INTO comments (user_id, post_id, content) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user_id, $post_id, $content]);

    // Get the newly created comment with user info
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            u.username,
            u.profile_image as user_image
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conn->lastInsertId()]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $comment
    ]);

} catch(Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
