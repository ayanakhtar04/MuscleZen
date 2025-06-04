<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';
require_once '../utils/validation.php';

try {
    AdminAuth::requireAdmin();
    
    // Super admin check for destructive operations
    $requireSuperAdmin = ['delete_users', 'delete_workouts', 'delete_content'];
    if (in_array($_POST['operation'] ?? '', $requireSuperAdmin) && $_SESSION['admin_role'] !== 'super_admin') {
        throw new Exception('Insufficient privileges');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $operation = $_POST['operation'] ?? '';
    $items = json_decode($_POST['items'] ?? '[]', true);

    if (empty($items)) {
        throw new Exception('No items selected');
    }

    switch ($operation) {
        case 'delete_users':
            bulkDeleteUsers($conn, $items);
            break;
        case 'delete_workouts':
            bulkDeleteWorkouts($conn, $items);
            break;
        case 'delete_content':
            bulkDeleteContent($conn, $items);
            break;
        case 'export_users':
            bulkExportUsers($conn, $items);
            break;
        case 'update_status':
            bulkUpdateStatus($conn, $items, $_POST['status']);
            break;
        default:
            throw new Exception('Invalid operation');
    }

} catch (Exception $e) {
    error_log("Error in bulk_operations: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function bulkDeleteUsers($conn, $userIds) {
    $conn->beginTransaction();
    
    try {
        // Validate user IDs
        foreach ($userIds as $userId) {
            if (!filter_var($userId, FILTER_VALIDATE_INT)) {
                throw new Exception('Invalid user ID');
            }
        }

        // Delete related data first
        $tables = [
            'workout_schedules',
            'progress_logs',
            'notifications',
            'user_settings',
            'post_likes',
            'comments',
            'posts'
        ];

        foreach ($tables as $table) {
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $stmt = $conn->prepare("DELETE FROM $table WHERE user_id IN ($placeholders)");
            $stmt->execute($userIds);
        }

        // Finally delete users
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->execute($userIds);

        logBulkOperation($conn, 'delete_users', count($userIds));
        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => count($userIds) . ' users deleted successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function bulkDeleteWorkouts($conn, $workoutIds) {
    $conn->beginTransaction();
    
    try {
        $placeholders = str_repeat('?,', count($workoutIds) - 1) . '?';
        
        // Delete workout schedules first
        $stmt = $conn->prepare("DELETE FROM workout_schedules WHERE workout_id IN ($placeholders)");
        $stmt->execute($workoutIds);

        // Delete workouts
        $stmt = $conn->prepare("DELETE FROM workout_plans WHERE id IN ($placeholders)");
        $stmt->execute($workoutIds);

        logBulkOperation($conn, 'delete_workouts', count($workoutIds));
        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => count($workoutIds) . ' workouts deleted successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function bulkDeleteContent($conn, $contentData) {
    $conn->beginTransaction();
    
    try {
        foreach ($contentData as $type => $ids) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            
            switch ($type) {
                case 'posts':
                    // Delete related data first
                    $stmt = $conn->prepare("DELETE FROM comments WHERE post_id IN ($placeholders)");
                    $stmt->execute($ids);
                    $stmt = $conn->prepare("DELETE FROM post_likes WHERE post_id IN ($placeholders)");
                    $stmt->execute($ids);
                    // Delete posts
                    $stmt = $conn->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    break;

                case 'media':
                    // Get file paths before deletion
                    $stmt = $conn->prepare("SELECT filename FROM user_media WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Delete files
                    foreach ($files as $file) {
                        $filepath = '../../uploads/' . $file;
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                    }
                    
                    // Delete records
                    $stmt = $conn->prepare("DELETE FROM user_media WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    break;
            }
        }

        logBulkOperation($conn, 'delete_content', array_sum(array_map('count', $contentData)));
        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Content deleted successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function bulkExportUsers($conn, $userIds) {
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(DISTINCT w.id) as workout_count,
               COUNT(DISTINCT p.id) as post_count
        FROM users u
        LEFT JOIN workout_schedules w ON u.id = w.user_id
        LEFT JOIN posts p ON u.id = p.user_id
        WHERE u.id IN ($placeholders)
        GROUP BY u.id
    ");
    $stmt->execute($userIds);
    $users = $stmt->fetchAll();

    // Convert to CSV
    $output = fopen('php://temp', 'r+');
    fputcsv($output, array_keys($users[0]));
    
    foreach ($users as $user) {
        fputcsv($output, $user);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    logBulkOperation($conn, 'export_users', count($userIds));

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export.csv"');
    echo $csv;
    exit;
}

function bulkUpdateStatus($conn, $items, $status) {
    $conn->beginTransaction();
    
    try {
        foreach ($items as $table => $ids) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id IN ($placeholders)");
            array_unshift($ids, $status);
            $stmt->execute($ids);
        }

        logBulkOperation($conn, 'update_status', array_sum(array_map('count', $items)));
        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Status updated successfully'
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function logBulkOperation($conn, $operation, $itemCount) {
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, details, ip_address)
        VALUES (:admin_id, :action, :details, :ip_address)
    ");

    $stmt->execute([
        ':admin_id' => $_SESSION['admin_id'],
        ':action' => 'bulk_' . $operation,
        ':details' => json_encode([
            'item_count' => $itemCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}
