<?php
require_once '../middleware/AdminAuth.php';
AdminAuth::requireAdmin();

header('Content-Type: application/json');
require_once '../db_config.php';

try {
    $userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    
    if (!$userId) {
        throw new Exception('Invalid user ID');
    }
    
    $conn->beginTransaction();
    
    // Delete user's related data
    $tables = ['workout_plans', 'progress_logs', 'meal_logs', 'user_settings'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    
    // Delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    $conn->commit();
    
    AdminAuth::logActivity(
        $_SESSION['admin_id'], 
        'delete_user', 
        "Deleted user ID: $userId"
    );
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User deleted successfully'
    ]);
} catch(Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
