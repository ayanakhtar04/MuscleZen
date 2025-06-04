<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $action = $_POST['action'] ?? '';
    $reportId = filter_var($_POST['report_id'], FILTER_VALIDATE_INT);
    $resolution = filter_var($_POST['resolution'] ?? '', FILTER_SANITIZE_STRING);

    if (!$reportId || !$action) {
        throw new Exception('Invalid report information');
    }

    switch ($action) {
        case 'review':
            reviewReport($conn, $reportId);
            break;
        case 'resolve':
            resolveReport($conn, $reportId, $resolution);
            break;
        case 'dismiss':
            dismissReport($conn, $reportId);
            break;
        default:
            throw new Exception('Invalid action');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Report handled successfully'
    ]);

} catch (Exception $e) {
    error_log("Error in manage_reports: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function reviewReport($conn, $reportId) {
    $stmt = $conn->prepare("
        UPDATE post_reports 
        SET status = 'reviewed', 
            reviewed_at = CURRENT_TIMESTAMP,
            reviewed_by = ?
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['admin_id'], $reportId]);
}

function resolveReport($conn, $reportId, $resolution) {
    // Start transaction
    $conn->beginTransaction();

    try {
        // Get report details
        $stmt = $conn->prepare("
            SELECT post_id, reason 
            FROM post_reports 
            WHERE id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            throw new Exception('Report not found');
        }

        // Update report status
        $stmt = $conn->prepare("
            UPDATE post_reports 
            SET status = 'resolved',
                resolution = ?,
                resolved_at = CURRENT_TIMESTAMP,
                resolved_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$resolution, $_SESSION['admin_id'], $reportId]);

        // Log resolution
        logReportResolution($conn, $reportId, $report['post_id'], $resolution);

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function dismissReport($conn, $reportId) {
    $stmt = $conn->prepare("
        UPDATE post_reports 
        SET status = 'dismissed',
            dismissed_at = CURRENT_TIMESTAMP,
            dismissed_by = ?
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['admin_id'], $reportId]);
}

function logReportResolution($conn, $reportId, $postId, $resolution) {
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, details, ip_address)
        VALUES (:admin_id, 'report_resolved', :details, :ip_address)
    ");

    $stmt->execute([
        ':admin_id' => $_SESSION['admin_id'],
        ':details' => json_encode([
            'report_id' => $reportId,
            'post_id' => $postId,
            'resolution' => $resolution
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}
