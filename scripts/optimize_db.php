<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../php/db_config.php';
require_once __DIR__ . '/../php/utils/logger.php';

class DatabaseOptimizer {
    private $conn;
    private $tables;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->getTables();
    }

    private function getTables() {
        $stmt = $this->conn->query("SHOW TABLES");
        $this->tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function analyze() {
        echo "Analyzing database...\n";
        
        foreach ($this->tables as $table) {
            echo "\nAnalyzing table '$table':\n";
            
            // Check table size
            $stmt = $this->conn->query("
                SELECT 
                    table_name AS 'Table',
                    round(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = '$table'
            ");
            $size = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "- Size: {$size['Size (MB)']} MB\n";

            // Check for missing indexes
            $this->checkIndexes($table);

            // Analyze query performance
            $this->analyzeQueries($table);
        }
    }

    private function checkIndexes($table) {
        // Get columns that should probably be indexed
        $stmt = $this->conn->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = '$table'
            AND (column_name LIKE '%_id' 
                OR column_name LIKE 'id_%'
                OR data_type IN ('datetime', 'date'))
        ");
        
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check existing indexes
        $stmt = $this->conn->query("SHOW INDEX FROM $table");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 4);
        
        $missing = array_diff($columns, $existing);
        if (!empty($missing)) {
            echo "- Suggested indexes for columns: " . implode(', ', $missing) . "\n";
        }
    }

    private function analyzeQueries($table) {
        // Analyze slow queries
        $this->conn->query("ANALYZE TABLE $table");
        
        echo "- Table structure optimized\n";
    }

    public function optimize() {
        echo "\nOptimizing database...\n";
        
        foreach ($this->tables as $table) {
            echo "Optimizing table '$table'... ";
            $this->conn->query("OPTIMIZE TABLE $table");
            echo "done\n";
        }
    }
}

// Run optimization
$optimizer = new DatabaseOptimizer($conn);
$optimizer->analyze();
$optimizer->optimize();
?>
