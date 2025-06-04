<?php
class Analytics {
    private $db;
    
    public function __construct() {
        $this->db = AdminDatabase::getInstance()->getConnection();
    }
    
    public function getUserAnalytics($startDate = null, $endDate = null) {
        try {
            if (!$startDate) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$endDate) {
                $endDate = date('Y-m-d');
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as new_users,
                    COUNT(DISTINCT CASE WHEN last_login >= :start_date THEN id END) as active_users
                FROM users
                WHERE created_at BETWEEN :start_date AND :end_date
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting user analytics: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getWorkoutAnalytics($startDate = null, $endDate = null) {
        try {
            if (!$startDate) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$endDate) {
                $endDate = date('Y-m-d');
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    w.category,
                    COUNT(wc.id) as completions,
                    AVG(wc.duration) as avg_duration,
                    AVG(wc.rating) as avg_rating
                FROM workouts w
                LEFT JOIN workout_completions wc ON w.id = wc.workout_id
                WHERE wc.completed_at BETWEEN :start_date AND :end_date
                GROUP BY w.category
            ");
            
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting workout analytics: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getContentAnalytics($startDate = null, $endDate = null) {
        try {
            if (!$startDate) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$endDate) {
                $endDate = date('Y-m-d');
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    type,
                    COUNT(*) as total,
                    SUM(views) as total_views,
                    AVG(views) as avg_views
                FROM content
                WHERE created_at BETWEEN :start_date AND :end_date
                GROUP BY type
            ");
            
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting content analytics: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getEngagementMetrics($days = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    'Workout Completions' as metric,
                    COUNT(*) as value
                FROM workout_completions
                WHERE completed_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                UNION ALL
                SELECT 
                    'Active Users',
                    COUNT(DISTINCT user_id)
                FROM user_activities
                WHERE activity_date >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                UNION ALL
                SELECT 
                    'Content Views',
                    SUM(views)
                FROM content
                WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
            ");
            
            $stmt->execute(['days' => $days]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting engagement metrics: " . $e->getMessage());
            throw $e;
        }
    }
}
