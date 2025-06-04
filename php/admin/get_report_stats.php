<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $range = $_GET['range'] ?? '7';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    if ($range === 'custom' && $startDate && $endDate) {
        $dateCondition = "BETWEEN '$startDate' AND '$endDate'";
    } else {
        $dateCondition = ">= DATE_SUB(CURRENT_DATE, INTERVAL $range DAY)";
    }

    // Get user growth data
    $stmt = $conn->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at $dateCondition 
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $userGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get workout engagement data
    $stmt = $conn->query("
        SELECT DATE(completed_at) as date, COUNT(*) as count 
        FROM workout_schedules 
        WHERE status = 'completed' 
        AND completed_at $dateCondition 
        GROUP BY DATE(completed_at)
        ORDER BY date
    ");
    $workoutEngagement = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics comparisons
    $currentStats = getStats($conn, $range, 0);
    $previousStats = getStats($conn, $range, $range);

    $statistics = calculateComparisons($currentStats, $previousStats);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'user_growth' => formatChartData($userGrowth),
            'workout_engagement' => formatChartData($workoutEngagement),
            'statistics' => $statistics
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_report_data: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load report data'
    ]);
}

function getStats($conn, $days, $offset) {
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users 
             WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
             AND created_at < DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)) as new_users,
            (SELECT COUNT(*) FROM workout_schedules 
             WHERE completed_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
             AND completed_at < DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
             AND status = 'completed') as completed_workouts,
            (SELECT COUNT(DISTINCT user_id) FROM workout_schedules 
             WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
             AND created_at < DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)) as active_users
    ");
    
    $stmt->execute([
        $days + $offset, $offset,
        $days + $offset, $offset,
        $days + $offset, $offset
    ]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateComparisons($current, $previous) {
    $stats = [];
    
    foreach ($current as $key => $value) {
        $prevValue = $previous[$key] ?: 1; // Avoid division by zero
        $change = (($value - $prevValue) / $prevValue) * 100;
        
        $stats[] = [
            'metric' => ucwords(str_replace('_', ' ', $key)),
            'current' => $value,
            'previous' => $previous[$key],
            'change' => round($change, 1)
        ];
    }
    
    return $stats;
}

function formatChartData($data) {
    $formatted = [
        'labels' => [],
        'values' => []
    ];
    
    foreach ($data as $row) {
        $formatted['labels'][] = $row['date'];
        $formatted['values'][] = $row['count'];
    }
    
    return $formatted;
}
