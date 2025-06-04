<?php
echo "PHP Environment Test\n";
echo "===================\n\n";

// PHP Version
echo "PHP Version: " . phpversion() . "\n";

// Required Extensions
$required_extensions = [
    'pdo',
    'pdo_mysql',
    'mbstring',
    'json',
    'openssl',
    'curl'
];

echo "\nChecking Required Extensions:\n";
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext\n";
    } else {
        echo "✗ $ext (Missing!)\n";
    }
}

// Directory Permissions
echo "\nChecking Directory Permissions:\n";
$directories = [
    '../uploads/profile_photos',
    '../uploads/post_media',
    '../logs'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "✓ $dir exists\n";
        if (is_writable($dir)) {
            echo "  ✓ Is writable\n";
        } else {
            echo "  ✗ Not writable!\n";
        }
    } else {
        echo "✗ $dir does not exist!\n";
    }
}

// Database Connection
echo "\nTesting Database Connection:\n";
try {
    require_once __DIR__ . '/../php/db_config.php';
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

// Mail Configuration
echo "\nTesting Mail Configuration:\n";
try {
    require_once __DIR__ . '/../php/utils/mailer.php';
    $mailer = new Mailer();
    echo "✓ Mail configuration loaded\n";
} catch (Exception $e) {
    echo "✗ Mail configuration error: " . $e->getMessage() . "\n";
}

echo "\nEnvironment test completed.\n";
?>
