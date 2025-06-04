<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();
    
    $contentId = $_POST['content_id'];
    
    // Get content info before deletion
    $infoStmt = $conn->prepare("SELECT type, title FROM content WHERE id = :id");
    $infoStmt->execute(['id' => $contentId]);
    $contentInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete content
    $stmt = $conn->prepare("DELETE FROM content WHERE id = :id");
    $stmt->execute(['id' => $contentId]);
    
    // Log activity
    $activityStmt = $conn->prepare("
        INSERT INTO admin_activity_log (
            admin_id, action, description, ip_address
        ) VALUES (
            :admin_id, 'delete', :description, :ip_address
        )
    ");
    
    $activityStmt->execute([
        'admin_id' => $_SESSION['admin_id'],
        'description' => "Deleted {$contentInfo['type']}: {$contentInfo['title']}",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Content deleted successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in delete_content: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error deleting content'
    ]);
}
