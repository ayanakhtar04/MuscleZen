<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();
    
    $type = $_POST['type'];
    parse_str($_POST['settings'], $settings);
    
    switch ($type) {
        case 'general':
            saveGeneralSettings($conn, $settings);
            break;
        case 'security':
            saveSecuritySettings($conn, $settings);
            break;
        case 'email':
            saveEmailSettings($conn, $settings);
            break;
        case 'backup':
            saveBackupSettings($conn, $settings);
            break;
        case 'api':
            saveApiSettings($conn, $settings);
            break;
    }
    
    // Log activity
    $activityStmt = $conn->prepare("
        INSERT INTO admin_activity_log (
            admin_id, action, description, ip_address
        ) VALUES (
            :admin_id, 'update', :description, :ip_address
        )
    ");
    
    $activityStmt->execute([
        'admin_id' => $_SESSION['admin_id'],
        'description' => "Updated {$type} settings",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Settings saved successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in save_settings: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error saving settings'
    ]);
}

function saveGeneralSettings($conn, $settings) {
    $stmt = $conn->prepare("
        UPDATE site_settings SET 
        site_name = :site_name,
        site_description = :site_description,
        timezone = :timezone,
        maintenance_mode = :maintenance_mode
    ");
    
    $stmt->execute([
        'site_name' => $settings['site_name'],
        'site_description' => $settings['site_description'],
        'timezone' => $settings['timezone'],
        'maintenance_mode' => isset($settings['maintenance_mode']) ? 1 : 0
    ]);
}

function saveSecuritySettings($conn, $settings) {
    $stmt = $conn->prepare("
        UPDATE security_settings SET 
        session_timeout = :session_timeout,
        max_login_attempts = :max_login_attempts,
        require_uppercase = :require_uppercase,
        require_numbers = :require_numbers,
        require_special = :require_special,
        two_factor_auth = :two_factor_auth
    ");
    
    $stmt->execute([
        'session_timeout' => $settings['session_timeout'],
        'max_login_attempts' => $settings['max_login_attempts'],
        'require_uppercase' => isset($settings['require_uppercase']) ? 1 : 0,
        'require_numbers' => isset($settings['require_numbers']) ? 1 : 0,
        'require_special' => isset($settings['require_special']) ? 1 : 0,
        'two_factor_auth' => isset($settings['two_factor_auth']) ? 1 : 0
    ]);
}

// Similar functions for email, backup, and API settings...
