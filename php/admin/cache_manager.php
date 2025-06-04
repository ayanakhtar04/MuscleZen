<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';
require_once '../utils/Cache.php';

try {
    AdminAuth::requireAdmin();
    
    $action = $_POST['action'] ?? 'status';
    $cacheType = $_POST['cache_type'] ?? 'all';

    switch ($action) {
        case 'status':
            $result = getCacheStatus($cacheType);
            break;
        case 'clear':
            $result = clearCache($cacheType);
            break;
        case 'optimize':
            $result = optimizeCache($cacheType);
            break;
        case 'warmup':
            $result = warmupCache();
            break;
        default:
            throw new Exception('Invalid action');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);

} catch (Exception $e) {
    error_log("Error in cache_manager: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function getCacheStatus($type) {
    $cacheRoot = '../../cache/';
    $status = [];

    switch ($type) {
        case 'all':
        case 'page':
            $status['page'] = [
                'size' => folderSize($cacheRoot . 'pages'),
                'files' => count(glob($cacheRoot . 'pages/*')),
                'hit_rate' => getCacheHitRate('page')
            ];
            if ($type === 'page') break;
            
        case 'api':
            $status['api'] = [
                'size' => folderSize($cacheRoot . 'api'),
                'files' => count(glob($cacheRoot . 'api/*')),
                'hit_rate' => getCacheHitRate('api')
            ];
            if ($type === 'api') break;
            
        case 'image':
            $status['image'] = [
                'size' => folderSize($cacheRoot . 'images'),
                'files' => count(glob($cacheRoot . 'images/*')),
                'hit_rate' => getCacheHitRate('image')
            ];
            break;
    }

    return $status;
}

function clearCache($type) {
    $cacheRoot = '../../cache/';
    $cleared = [];

    switch ($type) {
        case 'all':
            $cleared['page'] = clearCacheDirectory($cacheRoot . 'pages');
            $cleared['api'] = clearCacheDirectory($cacheRoot . 'api');
            $cleared['image'] = clearCacheDirectory($cacheRoot . 'images');
            break;
        case 'page':
        case 'api':
        case 'image':
            $cleared[$type] = clearCacheDirectory($cacheRoot . $type . 's');
            break;
    }

    logCacheOperation('clear', $type);
    return $cleared;
}

function optimizeCache($type) {
    $cacheRoot = '../../cache/';
    $optimized = [];

    switch ($type) {
        case 'all':
        case 'page':
            $optimized['page'] = removeExpiredCache($cacheRoot . 'pages');
            if ($type === 'page') break;
            
        case 'api':
            $optimized['api'] = removeExpiredCache($cacheRoot . 'api');
            if ($type === 'api') break;
            
        case 'image':
            $optimized['image'] = optimizeImageCache($cacheRoot . 'images');
            break;
    }

    logCacheOperation('optimize', $type);
    return $optimized;
}

function warmupCache() {
    $warmedUp = [
        'pages' => warmupPageCache(),
        'api' => warmupAPICache()
    ];

    logCacheOperation('warmup', 'all');
    return $warmedUp;
}

function clearCacheDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    return [
        'cleared_files' => count($files),
        'directory' => $dir
    ];
}

function removeExpiredCache($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $removed = 0;
    $files = glob($dir . '/*');
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $content = json_decode(file_get_contents($file), true);
            if ($content && isset($content['expires']) && $content['expires'] < time()) {
                unlink($file);
                $removed++;
            }
        }
    }

    return [
        'removed_files' => $removed,
        'total_files' => count($files)
    ];
}

function optimizeImageCache($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $optimized = 0;
    $files = glob($dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    
    foreach ($files as $file) {
        if (filesize($file) > 1024 * 1024) { // Files larger than 1MB
            $image = imagecreatefromstring(file_get_contents($file));
            if ($image) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                switch ($ext) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg($image, $file, 85);
                        break;
                    case 'png':
                        imagepng($image, $file, 7);
                        break;
                    case 'gif':
                        imagegif($image, $file);
                        break;
                }
                imagedestroy($image);
                $optimized++;
            }
        }
    }

    return [
        'optimized_files' => $optimized,
        'total_files' => count($files)
    ];
}

function warmupPageCache() {
    $pages = [
        'index.html',
        'dashboard.html',
        'workouts.html',
        'nutrition.html'
    ];

    $warmedUp = 0;
    foreach ($pages as $page) {
        $content = @file_get_contents('../../' . $page);
        if ($content) {
            $cacheKey = md5($page);
            $cachePath = '../../cache/pages/' . $cacheKey;
            file_put_contents($cachePath, json_encode([
                'content' => $content,
                'expires' => time() + 3600,
                'created_at' => time()
            ]));
            $warmedUp++;
        }
    }

    return [
        'warmed_up_pages' => $warmedUp,
        'total_pages' => count($pages)
    ];
}

function warmupAPICache() {
    $endpoints = [
        'get_dashboard_stats.php',
        'get_workout_stats.php',
        'get_content_stats.php'
    ];

    $warmedUp = 0;
    foreach ($endpoints as $endpoint) {
        $response = @file_get_contents('../admin/' . $endpoint);
        if ($response) {
            $cacheKey = md5($endpoint);
            $cachePath = '../../cache/api/' . $cacheKey;
            file_put_contents($cachePath, json_encode([
                'response' => $response,
                'expires' => time() + 1800,
                'created_at' => time()
            ]));
            $warmedUp++;
        }
    }

    return [
        'warmed_up_endpoints' => $warmedUp,
        'total_endpoints' => count($endpoints)
    ];
}

function getCacheHitRate($type) {
    $logFile = "../../logs/cache_{$type}_hits.log";
    if (!file_exists($logFile)) {
        return 0;
    }

    $logs = file($logFile);
    $hits = 0;
    $total = count($logs);

    foreach ($logs as $log) {
        if (strpos($log, 'HIT') !== false) {
            $hits++;
        }
    }

    return $total > 0 ? ($hits / $total) * 100 : 0;
}

function folderSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : folderSize($each);
    }
    return $size;
}

function logCacheOperation($operation, $type) {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, details, ip_address)
        VALUES (:admin_id, :action, :details, :ip_address)
    ");

    $stmt->execute([
        ':admin_id' => $_SESSION['admin_id'],
        ':action' => 'cache_' . $operation,
        ':details' => json_encode([
            'cache_type' => $type,
            'timestamp' => date('Y-m-d H:i:s')
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}
