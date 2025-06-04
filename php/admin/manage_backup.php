<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';

try {
    AdminAuth::requireAdmin();
    
    $action = $_POST['action'] ?? '';
    $backupId = $_POST['backup_id'] ?? null;

    switch ($action) {
        case 'create':
            $result = createBackup();
            break;
        case 'restore':
            if (!$backupId) {
                throw new Exception('Backup ID is required for restore');
            }
            $result = restoreBackup($backupId);
            break;
        case 'delete':
            if (!$backupId) {
                throw new Exception('Backup ID is required for deletion');
            }
            $result = deleteBackup($backupId);
            break;
        case 'list':
            $result = listBackups();
            break;
        default:
            throw new Exception('Invalid action');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);

} catch (Exception $e) {
    error_log("Error in manage_backup: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function createBackup() {
    $backupDir = '../../backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$timestamp}.sql";
    $filepath = $backupDir . $filename;

    // Get database credentials
    $config = parse_ini_file('../../.env.example');
    
    // Create backup command
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg($config['DB_HOST']),
        escapeshellarg($config['DB_USER']),
        escapeshellarg($config['DB_PASS']),
        escapeshellarg($config['DB_NAME']),
        escapeshellarg($filepath)
    );

    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
        throw new Exception('Failed to create backup');
    }

    // Compress the backup
    $zipPath = $filepath . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($filepath, basename($filepath));
        $zip->close();
        unlink($filepath); // Remove the uncompressed file
    }

    // Log backup creation
    logBackupAction('create', $filename);

    return [
        'filename' => basename($zipPath),
        'size' => filesize($zipPath),
        'created_at' => $timestamp
    ];
}

function restoreBackup($backupId) {
    $backupDir = '../../backups/';
    $backupFile = $backupDir . $backupId;

    if (!file_exists($backupFile)) {
        throw new Exception('Backup file not found');
    }

    // Extract zip if necessary
    if (pathinfo($backupFile, PATHINFO_EXTENSION) === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($backupFile) === TRUE) {
            $zip->extractTo($backupDir);
            $zip->close();
            $backupFile = $backupDir . pathinfo($backupId, PATHINFO_FILENAME);
        }
    }

    $config = parse_ini_file('../../.env.example');
    
    $command = sprintf(
        'mysql --host=%s --user=%s --password=%s %s < %s',
        escapeshellarg($config['DB_HOST']),
        escapeshellarg($config['DB_USER']),
        escapeshellarg($config['DB_PASS']),
        escapeshellarg($config['DB_NAME']),
        escapeshellarg($backupFile)
    );

    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
        throw new Exception('Failed to restore backup');
    }

    // Log backup restoration
    logBackupAction('restore', $backupId);

    return [
        'message' => 'Backup restored successfully'
    ];
}

function deleteBackup($backupId) {
    $backupFile = '../../backups/' . $backupId;
    
    if (!file_exists($backupFile)) {
        throw new Exception('Backup file not found');
    }

    if (!unlink($backupFile)) {
        throw new Exception('Failed to delete backup file');
    }

    // Log backup deletion
    logBackupAction('delete', $backupId);

    return [
        'message' => 'Backup deleted successfully'
    ];
}

function listBackups() {
    $backupDir = '../../backups/';
    $backups = [];
    
    if (is_dir($backupDir)) {
        foreach (new DirectoryIterator($backupDir) as $file) {
            if ($file->isFile() && $file->getExtension() === 'zip') {
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'created_at' => date('Y-m-d H:i:s', $file->getCTime())
                ];
            }
        }
    }

    return $backups;
}

function logBackupAction($action, $filename) {
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, details, ip_address)
        VALUES (:admin_id, :action, :details, :ip_address)
    ");

    $stmt->execute([
        ':admin_id' => $_SESSION['admin_id'],
        ':action' => 'backup_' . $action,
        ':details' => json_encode(['filename' => $filename]),
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}
