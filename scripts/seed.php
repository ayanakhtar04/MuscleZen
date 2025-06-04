<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../php/db_config.php';

try {
    echo "Starting database seeding...\n";

    // Seed users
    $password = password_hash('password123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password, created_at) VALUES 
        ('John Doe', 'john@example.com', ?, NOW()),
        ('Jane Smith', 'jane@example.com', ?, NOW())
    ");
    $stmt->execute([$password, $password]);
    echo "Users seeded.\n";

    // Seed workout plans
    $stmt = $conn->prepare("
        INSERT INTO workout_plans (user_id, name, category, description) VALUES 
        (1, 'Beginner Strength', 'strength', 'Basic strength training program'),
        (1, 'HIIT Cardio', 'cardio', 'High-intensity interval training'),
        (2, 'Yoga Flow', 'flexibility', 'Basic yoga routine')
    ");
    $stmt->execute();
    echo "Workout plans seeded.\n";

    // Seed user settings
    $stmt = $conn->prepare("
        INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES 
        (1, 'profile_visibility', 'public'),
        (1, 'workout_reminders', '1'),
        (2, 'profile_visibility', 'private'),
        (2, 'workout_reminders', '0')
    ");
    $stmt->execute();
    echo "User settings seeded.\n";

    echo "Database seeding completed successfully!\n";

} catch (Exception $e) {
    echo "Error during seeding: " . $e->getMessage() . "\n";
    exit(1);
}
?>
