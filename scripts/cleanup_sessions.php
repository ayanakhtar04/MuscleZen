<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../php/utils/logger.php';

class SessionCleaner {
    private $session_dir;
    private $max_lifetime;
    
    public function __construct() {
        $this->session_dir = session_save_path() ?: '/tmp';
        $this->max_lifetime = ini_get('session.gc_maxlifetime') ?: 1440;
    }
    
    public function cleanup() {
        $count = 0;
        $now = time();
        
        foreach (glob($this->session_dir . "/sess_*") as $file) {
            if (is_file($file) && ($now - filemtime($file) > $this->max_lifetime)) {
                unlink($file);
                $count++;
            }
        }
        
        $message = "Cleaned up $count expired sessions";
        echo $message . "\n";
        Logger::info($message);
    }
    
    public function analyzeSessionData() {
        $sessions = 0;
        $total_size = 0;
        
        foreach (glob($this->session_dir . "/sess_*") as $file) {
            $sessions++;
            $total_size += filesize($file);
        }
        
        $report = "Session Analysis:\n";
        $report .= "Total Sessions: $sessions\n";
        $report .= "Total Size: " . round($total_size / 1024, 2) . " KB\n";
        $report .= "Average Size: " . round($total_size / max(1, $sessions) / 1024, 2) . " KB\n";
        
        echo $report;
        Logger::info($report);
    }
}

// Run cleanup
$cleaner = new SessionCleaner();
$cleaner->analyzeSessionData();
$cleaner->cleanup();
?>
