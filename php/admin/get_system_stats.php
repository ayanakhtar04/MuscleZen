<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Get system statistics
    $stats = [
        'server' => getServerStats(),
        'database' => getDatabaseStats($conn),
        'application' => getApplicationStats($conn)
    ];

    echo json_encode([
        'status' => 'success',
        'data' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error in get_system_stats: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve system statistics'
    ]);
}

function getServerStats() {
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'memory_usage' => memory_get_usage(true),
        'memory_limit' => ini_get('memory_limit'),
        'max_upload_size' => ini_get('upload_max_filesize'),
        'max_execution_time' => ini_get('max_execution_time'),
        'disk_free_space' => disk_free_space('/'),
        'disk_total_space' => disk_total_space('/')
    ];
}

function getDatabaseStats($conn) {
    // Get database size
    $stmt = $conn->query("
        SELECT 
            table_schema AS 'database',
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb'
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        GROUP BY table_schema
    ");
    $dbSize = $stmt->fetch()['size_mb'] ?? 0;

    // Get table counts
    $stmt = $conn->query("
        SELECT COUNT(*) as table_count 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ");
    $tableCount = $stmt->fetch()['table_count'];

    return [
        'version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION),
        'database_size' => $dbSize,
        'table_count' => $tableCount,
        'connection_count' => getDbConnectionCount($conn)
    ];
}

function getApplicationStats($conn) {
    // Get storage usage for uploads
    $uploadStats = [
        'images' => getDirSize('../../uploads/images'),
        'videos' => getDirSize('../../uploads/videos')
    ];

    // Get cache stats if using file cache
    $cacheStats = [
        'size' => getDirSize('../../cache'),
        'files' => count(glob('../../cache/*'))
    ];

    // Get session stats
    $sessionStats = [
        'active_sessions' => countActiveSessions($conn),
        'session_files' => count(glob(session_save_path() . '/*'))
    ];

    return [
        'upload_stats' => $uploadStats,
        'cache_stats' => $cacheStats,
        'session_stats' => $sessionStats,
        'error_log_size' => filesize('../../logs/error.log')
    ];
}

function getDirSize($dir) {
    if (!is_dir($dir)) {
        return 0;
    }

    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function getDbConnectionCount($conn) {
    try {
        $stmt = $conn->query("SHOW STATUS WHERE Variable_name = 'Threads_connected'");
        $result = $stmt->fetch();
        return $result['Value'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function countActiveSessions($conn) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT session_id) as count 
        FROM user_sessions 
        WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute();
    return $stmt->fetch()['count'] ?? 0;
}
