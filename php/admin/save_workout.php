<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Validate input
    $name = trim($_POST['workout_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $difficulty = trim($_POST['difficulty'] ?? '');
    $duration = (int)($_POST['duration'] ?? 0);
    $calories = (int)($_POST['calories_burn'] ?? 0);

    if (empty($name) || empty($category) || empty($difficulty) || $duration <= 0) {
        throw new Exception('Please fill all required fields');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO workouts (
            name, description, category, difficulty, 
            duration, calories_burn, created_by
        ) VALUES (
            :name, :description, :category, :difficulty,
            :duration, :calories, :created_by
        )
    ");

    $stmt->execute([
        'name' => $name,
        'description' => $description,
        'category' => $category,
        'difficulty' => $difficulty,
        'duration' => $duration,
        'calories' => $calories,
        'created_by' => $_SESSION['admin_id']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Workout added successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
