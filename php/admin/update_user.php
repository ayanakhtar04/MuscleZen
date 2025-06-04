<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    // Check admin session
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Validate input
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = !empty($_POST['password']) ? $_POST['password'] : null;

    // Validate required fields
    if (!$userId || !$username || !$email) {
        throw new Exception('Required fields are missing');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Check if username or email exists for other users
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE (username = :username OR email = :email) 
        AND id != :id
    ");

    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'id' => $userId
    ]);

    if ($stmt->fetch()['count'] > 0) {
        throw new Exception('Username or email already exists');
    }

    // Build update query
    $updateFields = ['username = :username', 'email = :email'];
    $params = [
        'username' => $username,
        'email' => $email,
        'id' => $userId
    ];

    // Add password update if provided
    if ($password !== null) {
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long');
        }
        $updateFields[] = 'password = :password';
        $params['password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    // Update user
    $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $conn->prepare($updateQuery);
    $stmt->execute($params);

    // Check if the activity log table exists and has the necessary columns
    try {
        $stmt = $conn->prepare("DESCRIBE admin_activity_log");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Only log if the table has the necessary columns
        if (in_array('admin_id', $columns) && in_array('action', $columns)) {
            $logQuery = "INSERT INTO admin_activity_log (admin_id, action";
            $logValues = "VALUES (:admin_id, :action";
            $logParams = [
                'admin_id' => $_SESSION['admin_id'],
                'action' => 'update_user'
            ];

            // Add optional columns if they exist
            if (in_array('description', $columns)) {
                $logQuery .= ", description";
                $logValues .= ", :description";
                $logParams['description'] = "Updated user: {$username} (ID: {$userId})";
            }

            if (in_array('ip_address', $columns)) {
                $logQuery .= ", ip_address";
                $logValues .= ", :ip_address";
                $logParams['ip_address'] = $_SERVER['REMOTE_ADDR'];
            }

            $logQuery .= ") " . $logValues . ")";
            $stmt = $conn->prepare($logQuery);
            $stmt->execute($logParams);
        }
    } catch (Exception $e) {
        // If there's an error with the activity log, just log it but don't stop the update
        error_log("Error logging activity: " . $e->getMessage());
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'User updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Error updating user: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
