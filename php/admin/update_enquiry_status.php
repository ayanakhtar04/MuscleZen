<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $enquiryId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if (!$enquiryId || !$status) {
        throw new Exception('Invalid input parameters');
    }

    // Begin transaction
    $conn->beginTransaction();

    // Update enquiry status
    $stmt = $conn->prepare("
        UPDATE enquiries 
        SET status = :status 
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $enquiryId,
        'status' => $status
    ]);

    // Log the activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (
            admin_id, 
            action,
            details,
            ip_address
        ) VALUES (
            :admin_id,
            'update_enquiry',
            :details,
            :ip_address
        )
    ");

    $stmt->execute([
        'admin_id' => $_SESSION['admin_id'] ?? 1,
        'details' => "Marked enquiry #{$enquiryId} as {$status}",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Enquiry status updated successfully'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error in update_enquiry_status: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error updating enquiry status: ' . $e->getMessage()
    ]);
}
