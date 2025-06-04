<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    $userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT id, username, email, created_at
        FROM users 
        WHERE id = :id
    ");
    
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $user
    ]);

} catch (Exception $e) {
    error_log("Error in get_user_details: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
