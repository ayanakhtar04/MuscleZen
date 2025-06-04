<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../php/db_config.php';

try {
    echo "Starting database migration...\n";

    // Get current schema version
    $stmt = $conn->query("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version INT NOT NULL,
            migration_name VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Get last executed migration
    $stmt = $conn->query("SELECT MAX(version) as current_version FROM migrations");
    $currentVersion = $stmt->fetch(PDO::FETCH_ASSOC)['current_version'] ?? 0;

    // Get all migration files
    $migrations = glob(__DIR__ . '/../database/migrations/*.sql');
    sort($migrations);

    foreach ($migrations as $migration) {
        $version = (int)basename($migration, '.sql');
        
        if ($version > $currentVersion) {
            echo "Executing migration $version...\n";
            
            // Read and execute migration file
            $sql = file_get_contents($migration);
            $conn->exec($sql);

            // Record migration
            $migrationName = basename($migration);
            $stmt = $conn->prepare("
                INSERT INTO migrations (version, migration_name) 
                VALUES (?, ?)
            ");
            $stmt->execute([$version, $migrationName]);
            
            echo "Migration $version completed.\n";
        }
    }

    echo "Database migration completed successfully!\n";

} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
