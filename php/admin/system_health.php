<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $action = $_GET['action'] ?? 'check';
    
    switch ($action) {
        case 'check':
            $health = checkSystemHealth($conn);
            break;
        case 'fix':
            $health = fixSystemIssues($conn);
            break;
        case 'optimize':
            $health = optimizeSystem($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $health
    ]);

} catch (Exception $e) {
    error_log("Error in system_health: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function checkSystemHealth($conn) {
    return [
        'system' => checkSystemMetrics(),
        'database' => checkDatabaseHealth($conn),
        'storage' => checkStorageHealth(),
        'security' => checkSecurityHealth(),
        'performance' => checkPerformanceMetrics($conn)
    ];
}

function checkSystemMetrics() {
    return [
        'memory_usage' => [
            'used' => memory_get_usage(true),
            'limit' => ini_get('memory_limit'),
            'status' => memory_get_usage(true) < getPHPMemoryLimit() ? 'healthy' : 'warning'
        ],
        'disk_space' => [
            'free' => disk_free_space('/'),
            'total' => disk_total_space('/'),
            'status' => (disk_free_space('/') / disk_total_space('/')) > 0.2 ? 'healthy' : 'warning'
        ],
        'php_version' => [
            'current' => PHP_VERSION,
            'recommended' => '7.4.0',
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'healthy' : 'warning'
        ]
    ];
}

function checkDatabaseHealth($conn) {
    // Check table status
    $stmt = $conn->query("SHOW TABLE STATUS");
    $tableStatus = $stmt->fetchAll();
    
    $dbHealth = [
        'tables' => [],
        'connections' => [
            'active' => getCurrentConnections($conn),
            'max' => getMaxConnections($conn)
        ],
        'storage' => getDatabaseSize($conn)
    ];

    foreach ($tableStatus as $table) {
        $dbHealth['tables'][$table['Name']] = [
            'rows' => $table['Rows'],
            'data_length' => $table['Data_length'],
            'index_length' => $table['Index_length'],
            'overhead' => $table['Data_free']
        ];
    }

    return $dbHealth;
}

function checkStorageHealth() {
    $uploadDirs = ['images', 'videos', 'temp'];
    $storageHealth = [];

    foreach ($uploadDirs as $dir) {
        $path = "../../uploads/$dir";
        if (is_dir($path)) {
            $storageHealth[$dir] = [
                'size' => folderSize($path),
                'file_count' => count(glob("$path/*")),
                'writable' => is_writable($path)
            ];
        }
    }

    return $storageHealth;
}

function checkSecurityHealth() {
    return [
        'ssl_enabled' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'php_errors_hidden' => !ini_get('display_errors'),
        'session_secure' => ini_get('session.cookie_secure'),
        'upload_max_size' => ini_get('upload_max_filesize'),
        'file_permissions' => checkFilePermissions()
    ];
}

function checkPerformanceMetrics($conn) {
    return [
        'query_cache' => [
            'size' => getQueryCacheSize($conn),
            'efficiency' => getQueryCacheEfficiency($conn)
        ],
        'slow_queries' => getSlowQueries($conn),
        'response_times' => getAverageResponseTimes()
    ];
}

function fixSystemIssues($conn) {
    $fixes = [
        'database' => fixDatabaseIssues($conn),
        'storage' => fixStorageIssues(),
        'security' => fixSecurityIssues(),
        'performance' => fixPerformanceIssues($conn)
    ];

    return [
        'fixed_issues' => $fixes,
        'current_health' => checkSystemHealth($conn)
    ];
}

function optimizeSystem($conn) {
    return [
        'database' => optimizeDatabase($conn),
        'cache' => optimizeCache(),
        'files' => cleanupFiles()
    ];
}

// Helper functions
function folderSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : folderSize($each);
    }
    return $size;
}

function getPHPMemoryLimit() {
    $memory_limit = ini_get('memory_limit');
    if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
        if ($matches[2] == 'M') {
            return $matches[1] * 1024 * 1024;
        } else if ($matches[2] == 'G') {
            return $matches[1] * 1024 * 1024 * 1024;
        }
    }
    return $memory_limit;
}

function getCurrentConnections($conn) {
    $stmt = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['Value'];
}

function getMaxConnections($conn) {
    $stmt = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['Value'];
}

function getDatabaseSize($conn) {
    $stmt = $conn->query("
        SELECT table_schema AS 'database',
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb'
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        GROUP BY table_schema
    ");
    return $stmt->fetch(PDO::FETCH_    ASSOC)['size_mb'];
}

function getQueryCacheSize($conn) {
    $stmt = $conn->query("SHOW VARIABLES LIKE 'query_cache_size'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['Value'];
}

function getQueryCacheEfficiency($conn) {
    $stmt = $conn->query("
        SHOW STATUS LIKE 'Qcache%'
    ");
    $cache = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cache[$row['Variable_name']] = $row['Value'];
    }
    
    if ($cache['Qcache_hits'] == 0) {
        return 0;
    }
    
    return ($cache['Qcache_hits'] / ($cache['Qcache_hits'] + $cache['Qcache_inserts'])) * 100;
}

function getSlowQueries($conn) {
    $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['Value'];
}

function getAverageResponseTimes() {
    $logFile = '../../logs/performance.log';
    if (!file_exists($logFile)) {
        return ['average' => 0, 'max' => 0, 'min' => 0];
    }

    $times = array_map('floatval', file($logFile));
    if (empty($times)) {
        return ['average' => 0, 'max' => 0, 'min' => 0];
    }

    return [
        'average' => array_sum($times) / count($times),
        'max' => max($times),
        'min' => min($times)
    ];
}

function checkFilePermissions() {
    $criticalDirs = [
        '../../uploads',
        '../../logs',
        '../../cache',
        '../../config'
    ];

    $permissions = [];
    foreach ($criticalDirs as $dir) {
        if (file_exists($dir)) {
            $permissions[$dir] = [
                'mode' => substr(sprintf('%o', fileperms($dir)), -4),
                'writable' => is_writable($dir),
                'owner' => posix_getpwuid(fileowner($dir))['name']
            ];
        }
    }

    return $permissions;
}

function fixDatabaseIssues($conn) {
    $fixed = [];

    // Repair and optimize tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt = $conn->query("CHECK TABLE `$table`");
        $result = $stmt->fetch();
        
        if ($result['Msg_text'] !== 'OK') {
            $conn->query("REPAIR TABLE `$table`");
            $fixed['repaired_tables'][] = $table;
        }

        $conn->query("OPTIMIZE TABLE `$table`");
        $fixed['optimized_tables'][] = $table;
    }

    // Reset query cache if efficiency is low
    if (getQueryCacheEfficiency($conn) < 30) {
        $conn->query("RESET QUERY CACHE");
        $fixed['query_cache'] = 'reset';
    }

    return $fixed;
}

function fixStorageIssues() {
    $fixed = [];

    // Clean temp directory
    $tempDir = '../../uploads/temp';
    if (is_dir($tempDir)) {
        $files = glob("$tempDir/*");
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 86400) {
                unlink($file);
                $fixed['deleted_temp_files'][] = basename($file);
            }
        }
    }

    // Clean old logs
    $logDir = '../../logs';
    if (is_dir($logDir)) {
        $logs = glob("$logDir/*.log");
        foreach ($logs as $log) {
            if ((time() - filemtime($log)) > (30 * 86400)) { // 30 days
                unlink($log);
                $fixed['deleted_logs'][] = basename($log);
            }
        }
    }

    return $fixed;
}

function fixSecurityIssues() {
    $fixed = [];

    // Fix file permissions
    $directories = [
        '../../uploads' => 0755,
        '../../logs' => 0755,
        '../../cache' => 0755,
        '../../config' => 0755
    ];

    foreach ($directories as $dir => $permission) {
        if (is_dir($dir) && (fileperms($dir) & 0777) !== $permission) {
            chmod($dir, $permission);
            $fixed['permissions'][] = $dir;
        }
    }

    // Ensure secure PHP settings
    $secureSettings = [
        'session.cookie_secure' => 1,
        'session.cookie_httponly' => 1,
        'session.use_only_cookies' => 1
    ];

    foreach ($secureSettings as $key => $value) {
        if (ini_get($key) != $value) {
            ini_set($key, $value);
            $fixed['php_settings'][] = $key;
        }
    }

    return $fixed;
}

function fixPerformanceIssues($conn) {
    $fixed = [];

    // Optimize MySQL settings
    $optimizations = [
        'query_cache_size' => '64M',
        'max_connections' => '150',
        'key_buffer_size' => '32M'
    ];

    foreach ($optimizations as $variable => $value) {
        $stmt = $conn->query("SHOW VARIABLES LIKE '$variable'");
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current['Value'] !== $value) {
            $conn->query("SET GLOBAL $variable = $value");
            $fixed['mysql_settings'][] = $variable;
        }
    }

    // Clear application cache
    if (clearApplicationCache()) {
        $fixed['cache'] = 'cleared';
    }

    return $fixed;
}

function optimizeDatabase($conn) {
    $results = [];

    // Analyze and optimize tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $conn->query("ANALYZE TABLE `$table`");
        $conn->query("OPTIMIZE TABLE `$table`");
        $results['optimized_tables'][] = $table;
    }

    // Update statistics
    $conn->query("FLUSH STATUS");
    $results['status'] = 'flushed';

    return $results;
}

function optimizeCache() {
    $results = [];

    // Clear PHP opcode cache
    if (function_exists('opcache_reset')) {
        opcache_reset();
        $results['opcache'] = 'cleared';
    }

    // Clear application cache
    $cacheDir = '../../cache';
    if (is_dir($cacheDir)) {
        $files = glob("$cacheDir/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $results['app_cache'] = 'cleared';
    }

    return $results;
}

function cleanupFiles() {
    $results = [];
    
    // Clean old session files
    $sessionPath = session_save_path();
    if (is_dir($sessionPath)) {
        $files = glob("$sessionPath/sess_*");
        foreach ($files as $file) {
            if ((time() - filemtime($file)) > 86400) {
                unlink($file);
                $results['deleted_sessions'][] = basename($file);
            }
        }
    }

    // Clean upload temp files
    $uploadTmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
    $files = glob("$uploadTmp/*");
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > 3600) {
            unlink($file);
            $results['deleted_temp_files'][] = basename($file);
        }
    }

    return $results;
}

function clearApplicationCache() {
    $cacheDir = '../../cache';
    if (!is_dir($cacheDir)) {
        return false;
    }

    $files = glob("$cacheDir/*");
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    return true;
}
?>

