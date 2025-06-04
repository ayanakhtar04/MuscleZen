<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $duration = filter_var($_POST['duration'] ?? 0, FILTER_VALIDATE_INT);

    // Calculate end time if duration is specified
    $endTime = $duration ? date('Y-m-d H:i:s', strtotime("+$duration minutes")) : null;

    // Update maintenance mode settings
    $stmt = $conn->prepare("
        INSERT INTO user_settings (setting_key, setting_value) 
        VALUES 
            ('maintenance_mode', ?),
            ('maintenance_end_time', ?)
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value)
    ");

    $stmt->execute([
        $enabled ? '1' : '0',
        $endTime
    ]);

    // Log the maintenance mode change
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, details, ip_address) 
        VALUES (?, ?, ?, ?)
    ");

    $details = json_encode([
        'enabled' => $enabled,
        'duration' => $duration,
        'end_time' => $endTime
    ]);

    $stmt->execute([
        $_SESSION['admin_id'],
        'maintenance_mode_update',
        $details,
        $_SERVER['REMOTE_ADDR']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => $enabled ? 'Maintenance mode enabled' : 'Maintenance mode disabled',
        'data' => [
            'end_time' => $endTime
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in set_maintenance_mode: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update maintenance mode'
    ]);
}
