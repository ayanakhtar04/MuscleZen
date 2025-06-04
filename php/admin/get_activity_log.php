<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Create activity log table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS admin_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT,
            action VARCHAR(50) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Get recent activities
    $query = "
        SELECT 
            al.*,
            COALESCE(u.username, 'System') as admin_name
        FROM admin_activity_log al
        LEFT JOIN users u ON al.admin_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ";

    $activities = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // Format activities for display
    $formattedActivities = array_map(function($activity) {
        return [
            'id' => $activity['id'],
            'type' => $activity['action'],
            'details' => $activity['details'],
            'admin_name' => $activity['admin_name'],
            'ip_address' => $activity['ip_address'],
            'created_at' => $activity['created_at']
        ];
    }, $activities);

    echo json_encode([
        'status' => 'success',
        'data' => $formattedActivities
    ]);

} catch (Exception $e) {
    error_log("Error in get_activity_log: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
