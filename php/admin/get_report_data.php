<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Get date range parameters
    $range = isset($_GET['range']) ? $_GET['range'] : '7';
    $startDate = null;
    $endDate = null;

    if ($range === 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $startDate = $_GET['start_date'];
        $endDate = $_GET['end_date'];
    } else {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$range} days"));
    }

    // Get user growth data
    $userQuery = "
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users
        WHERE created_at BETWEEN :start_date AND :end_date
        GROUP BY DATE(created_at)
        ORDER BY date
    ";

    $stmt = $conn->prepare($userQuery);
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate . ' 23:59:59'
    ]);

    $userGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get workout engagement data
    $workoutQuery = "
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM workout_completions
        WHERE created_at BETWEEN :start_date AND :end_date
        GROUP BY DATE(created_at)
        ORDER BY date
    ";

    $stmt = $conn->prepare($workoutQuery);
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate . ' 23:59:59'
    ]);

    $workoutEngagement = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics for comparison
    $currentStats = getStatistics($conn, $startDate, $endDate);
    $previousStart = date('Y-m-d', strtotime($startDate . " -{$range} days"));
    $previousEnd = date('Y-m-d', strtotime($startDate . " -1 day"));
    $previousStats = getStatistics($conn, $previousStart, $previousEnd);

    // Format data for response
    $response = [
        'status' => 'success',
        'data' => [
            'user_growth' => [
                'labels' => array_map(function($item) {
                    return date('M d', strtotime($item['date']));
                }, $userGrowth),
                'values' => array_column($userGrowth, 'count')
            ],
            'workout_engagement' => [
                'labels' => array_map(function($item) {
                    return date('M d', strtotime($item['date']));
                }, $workoutEngagement),
                'values' => array_column($workoutEngagement, 'count')
            ],
            'statistics' => [
                [
                    'metric' => 'New Users',
                    'current' => $currentStats['new_users'],
                    'previous' => $previousStats['new_users']
                ],
                [
                    'metric' => 'Active Users',
                    'current' => $currentStats['active_users'],
                    'previous' => $previousStats['active_users']
                ],
                [
                    'metric' => 'Workout Completions',
                    'current' => $currentStats['workout_completions'],
                    'previous' => $previousStats['workout_completions']
                ],
                [
                    'metric' => 'Average Duration (mins)',
                    'current' => $currentStats['avg_duration'],
                    'previous' => $previousStats['avg_duration']
                ]
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_report_data: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching report data: ' . $e->getMessage()
    ]);
}

function getStatistics($conn, $startDate, $endDate) {
    // New users in period
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM users
        WHERE created_at BETWEEN :start_date AND :end_date
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate . ' 23:59:59']);
    $newUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Active users in period
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT user_id) as count
        FROM workout_completions
        WHERE created_at BETWEEN :start_date AND :end_date
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate . ' 23:59:59']);
    $activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Workout completions
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, COALESCE(AVG(duration), 0) as avg_duration
        FROM workout_completions
        WHERE created_at BETWEEN :start_date AND :end_date
    ");
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate . ' 23:59:59']);
    $workoutStats = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'new_users' => (int)$newUsers,
        'active_users' => (int)$activeUsers,
        'workout_completions' => (int)$workoutStats['count'],
        'avg_duration' => round($workoutStats['avg_duration'], 1)
    ];
}
