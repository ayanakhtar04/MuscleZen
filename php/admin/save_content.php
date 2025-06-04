<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();
    
    $contentType = $_POST['content_type'];
    $title = $_POST['title'];
    $category = $_POST['category'];
    $content = $_POST['content'];
    $authorId = $_SESSION['admin_id'];
    
    // Handle file upload if exists
    $mediaUrl = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/content/';
        $fileName = uniqid() . '_' . basename($_FILES['media']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadFile)) {
            $mediaUrl = 'uploads/content/' . $fileName;
        }
    }
    
    // Insert content
    $stmt = $conn->prepare("
        INSERT INTO content (
            type, title, category, content, media_url, 
            author_id, status, created_at
        ) VALUES (
            :type, :title, :category, :content, :media_url,
            :author_id, 'published', NOW()
        )
    ");
    
    $stmt->execute([
        'type' => $contentType,
        'title' => $title,
        'category' => $category,
        'content' => $content,
        'media_url' => $mediaUrl,
        'author_id' => $authorId
    ]);
    
    // Log activity
    $activityStmt = $conn->prepare("
        INSERT INTO admin_activity_log (
            admin_id, action, description, ip_address
        ) VALUES (
            :admin_id, 'create', :description, :ip_address
        )
    ");
    
    $activityStmt->execute([
        'admin_id' => $authorId,
        'description' => "Created new {$contentType}: {$title}",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Content saved successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in save_content: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error saving content'
    ]);
}
