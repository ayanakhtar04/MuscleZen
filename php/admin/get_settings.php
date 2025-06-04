<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();
    
    // Get general settings
    $stmt = $conn->query("SELECT * FROM site_settings");
    $generalSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get security settings
    $stmt = $conn->query("SELECT * FROM security_settings");
    $securitySettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get email settings
    $stmt = $conn->query("SELECT * FROM email_settings");
    $emailSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get backup settings
    $stmt = $conn->query("SELECT * FROM backup_settings");
    $backupSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get API settings
    $stmt = $conn->query("SELECT * FROM api_settings");
    $apiSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'general' => $generalSettings,
            'security' => $securitySettings,
            'email' => $emailSettings,
            'backup' => $backupSettings,
            'api' => $apiSettings
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_settings: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching settings'
    ]);
}
