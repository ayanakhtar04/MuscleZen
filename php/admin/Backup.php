<?php
class Backup {
    private $db;
    private $backupDir;
    
    public function __construct() {
        $this->db = AdminDatabase::getInstance()->getConnection();
        $this->backupDir = __DIR__ . '/../../../backups/';
        
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    public function createBackup() {
        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $this->backupDir . $filename;
            
            // Get database credentials
            require_once __DIR__ . '/../../config/app_config.php';
            
            // Create backup command
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_NAME),
                escapeshellarg($filepath)
            );
            
            // Execute backup
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("Backup failed");
            }
            
            // Log backup
            $this->logBackup($filename, filesize($filepath));
            
            // Clean old backups
            $this->cleanOldBackups();
            
            return [
                'status' => 'success',
                'filename' => $filename,
                'size' => filesize($filepath)
            ];
        } catch (Exception $e) {
            error_log("Backup error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function restoreBackup($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            if (!file_exists($filepath)) {
                throw new Exception("Backup file not found");
            }
            
            // Get database credentials
            require_once __DIR__ . '/../../config/app_config.php';
            
            // Restore command
            $command = sprintf(
                'mysql --user=%s --password=%s --host=%s %s < %s',
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_NAME),
                escapeshellarg($filepath)
            );
            
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("Restore failed");
            }
            
            return ['status' => 'success'];
        } catch (Exception $e) {
            error_log("Restore error: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function logBackup($filename, $size) {
        $stmt = $this->db->prepare("
            INSERT INTO backup_logs (filename, size, status, created_by)
            VALUES (:filename, :size, 'success', :admin_id)
        ");
        
        $stmt->execute([
            'filename' => $filename,
            'size' => $size,
            'admin_id' => $_SESSION['admin_id'] ?? null
        ]);
    }
    
    private function cleanOldBackups() {
        // Get retention period from settings
        $stmt = $this->db->query("
            SELECT retention_days FROM backup_settings LIMIT 1
        ");
        $retention = $stmt->fetch()['retention_days'] ?? 30;
        
        // Delete old backup files
        $oldFiles = glob($this->backupDir . 'backup_*.sql');
        $cutoffDate = strtotime("-{$retention} days");
        
        foreach ($oldFiles as $file) {
            if (filemtime($file) < $cutoffDate) {
                unlink($file);
            }
        }
    }
}
