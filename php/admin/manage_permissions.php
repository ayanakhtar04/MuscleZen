<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    
    // Verify super admin role for permission management
    if ($_SESSION['admin_role'] !== 'super_admin') {
        throw new Exception('Insufficient privileges');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'assign':
            assignPermission($conn);
            break;
        case 'revoke':
            revokePermission($conn);
            break;
        case 'list':
            listPermissions($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    error_log("Error in manage_permissions: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function assignPermission($conn) {
    $adminId = filter_var($_POST['admin_id'], FILTER_VALIDATE_INT);
    $permission = filter_var($_POST['permission'], FILTER_SANITIZE_STRING);
    
    if (!$adminId || !$permission) {
        throw new Exception('Invalid input parameters');
    }

    // Check if admin exists
    $stmt = $conn->prepare("SELECT role FROM admin_users WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception('Admin user not found');
    }

    // Insert permission
    $stmt = $conn->prepare("
        INSERT INTO admin_permissions (role, permission)
        VALUES (:role, :permission)
        ON DUPLICATE KEY UPDATE permission = VALUES(permission)
    ");

    $stmt->execute([
        ':role' => $admin['role'],
        ':permission' => $permission
    ]);

    // Log permission assignment
    logPermissionChange($conn, $adminId, $permission, 'assign');

    echo json_encode([
        'status' => 'success',
        'message' => 'Permission assigned successfully'
    ]);
}

function revokePermission($conn) {
    $adminId = filter_var($_POST['admin_id'], FILTER_VALIDATE_INT);
    $permission = filter_var($_POST['permission'], FILTER_SANITIZE_STRING);
    
    if (!$adminId || !$permission) {
        throw new Exception('Invalid input parameters');
    }

    // Get admin role
    $stmt = $conn->prepare("SELECT role FROM admin_users WHERE id = ?");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception('Admin user not found');
    }

    // Delete permission
    $stmt = $conn->prepare("
        DELETE FROM admin_permissions
        WHERE role = ? AND permission = ?
    ");

    $stmt->execute([$admin['role'], $permission]);

    // Log permission revocation
    logPermissionChange($conn, $adminId, $permission, 'revoke');

    echo json_encode([
        'status' => 'success',
        'message' => 'Permission revoked successfully'
    ]);
}

function listPermissions($conn) {
    $role = filter_var($_GET['role'] ?? '', FILTER_SANITIZE_STRING);
    
    if ($role) {
        $stmt = $conn->prepare("
            SELECT permission 
            FROM admin_permissions 
            WHERE role = ?
        ");
        $stmt->execute([$role]);
    } else {
        $stmt = $conn->query("
            SELECT role, GROUP_CONCAT(permission) as permissions
            FROM admin_permissions
            GROUP BY role
        ");
    }

    $permissions = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $permissions
    ]);
}

function logPermissionChange($conn, $adminId, $permission, $action) {
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, details, ip_address)
        VALUES (:admin_id, :action, :details, :ip_address)
    ");

    $stmt->execute([
        ':admin_id' => $_SESSION['admin_id'],
        ':action' => 'permission_' . $action,
        ':details' => json_encode([
            'target_admin_id' => $adminId,
            'permission' => $permission
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}
