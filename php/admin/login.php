<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'admin_db_config.php';

try {
    // Get database connection
    $adminDb = AdminDatabase::getInstance();
    $conn = $adminDb->getConnection();

    // Log incoming request
    error_log("Admin login attempt - POST data: " . print_r($_POST, true));

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and sanitize inputs
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW));
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        throw new Exception('Please provide both username and password');
    }

    // Prepare statement with parameter binding
    $stmt = $conn->prepare("
        SELECT id, username, password, role, status
        FROM admin_users 
        WHERE (username = :username OR email = :username)
        AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([':username' => $username]);
    $admin = $stmt->fetch();

    // Debug log
    error_log("Admin query result: " . print_r($admin, true));

    if (!$admin) {
        throw new Exception('Invalid credentials');
    }

    if (!password_verify($password, $admin['password'])) {
        throw new Exception('Invalid credentials');
    }

    // Set session variables
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['last_activity'] = time();

    // Update last login
    $stmt = $conn->prepare("
        UPDATE admin_users 
        SET last_login = CURRENT_TIMESTAMP 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $admin['id']]);

    // Log successful login
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, ip_address, details) 
        VALUES (:admin_id, 'login', :ip_address, :details)
    ");
    
    $stmt->execute([
        ':admin_id' => $admin['id'],
        ':ip_address' => $_SERVER['REMOTE_ADDR'],
        ':details' => json_encode([
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ])
    ]);

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'data' => [
            'username' => $admin['username'],
            'role' => $admin['role']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in admin login: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Admin login error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
