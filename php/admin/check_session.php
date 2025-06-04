<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['admin_id'])) {
    require_once '../db_config.php';

    try {
        $stmt = $conn->prepare("
            SELECT username, last_login 
            FROM admin_users 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'logged_in' => true,
            'username' => $admin['username'],
            'role' => $_SESSION['admin_role'],
            'last_login' => $admin['last_login']
        ]);
    } catch(Exception $e) {
        echo json_encode([
            'logged_in' => false,
            'message' => 'Error fetching admin data'
        ]);
    }
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
?>
