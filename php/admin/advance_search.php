<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';
require_once '../utils/validation.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $searchType = $_GET['type'] ?? 'users';
    $query = $_GET['query'] ?? '';
    $filters = json_decode($_GET['filters'] ?? '{}', true);
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'DESC';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = intval($_GET['limit'] ?? 20);

    $results = performSearch($conn, $searchType, $query, $filters, $sort, $order, $page, $limit);

    echo json_encode([
        'status' => 'success',
        'data' => $results
    ]);

} catch (Exception $e) {
    error_log("Error in advanced_search: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function performSearch($conn, $type, $query, $filters, $sort, $order, $page, $limit) {
    $offset = ($page - 1) * $limit;
    $whereConditions = [];
    $params = [];

    // Base query based on type
    switch ($type) {
        case 'users':
            return searchUsers($conn, $query, $filters, $sort, $order, $offset, $limit);
        case 'workouts':
            return searchWorkouts($conn, $query, $filters, $sort, $order, $offset, $limit);
        case 'content':
            return searchContent($conn, $query, $filters, $sort, $order, $offset, $limit);
        case 'activities':
            return searchActivities($conn, $query, $filters, $sort, $order, $offset, $limit);
        default:
            throw new Exception('Invalid search type');
    }
}

function searchUsers($conn, $query, $filters, $sort, $order, $offset, $limit) {
    $whereConditions = [];
    $params = [];

    if (!empty($query)) {
        $whereConditions[] = "(username LIKE ? OR email LIKE ? OR name LIKE ?)";
        $queryParam = "%$query%";
        array_push($params, $queryParam, $queryParam, $queryParam);
    }

    // Apply filters
    if (!empty($filters)) {
        if (isset($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['role'])) {
            $whereConditions[] = "role = ?";
            $params[] = $filters['role'];
        }
        if (isset($filters['date_range'])) {
            $whereConditions[] = "created_at BETWEEN ? AND ?";
            array_push($params, $filters['date_range']['start'], $filters['date_range']['end']);
        }
    }

    $where = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    // Count total results
    $countSql = "SELECT COUNT(*) as total FROM users $where";
    $stmt = $conn->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Get paginated results
    $sql = "
        SELECT 
            id, username, email, role, status, created_at,
            (SELECT COUNT(*) FROM workout_schedules WHERE user_id = users.id) as workout_count,
            (SELECT COUNT(*) FROM posts WHERE user_id = users.id) as post_count
        FROM users 
        $where 
        ORDER BY $sort $order 
        LIMIT ? OFFSET ?
    ";
    
    array_push($params, $limit, $offset);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    return [
        'total' => $total,
        'pages' => ceil($total / $limit),
        'current_page' => floor($offset / $limit) + 1,
        'results' => $results
    ];
}

function searchWorkouts($conn, $query, $filters, $sort, $order, $offset, $limit) {
    $whereConditions = [];
    $params = [];

    if (!empty($query)) {
        $whereConditions[] = "(wp.name LIKE ? OR wp.description LIKE ?)";
        $queryParam = "%$query%";
        array_push($params, $queryParam, $queryParam);
    }

    ifHere's the completion of the system_health.php file with the remaining (!empty($filters)) {
        if (isset($filters['category functions:

```php
    return $stmt->fetch(PDO'])) {
            $whereConditions[] = "wp.category::FETCH_ASSOC)['size_mb'];
} = ?";
            $params[] = $filters

function checkFilePermissions() {
    $crit['category'];
        }
        if (isset($filters['difficulty'])) {
            icalPaths = [
        '../../uploads',
        '../../logs$whereConditions[] = "wp.difficulty = ?";
            $params[] = $filters['difficulty'];
        }
    ',
        '../../cache',
        '../../config}

    $where = !empty($where'
    ];
    
    $permissions = [];Conditions) ? "WHERE " . implode(" AND ", $where
    foreach ($criticalPaths as $path) {
        if (file_exists($path)) {
            $permissionsConditions) : "";
    
    // Count[$path] = [
                'readable' => is_readable($path),
                'writable' => is_writable($path total results
    $countSql = "
        SELECT COUNT(*) as total 
        FROM workout_plans wp 
        $where
    ";
    $stmt = $conn->prepare),
                'mode' => substr(sprintf('%o', fileper($countSql);
    $stmt->ms($path)), -4)
            ];execute($params);
    $total = $stmt->fetch()['
        }
    }
    return $permissions;
}

function getQueryCacheSize($conn) {
    total'];

    // Get paginated results
    $sql = "$stmt = $conn->query("SHOW
        SELECT 
            wp.*,
            (SELECT COUNT(*) FROM workout_schedules ws WHERE ws.workout_ VARIABLES LIKE 'query_cache_size'");
    returnid = wp.id) as schedule_count, $stmt->fetch(PDO::FETCH_ASSOC)['Value'];
}
            (SELECT COUNT(*) FROM workout_schedules ws WHERE ws.workout_id = wp.id AND ws.status = 

function getQueryCacheEfficiency($conn) {
    $stmt = $conn->query("
        SHOW STATUS LIKE 'Qcache%'
    ");
    $cache ='completed') as completion_count
        FROM workout_plans wp
        $where 
        ORDER BY $ [];
    while ($row = $stmt->fetchsort $order 
        LIMIT ?(PDO::FETCH_ASSOC)) {
        $cache[$row['Variable OFFSET ?
    ";
    
    array_push($params, $limit, $_name']] = $row['Value'];offset);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    }
    
    $hits = $cache['Qcache_hits'] ?? 0;
    $total = $hits + ($cache['Qc

    return [
        'total' => $ache_inserts'] ?? 0);total,
        'pages' => ceil($total / $limit),
    return $total > 0 ? ($
        'current_page' => floor($offset / $limit) + 1,hits / $total) * 100 : 0;
}

function getSlow
        'results' => $results
    ];
}

function searchQueries($conn) {
    $stmt = $conn->query("SHOW GLOBAL STATUS LIKE 'Content($conn, $query, $filters, $sort, $order, $offset, $limit) {
    Slow_queries'");
    return $stmt->fetch(PDO::FETCH_$whereConditions = [];
    $params = [];

    if (!empty($query)) {
        $whereConditions[] = "(ASSOC)['Value'];
}

function getAverageResponseTimes() {p.content LIKE ? OR p.title LIKE ?)";
        $queryParam = "%$query%
    $logFile = '../../logs/response_times.log';
    if (!file_exists($logFile)) {
        return 0;
    }";
        array_push($params, $queryParam, $queryParam);
    }

    if (!empty($filters)) {
        if (isset($filters
    
    $times = array_map('floatval', file($logFile));
    return['type'])) {
            $whereConditions[] count($times) > 0 ? array_sum($times) / count($times) : = "p.type = ?";
            $params[] = $filters['type']; 0;
}

function fixDatabaseIssues($conn) {
    $
        }
        if (isset($filters['status'])) {fixed = [];
    
    // Fix tables
            $whereConditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
    $stmt = $conn->query("SHOW TABLES");
    $
    }

    $where = !empty($wheretables = $stmt->fetchAll(PDOConditions) ? "WHERE " . implode(" AND ", $where::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $conn->query("ANALYZE TABLE $table");
        $connConditions) : "";
    
    // Count total results
    $countSql = "SELECT->query("OPTIMIZE TABLE $table"); COUNT(*) as total FROM posts p $where";
    $stmt
        $fixed['optimized_tables'][] = $ = $conn->prepare($countSql);
    $stmt->table;
    }
    
    // Reset query cache if efficiency is low
    if (getQueryCacheEfficiency($conn) execute($params);
    $total = $< 30) {
        $conn->query("stmt->fetch()['total'];

    // Get paginated results
    $sql = "
        SELECT 
            p.*,
            u.username asRESET QUERY CACHE");
        $fixe author,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.d['reset_query_cache'] = true;
    }
    
    return $fixed;
}

function fixStorid) as comment_count,
            (SELECT COUNTageIssues() {
    $fixed = [];
    $uploadDirs = ['images', 'videos(*) FROM post_likes pl WHERE pl.post', 'temp'];
    
    foreach ($uploadDirs as_id = p.id) as like_count
        FROM posts p
        LEFT JOIN users u ON p. $dir) {
        $path = "../../uploadsuser_id = u.id
        $where 
        ORDER BY $sort $order /$dir";
        if (!is_dir($path
        LIMIT ? OFFSET ?
    ";
    
    array)) {
            mkdir($path, 0755, true);
            $fixed['created_directories'][] = $dir_push($params, $limit, $offset);
    $stmt;
        }
        
        // Fix permissions if needed
        if (!is_writable($path)) {
            chmod($path, 0755);
            $fixed['fixe = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->d_permissions'][] = $dir;
        fetchAll();

    return [
        'total' => $total}
    }
    
    // Clean old temp files
    $tempFiles,
        'pages' => ceil($total / $limit),
        'current_page' => floor($offset / $limit) + 1,
        'results' => $ = glob("../../uploads/temp/*");
    foreach ($tempFiles as $file) {results
    ];
}

function searchActivities($conn, $query, $filters,
        if (filemtime($file) < strtotime('-24 hours')) {
            unlink($file);
            $fixe $sort, $order, $offset, $limit) {
    d['cleaned_temp_files'][] = basename($file);
        $whereConditions = [];
    $params}
    }
    
    return $ = [];

    if (!empty($query)) {
        $whereConditions[] = "(fixed;
}

function fixSecurityIssues() {
    $fixed = [];al.action LIKE ? OR al.details LIKE ?)";
        $queryParam = "%$query%";
        array_
    
    // Ensure secure PHP settings
    if (ini_get('display_errors')) {
        ini_set('display_push($params, $queryParam, $queryParam);
    }

    if (!emptyerrors', 0);
        $fixed['($filters)) {
        if (isset($disabled_error_display'] = true;
    }
    filters['action_type'])) {
            $whereConditions[] = "al.action = ?";
            $
    if (!ini_get('session.cookie_secure'))params[] = $filters['action_type']; {
        ini_set('session.cookie_secure', 1);
        $fixed['enable
        }
        if (isset($filters['date_range'])) {d_secure_cookies'] = true;
    }
    
    // Fix file permissions
    $secur
            $whereConditions[] = "al.created_at BETWEEN ? AND ?";ePaths = [
        '../../config' => 0755,
        '../../
            array_push($params, $filters['date_range']['start'], $filters['date_range']['end']);
        }logs' => 0755,
        '../../
    }

    $where = !empty($uploads' => 0755
    ];
    whereConditions) ? "WHERE " . implode(" AND ", $
    foreach ($securePaths as $path => $mode) {
        if (file_exists($path) && (fileperms($path) & 0777)whereConditions) : "";
    
    // Count total results
    $countSql !== $mode) {
            chmod($path, = "SELECT COUNT(*) as total FROM admin_activity $mode);
            $fixed['fixed__log al $where";
    $stmt = $permissions'][] = $path;
        }
    }
    conn->prepare($countSql);
    $stmt->execute($params);
    $
    return $fixed;
}

function fixPerformanceIssues($conn) {
    $fixetotal = $stmt->fetch()['total'];

    // Get paginated results
    $sql = "
        SELECT 
            al.*,
            au.username as admind = [];
    
    // Optimize MySQL settings
    $optimizations = [
        'query_cache_type' => 1_username
        FROM admin_activity_log al,
        'query_cache_size' => 268
        LEFT JOIN admin_users au ON al435456, // 256MB
        'max.admin_id = au.id
        $where 
        ORDER BY $sort $order 
        LIMIT ?_connections' => 150
    ];
     OFFSET ?
    ";
    
    array_push($params, $limit, $offset
    foreach ($optimizations as $variable => $value) {
        $conn->query("SET GLOBAL $variable =);
    $stmt = $conn->prepare $value");
        $fixed['mysql_optimizations'][] = $variable;
    }
    
    // Clear($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    return [
        'total' => $total,
        'pages old logs
    $logFiles = glob('../../logs/*.' => ceil($total / $limit),log');
    foreach ($logFiles as $log) {
        if (filesize($log) >
        'current_page' => floor($offset 10485760) { // 10MB
             / $limit) + 1,
        'results' => $results
    ];file_put_contents($log, '');
            $fixed['trunc
}
