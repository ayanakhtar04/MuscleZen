<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Ensure enquiries table exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS enquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            message TEXT,
            status ENUM('new', 'read', 'contacted') DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Get latest enquiries
    $stmt = $conn->query("
        SELECT * FROM enquiries 
        ORDER BY created_at DESC 
        LIMIT 5
    ");

    $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log for debugging
    error_log("Fetched enquiries: " . print_r($enquiries, true));

    echo json_encode([
        'status' => 'success',
        'data' => $enquiries
    ]);

} catch (Exception $e) {
    error_log("Error in get_latest_enquiries: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
