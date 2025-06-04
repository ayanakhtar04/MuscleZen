<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();
    
    // Get category counts
    $categorySql = "
        SELECT 
            category,
            COUNT(*) as count
        FROM workouts
        GROUP BY category
    ";
    
    $stmt = $conn->query($categorySql);
    $categoryStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get completion stats
    $completionSql = "
        SELECT 
            DATE(completed_at) as date,
            COUNT(*) as count
        FROM workout_completions
        WHERE completed_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY DATE(completed_at)
        ORDER BY date
    ";
    
    $stmt = $conn->query($completionSql);
    $completionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'categories' => $categoryStats,
            'completions' => [
                'labels' => array_column($completionStats, 'date'),
                'values' => array_column($completionStats, 'count')
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_workout_stats: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching workout statistics'
    ]);
}
