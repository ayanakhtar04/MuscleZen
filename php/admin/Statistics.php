<?php
class Statistics {
    private $db;
    
    public function __construct() {
        $this->db = AdminDatabase::getInstance()->getConnection();
    }
    
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Get user stats
            $stats['users'] = $this->getUserStats();
            
            // Get workout stats
            $stats['workouts'] = $this->getWorkoutStats();
            
            // Get content stats
            $stats['content'] = $this->getContentStats();
            
            // Get engagement stats
            $stats['engagement'] = $this->getEngagementStats();
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting dashboard stats: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function getUserStats() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_users,
                COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as active_users
            FROM users
        ");
        return $stmt->fetch();
    }
    
    private function getWorkoutStats() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_workouts,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_workouts,
                AVG(duration) as avg_duration
            FROM workouts
            WHERE status = 'active'
        ");
        return $stmt->fetch();
    }
    
    private function getContentStats() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_content,
                COUNT(CASE WHEN type = 'post' THEN 1 END) as total_posts,
                COUNT(CASE WHEN type = 'video' THEN 1 END) as total_videos,
                COUNT(CASE WHEN type = 'image' THEN 1 END) as total_images,
                COUNT(CASE WHEN is_reported = 1 THEN 1 END) as reported_content
            FROM content
        ");
        return $stmt->fetch();
    }
    
    private function getEngagementStats() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_completions,
                AVG(duration) as avg_completion_time,
                AVG(rating) as avg_rating
            FROM workout_completions
            WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        return $stmt->fetch();
    }
    
    public function getTimelineStats($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM users
                WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            
            $stmt->execute(['days' => $days]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting timeline stats: " . $e->getMessage());
            throw $e;
        }
    }
}
