<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Get all enquiries ordered by created_at
    $stmt = $conn->query("
        SELECT * FROM enquiries 
        ORDER BY 
            CASE 
                WHEN status = 'new' THEN 0 
                ELSE 1 
            END,
            created_at DESC
    ");

    $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $enquiries
    ]);

} catch (Exception $e) {
    error_log("Error in get_all_enquiries: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error loading enquiries: ' . $e->getMessage()
    ]);
}
