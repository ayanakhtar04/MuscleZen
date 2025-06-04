<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $action = $_GET['action'] ?? 'view';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $adminId = $_GET['admin_id'] ?? null;
    $logType = $_GET['type'] ?? null;

    switch ($action) {
        case 'view':
            getAuditLogs($conn, $startDate, $endDate, $adminId, $logType);
            break;
        case 'export':
            exportAuditLogs($conn, $startDate, $endDate, $adminId, $logType);
            break;
        case 'clear':
            clearOldLogs($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    error_log("Error in audit_log: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function getAuditLogs($conn, $startDate, $endDate, $adminId = null, $logType = null) {
    $params = [$startDate, $endDate];
    $whereConditions = ["DATE(created_at) BETWEEN ? AND ?"];
    
    if ($adminId) {
        $whereConditions[] = "admin_id = ?";
        $params[] = $adminId;
    }
    
    if ($logType) {
        $whereConditions[] = "action LIKE ?";
        $params[] = $logType . '%';
    }

    $whereClause = implode(" AND ", $whereConditions);

    $stmt = $conn->prepare("
        SELECT 
            al.id,
            al.admin_id,
            au.username as admin_username,
            al.action,
            al.details,
            al.ip_address,
            al.created_at
        FROM admin_activity_log al
        JOIN admin_users au ON al.admin_id = au.id
        WHERE $whereClause
        ORDER BY al.created_at DESC
        LIMIT 1000
    ");

    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $logs
    ]);
}

function exportAuditLogs($conn, $startDate, $endDate, $adminId = null, $logType = null) {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_log_export.csv"');

    $params = [$startDate, $endDate];
    $whereConditions = ["DATE(created_at) BETWEEN ? AND ?"];
    
    if ($adminId) {
        $whereConditions[] = "admin_id = ?";
        $params[] = $adminId;
    }
    
    if ($logType) {
        $whereConditions[] = "action LIKE ?";
        $params[] = $logType . '%';
    }

    $whereClause = implode(" AND ", $whereConditions);

    $stmt = $conn->prepare("
        SELECT 
            al.id,
            au.username as admin_username,
            al.action,
            al.details,
            al.ip_address,
            al.created_at
        FROM admin_activity_log al
        JOIN admin_users au ON al.admin_id = au.id
        WHERE $whereClause
        ORDER BY al.created_at DESC
    ");

    $stmt->execute($params);

    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, ['ID', 'Admin Username', 'Action', 'Details', 'IP Address', 'Timestamp']);
    
    // Add data rows
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['admin_username'],
            $row['action'],
            $row['details'],
            $row['ip_address'],
            $row['created_at']
        ]);
    }

    fclose($output);
}

function clearOldLogs($conn) {
    // Require super admin for this action
    if ($_SESSION['admin_role'] !== 'super_admin') {
        throw new Exception('Insufficient privileges');
    }

    // Keep logs for the last 90 days
    $stmt = $conn->prepare("
        DELETE FROM admin_activity_log 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Old logs cleared successfully',
        'rows_affected' => $stmt->rowCount()
    ]);
}
