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

    // First, let's check what columns exist in the users table
    $stmt = $conn->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Base columns that we'll always select
    $selectColumns = ['id', 'username', 'email', 'created_at'];

    // Get pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';

    // Build query conditions
    $conditions = [];
    $params = [];

    if (!empty($search)) {
        $conditions[] = "(username LIKE :search OR email LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Determine sort order
    $orderBy = match($sort) {
        'oldest' => 'created_at ASC',
        'name' => 'username ASC',
        default => 'created_at DESC'
    };

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
    $stmt = $conn->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Get users with pagination
    $query = "
        SELECT " . implode(', ', $selectColumns) . "
        FROM users
        $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    
    // Bind parameters for conditions first
    $paramPosition = 1;
    foreach ($params as $key => $value) {
        $stmt->bindValue($paramPosition, $value);
        $paramPosition++;
    }
    
    // Bind limit and offset
    $stmt->bindValue($paramPosition, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramPosition + 1, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($users as &$user) {
        $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
    }

    echo json_encode([
        'status' => 'success',
        'data' => $users,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_users: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching users: ' . $e->getMessage()
    ]);
}
