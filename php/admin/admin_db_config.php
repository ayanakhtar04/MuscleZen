<?php
class AdminDatabase {
    private static $instance = null;
    private $conn;
    
    // Database configuration
    private $host;
    private $dbUsername;
    private $dbPassword;
    private $dbName;

    private function __construct() {
        // Load configuration from .env file or use defaults
        $envFile = __DIR__ . '/../../.env.example';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $this->host = $env['DB_HOST'] ?? 'localhost';
            $this->dbUsername = $env['DB_USER'] ?? 'root';
            $this->dbPassword = $env['DB_PASS'] ?? 'Mustafa786.';
            $this->dbName = $env['DB_NAME'] ?? 'gym_db';
        } else {
            // Default values if .env doesn't exist
            $this->host = 'localhost';
            $this->dbUsername = 'root';
            $this->dbPassword = 'Mustafa786.';
            $this->dbName = 'gym_db';
        }

        try {
            // Connect directly to the database
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4",
                $this->dbUsername,
                $this->dbPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_TIMEOUT => 5 // 5 seconds timeout
                ]
            );

            // Check if admin tables exist and create them if needed
            $this->checkAndCreateAdminTables();

        } catch(PDOException $e) {
            error_log("Admin Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function checkAndCreateAdminTables() {
        try {
            // Check if admin_users table exists
            $stmt = $this->conn->query("SHOW TABLES LIKE 'admin_users'");
            if ($stmt->rowCount() == 0) {
                // Create admin tables
                $this->createAdminTables();
            }
        } catch(PDOException $e) {
            error_log("Admin Tables Check Error: " . $e->getMessage());
            throw new Exception("Failed to check admin tables: " . $e->getMessage());
        }
    }

    private function createAdminTables() {
        try {
            // Create admin_users table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS admin_users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) NOT NULL UNIQUE,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    role VARCHAR(50) NOT NULL DEFAULT 'admin',
                    status VARCHAR(20) NOT NULL DEFAULT 'active',
                    last_login TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

            // Create admin_activity_log table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS admin_activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT NOT NULL,
                    action VARCHAR(255) NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (admin_id) REFERENCES admin_users(id)
                )
            ");

            // Create workouts table
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS workouts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    category VARCHAR(20) NOT NULL,
                    difficulty VARCHAR(20) NOT NULL,
                    duration INT NOT NULL,
                    calories_burn INT,
                    status VARCHAR(20) DEFAULT 'active',
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

            // Insert default admin user if none exists
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM admin_users");
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $this->conn->exec("
                    INSERT INTO admin_users (username, email, password, role) 
                    VALUES (
                        'admin',
                        'admin@musclezen.com',
                        '{$defaultPassword}',
                        'super_admin'
                    )
                ");
            }

        } catch(PDOException $e) {
            error_log("Admin Tables Creation Error: " . $e->getMessage());
            throw new Exception("Failed to create admin tables: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Admin Query Error: " . $e->getMessage());
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

    private function __clone() {}
    private function __wakeup() {}
}

// Do not return anything here - let the class handle everything
?>
