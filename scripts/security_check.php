<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../php/utils/logger.php';

class SecurityChecker {
    private $issues = [];
    
    public function checkFilePermissions() {
        $sensitiveDirs = [
            '../php/config',
            '../logs',
            '../uploads'
        ];

        foreach ($sensitiveDirs as $dir) {
            if (file_exists($dir)) {
                $perms = substr(sprintf('%o', fileperms($dir)), -4);
                if ($perms > '0755') {
                    $this->issues[] = "Directory permissions too permissive: $dir ($perms)";
                }
            }
        }
    }

    public function checkConfigurationFiles() {
        $files = [
            '../.env',
            '../php/config/app_config.php',
            '../php/db_config.php'
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $perms = substr(sprintf('%o', fileperms($file)), -4);
                if ($perms > '0644') {
                    $this->issues[] = "File permissions too permissive: $file ($perms)";
                }
            } else {
                $this->issues[] = "Missing configuration file: $file";
            }
        }
    }

    public function checkDatabaseSecurity() {
        try {
            $db = new PDO("mysql:host=localhost;dbname=gym_db", "root", "Mustafa786.");
            
            // Check user permissions
            $stmt = $db->query("SHOW GRANTS");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (strpos($row[0], 'ALL PRIVILEGES') !== false) {
                    $this->issues[] = "Database user has excessive privileges";
                }
            }

        } catch (PDOException $e) {
            $this->issues[] = "Database connection error: " . $e->getMessage();
        }
    }

    public function checkSSLConfiguration() {
        $curl = curl_init('https://' . $_SERVER['HTTP_HOST']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($curl);
        
        if ($result === false) {
            $this->issues[] = "SSL verification failed: " . curl_error($curl);
        }
        
        curl_close($curl);
    }

    public function generateReport() {
        $this->checkFilePermissions();
        $this->checkConfigurationFiles();
        $this->checkDatabaseSecurity();
        $this->checkSSLConfiguration();

        $report = "Security Audit Report - " . date('Y-m-d H:i:s') . "\n";
        $report .= "=====================================\n\n";

        if (empty($this->issues)) {
            $report .= "No security issues found.\n";
        } else {
            $report .= "Security Issues Found:\n";
            foreach ($this->issues as $issue) {
                $report .= "- $issue\n";
            }
        }

        // Log report
        Logger::info($report);

        return $report;
    }
}

// Run security check
$checker = new SecurityChecker();
echo $checker->generateReport();
?>
