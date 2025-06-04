<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../php/utils/logger.php';

class SystemMonitor {
    private $metrics = [];
    
    public function checkSystem() {
        // Check CPU usage
        $load = sys_getloadavg();
        $this->metrics['cpu_load'] = $load[0];

        // Check memory usage
        $free = shell_exec('free');
        $free = (string)trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        $this->metrics['memory_usage'] = round($mem[2]/$mem[1]*100, 2);

        // Check disk usage
        $disk_free = disk_free_space("/");
        $disk_total = disk_total_space("/");
        $this->metrics['disk_usage'] = round(($disk_total - $disk_free) / $disk_total * 100, 2);

        // Check MySQL status
        try {
            $db = new PDO("mysql:host=localhost;dbname=gym_db", "root", "Mustafa786.");
            $this->metrics['database_status'] = 'OK';
            
            // Check slow queries
            $stmt = $db->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->metrics['slow_queries'] = $result['Value'];
            
        } catch (PDOException $e) {
            $this->metrics['database_status'] = 'ERROR: ' . $e->getMessage();
        }

        return $this->metrics;
    }

    public function checkApplicationStatus() {
        // Check error logs
        $error_log = file_get_contents(__DIR__ . '/../logs/error.log');
        $error_count = substr_count($error_log, 'ERROR');
        $this->metrics['error_count_24h'] = $error_count;

        // Check active users
        try {
            $db = new PDO("mysql:host=localhost;dbname=gym_db", "root", "Mustafa786.");
            $stmt = $db->query("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->metrics['active_users_24h'] = $result['count'];
        } catch (PDOException $e) {
            $this->metrics['active_users_24h'] = 'ERROR';
        }

        return $this->metrics;
    }

    public function generateReport() {
        $this->checkSystem();
        $this->checkApplicationStatus();

        $report = "System Status Report - " . date('Y-m-d H:i:s') . "\n";
        $report .= "=====================================\n\n";

        foreach ($this->metrics as $key => $value) {
            $report .= sprintf("%-20s: %s\n", ucwords(str_replace('_', ' ', $key)), $value);
        }

        // Log report
        Logger::info($report);

        // Send alert if metrics exceed thresholds
        $this->checkAlerts();

        return $report;
    }

    private function checkAlerts() {
        $alerts = [];

        if ($this->metrics['cpu_load'] > 80) {
            $alerts[] = "High CPU usage: {$this->metrics['cpu_load']}%";
        }

        if ($this->metrics['memory_usage'] > 90) {
            $alerts[] = "High memory usage: {$this->metrics['memory_usage']}%";
        }

        if ($this->metrics['disk_usage'] > 90) {
            $alerts[] = "High disk usage: {$this->metrics['disk_usage']}%";
        }

        if ($this->metrics['error_count_24h'] > 100) {
            $alerts[] = "High error count: {$this->metrics['error_count_24h']} in last 24h";
        }

        if (!empty($alerts)) {
            // Send email alert
            require_once __DIR__ . '/../php/utils/mailer.php';
            $mailer = new Mailer();
            $mailer->sendAlert(implode("\n", $alerts));
        }
    }
}

// Run monitor
$monitor = new SystemMonitor();
echo $monitor->generateReport();
?>
