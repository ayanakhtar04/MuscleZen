<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Validate input
    $workoutId = (int)($_POST['workout_id'] ?? 0);
    $name = trim($_POST['workout_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $difficulty = trim($_POST['difficulty'] ?? '');
    $duration = (int)($_POST['duration'] ?? 0);
    $calories = (int)($_POST['calories_burn'] ?? 0);

    if (!$workoutId || empty($name) || empty($category) || empty($difficulty) || $duration <= 0) {
        throw new Exception('Please fill all required fields');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        UPDATE workouts SET
            name = :name,
            description = :description,
            category = :category,
            difficulty = :difficulty,
            duration = :duration,
            calories_burn = :calories,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $workoutId,
        'name' => $name,
        'description' => $description,
        'category' => $category,
        'difficulty' => $difficulty,
        'duration' => $duration,
        'calories' => $calories
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Workout updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
