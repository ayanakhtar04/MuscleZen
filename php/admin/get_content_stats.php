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

    // Check if content table exists, create if it doesn't
    $tableExists = $conn->query("SHOW TABLES LIKE 'content'")->rowCount() > 0;
    if (!$tableExists) {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                type ENUM('post', 'video', 'image') NOT NULL,
                category VARCHAR(50) NOT NULL,
                media_url VARCHAR(255),
                author_id INT NOT NULL,
                status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
                is_reported BOOLEAN DEFAULT FALSE,
                views INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    // Get content statistics
    $stats = [
        'posts' => 0,
        'videos' => 0,
        'images' => 0,
        'reported' => 0
    ];

    // Count by type
    $typeQuery = "
        SELECT 
            type,
            COUNT(*) as count
        FROM content
        GROUP BY type
    ";
    $typeStmt = $conn->query($typeQuery);
    while ($row = $typeStmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['type'] . 's'] = (int)$row['count'];
    }

    // Count reported content
    $reportedQuery = "
        SELECT COUNT(*) as count
        FROM content
        WHERE is_reported = 1
    ";
    $reportedStmt = $conn->query($reportedQuery);
    $stats['reported'] = (int)$reportedStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Insert sample content if none exists
    if (array_sum(array_slice($stats, 0, 3)) === 0) {
        $sampleContent = [
            [
                'title' => 'Getting Started with Fitness',
                'content' => 'A comprehensive guide for beginners...',
                'type' => 'post',
                'category' => 'fitness',
                'status' => 'published'
            ],
            [
                'title' => 'Basic Workout Routine',
                'content' => 'Learn the fundamental exercises...',
                'type' => 'video',
                'category' => 'workout',
                'status' => 'published'
            ],
            [
                'title' => 'Proper Form Guide',
                'content' => 'Visual guide for exercise forms...',
                'type' => 'image',
                'category' => 'tutorial',
                'status' => 'published'
            ]
        ];

        $insertStmt = $conn->prepare("
            INSERT INTO content (
                title, content, type, category, author_id, status
            ) VALUES (
                :title, :content, :type, :category, :author_id, :status
            )
        ");

        foreach ($sampleContent as $content) {
            $insertStmt->execute([
                'title' => $content['title'],
                'content' => $content['content'],
                'type' => $content['type'],
                'category' => $content['category'],
                'author_id' => $_SESSION['admin_id'],
                'status' => $content['status']
            ]);
        }

        // Update stats after inserting sample content
        $stats['posts'] = 1;
        $stats['videos'] = 1;
        $stats['images'] = 1;
    }

    echo json_encode([
        'status' => 'success',
        'stats' => $stats
    ]);

} catch (Exception $e) {
    error_log("Error in get_content_stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error loading content stats: ' . $e->getMessage()
    ]);
}
