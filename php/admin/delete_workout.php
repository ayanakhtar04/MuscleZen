<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    $workoutId = (int)($_POST['id'] ?? 0);
    if (!$workoutId) {
        throw new Exception('Invalid workout ID');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("DELETE FROM workouts WHERE id = ?");
    $stmt->execute([$workoutId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Workout deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
