<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'admin_db_config.php';
    
    $adminDb = AdminDatabase::getInstance();
    $conn = $adminDb->getConnection();
    
    // Check if tables exist
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Check admin user
    $adminExists = $conn->query("SELECT COUNT(*) as count FROM admin_users")->fetch();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'tables' => $tables,
        'admin_users' => $adminExists['count'],
        'connection_info' => [
            'database' => $conn->query("SELECT DATABASE()")->fetchColumn(),
            'server_version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION),
            'client_version' => $conn->getAttribute(PDO::ATTR_CLIENT_VERSION)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
