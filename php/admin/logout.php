<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    // Add debug logging
    error_log("Logout attempt initiated");
    
    if (isset($_SESSION['admin_id'])) {
        error_log("Admin ID found: " . $_SESSION['admin_id']);
        
        // Log the logout activity
        $db = AdminDatabase::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO admin_activity_log (admin_id, action, ip_address)
            VALUES (:admin_id, 'logout', :ip_address)
        ");
        
        $stmt->execute([
            ':admin_id' => $_SESSION['admin_id'],
            ':ip_address' => $_SERVER['REMOTE_ADDR']
        ]);

        // Clear admin session
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['admin_role']);
        
        // Destroy the session
        session_destroy();
        
        error_log("Session destroyed successfully");
    }

    $response = [
        'status' => 'success',
        'message' => 'Logged out successfully',
        'redirect' => '../index.html'
    ];
    
    error_log("Sending response: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in admin_logout: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error during logout: ' . $e->getMessage()
    ]);
}
