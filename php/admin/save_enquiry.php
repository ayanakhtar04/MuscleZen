<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Create enquiries table if it doesn't exist
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

    // Validate and sanitize input
    $name = trim(strip_tags($_POST['cf-name'] ?? ''));
    $email = filter_var(trim($_POST['cf-email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = preg_replace('/[^0-9]/', '', $_POST['cf-phone'] ?? ''); // Keep only numbers
    $message = trim(strip_tags($_POST['cf-message'] ?? ''));

    // Validation
    if (empty($name)) {
        throw new Exception('Name is required');
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email is required');
    }
    
    // Validate Indian phone number
    if (empty($phone) || !preg_match('/^[6-9]\d{9}$/', $phone)) {
        throw new Exception('Valid Indian mobile number is required (10 digits starting with 6-9)');
    }

    // Format phone number for display (XXX-XXX-XXXX)
    $formattedPhone = substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);

    // Begin transaction
    $conn->beginTransaction();

    // Insert enquiry
    $stmt = $conn->prepare("
        INSERT INTO enquiries (name, email, phone, message, status)
        VALUES (:name, :email, :phone, :message, 'new')
    ");

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'phone' => $formattedPhone,
        'message' => $message
    ]);

    $enquiryId = $conn->lastInsertId();

    // Log activity
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (
            admin_id, 
            action, 
            details, 
            ip_address
        ) VALUES (
            1,
            'new_enquiry',
            :details,
            :ip_address
        )
    ");

    $details = "New enquiry received from {$name} ({$formattedPhone})";
    $stmt->execute([
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Enquiry submitted successfully',
        'enquiry_id' => $enquiryId
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error in save_enquiry: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
