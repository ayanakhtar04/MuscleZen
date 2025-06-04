<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Get enquiries count
    $newEnquiries = $conn->query("
        SELECT COUNT(*) FROM enquiries WHERE status = 'new'
    ")->fetchColumn();

    // Get other stats with error handling
    $stats = [
        'total_users' => 0,
        'total_workouts' => 0,
        'active_users' => 0,
        'new_enquiries' => $newEnquiries
    ];

    // Try to get users count if table exists
    try {
        $stats['total_users'] = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting users count: " . $e->getMessage());
    }

    // Try to get workouts count if table exists
    try {
        $stats['total_workouts'] = $conn->query("SELECT COUNT(*) FROM workouts")->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting workouts count: " . $e->getMessage());
    }

    echo json_encode([
        'status' => 'success',
        'data' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error in get_dashboard_stats: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
