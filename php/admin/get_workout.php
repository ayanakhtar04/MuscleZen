<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once 'admin_db_config.php';

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Check if workouts table exists with correct structure
    $tableCheck = $conn->query("SHOW TABLES LIKE 'workouts'")->rowCount();
    if ($tableCheck === 0) {
        // Create workouts table if it doesn't exist
        $conn->exec("
            CREATE TABLE IF NOT EXISTS workouts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                category ENUM('strength', 'cardio', 'flexibility', 'hiit') NOT NULL,
                difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL,
                duration INT NOT NULL,
                calories_burn INT,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    // Get workouts
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $workoutId = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($workoutId) {
        // Get specific workout
        $stmt = $conn->prepare("
            SELECT * FROM workouts 
            WHERE id = ?
        ");
        $stmt->execute([$workoutId]);
        $workouts = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Get all workouts with optional search
        $query = "SELECT * FROM workouts";
        $params = [];

        if (!empty($search)) {
            $query .= " WHERE name LIKE ? OR description LIKE ?";
            $params = ["%$search%", "%$search%"];
        }

        $query .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get statistics
    $stats = [
        'strength' => 0,
        'cardio' => 0,
        'flexibility' => 0,
        'hiit' => 0
    ];

    $statsQuery = "
        SELECT category, COUNT(*) as count 
        FROM workouts 
        GROUP BY category
    ";
    
    $statsStmt = $conn->query($statsQuery);
    while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($stats[$row['category']])) {
            $stats[$row['category']] = (int)$row['count'];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => $workouts ?? [],
        'stats' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error in get_workout.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server Error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
