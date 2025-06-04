<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    // Verify admin is logged in
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Get and validate input data
    // Using htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING
    $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    // Validation checks
    if (empty($username) || empty($email) || empty($password)) {
        throw new Exception('All fields are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Begin transaction
    $conn->beginTransaction();

    // Check if username or email already exists
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE username = :username OR email = :email
    ");
    
    $stmt->execute([
        'username' => $username,
        'email' => $email
    ]);

    if ($stmt->fetch()['count'] > 0) {
        throw new Exception('Username or email already exists');
    }

    // Insert new user
    $stmt = $conn->prepare("
        INSERT INTO users (
            username, 
            email, 
            password, 
            created_at
        ) VALUES (
            :username,
            :email,
            :password,
            NOW()
        )
    ");

    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword
    ]);

    $userId = $conn->lastInsertId();

    // Log activity - Check if columns exist first
    try {
        $stmt = $conn->prepare("DESCRIBE admin_activity_log");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($columns)) {
            $logColumns = ['admin_id', 'action'];
            $logValues = [':admin_id', ':action'];
            $logParams = [
                ':admin_id' => $_SESSION['admin_id'],
                ':action' => 'create_user'
            ];

            // Add optional columns if they exist
            if (in_array('description', $columns)) {
                $logColumns[] = 'description';
                $logValues[] = ':description';
                $logParams[':description'] = "Created new user: {$username}";
            }

            if (in_array('ip_address', $columns)) {
                $logColumns[] = 'ip_address';
                $logValues[] = ':ip_address';
                $logParams[':ip_address'] = $_SERVER['REMOTE_ADDR'];
            }

            $logQuery = "INSERT INTO admin_activity_log (" . implode(', ', $logColumns) . ") 
                        VALUES (" . implode(', ', $logValues) . ")";

            $stmt = $conn->prepare($logQuery);
            $stmt->execute($logParams);
        }
    } catch (Exception $e) {
        // Just log the error but don't stop the user creation
        error_log("Error logging activity: " . $e->getMessage());
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'User created successfully',
        'user_id' => $userId
    ]);

} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error creating user: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
