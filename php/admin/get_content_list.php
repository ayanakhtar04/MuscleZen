<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized access');
    }

    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    // Get parameters
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $reported = isset($_GET['reported']) ? true : false;
    $contentId = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // Base query
    $query = "
        SELECT 
            c.*,
            u.username as author_name
        FROM content c
        LEFT JOIN users u ON c.author_id = u.id
        WHERE 1=1
    ";
    $params = [];

    // Add filters
    if ($contentId) {
        $query .= " AND c.id = :content_id";
        $params['content_id'] = $contentId;
    }

    if ($type) {
        $query .= " AND c.type = :type";
        $params['type'] = $type;
    }

    if ($reported) {
        $query .= " AND c.is_reported = 1";
    }

    if ($search) {
        $query .= " AND (c.title LIKE :search OR c.content LIKE :search)";
        $params['search'] = "%$search%";
    }

    $query .= " ORDER BY c.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $content = $contentId ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($contentId && !$content) {
        throw new Exception('Content not found');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $content
    ]);

} catch (Exception $e) {
    error_log("Error in get_content_list: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error loading content: ' . $e->getMessage()
    ]);
}
