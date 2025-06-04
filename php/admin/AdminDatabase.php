<?php
class AdminDatabase {
    private static $instance = null;
    private $conn = null;
    
    private function __construct() {
        try {
            require_once __DIR__ . '/../config/app_config.php';
            
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function logActivity($action, $description, $details = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO admin_activity_log (
                    admin_id, 
                    action, 
                    description, 
                    details,
                    ip_address
                ) VALUES (
                    :admin_id,
                    :action,
                    :description,
                    :details,
                    :ip_address
                )
            ");

            $stmt->execute([
                'admin_id' => $_SESSION['admin_id'] ?? 0,
                'action' => $action,
                'description' => $description,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }

    public function getConnection() {
        return $this->conn;
    }
    
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    public function commit() {
        return $this->conn->commit();
    }
    
    public function rollBack() {
        return $this->conn->rollBack();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
