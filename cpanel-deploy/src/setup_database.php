<?php
/**
 * Database Setup Helper
 * Use this to verify connection and initialize database schema if empty
 */

require_once __DIR__ . '/bootstrap.php';

echo "<h1>Church Partnership Script - Database Setup</h1>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Check if tables exist
    $tables = ['users', 'partnerships', 'transactions', 'projects', 'campaigns'];
    $missing = [];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() == 0) {
            $missing[] = $table;
        }
    }
    
    if (empty($missing)) {
        echo "<p style='color: green;'>✅ All required tables found.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Missing tables: " . implode(', ', $missing) . "</p>";
        echo "<p>Please import the database schema from the SQL file provided in the repository.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Connection Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Troubleshooting Tips for Windows (Localhost):</h3>";
    echo "<ul>";
    echo "<li>Ensure your MySQL/MariaDB server is running (XAMPP, WAMP, or Laragon).</li>";
    echo "<li>Check your <b>.env.php</b> file in the root directory.</li>";
    echo "<li>Default local user is usually <b>root</b> with <b>no password</b>.</li>";
    echo "<li>If you are using <b>church_user</b>, make sure you created that user in MySQL and granted it permissions.</li>";
    echo "</ul>";
    
    echo "<h4>Current Environment detected:</h4>";
    echo "<pre>";
    $envFile = __DIR__ . '/.env.php';
    if (file_exists($envFile)) {
        echo ".env.php exists.\n";
        // Do not print secrets here for security, but maybe keys
    } else {
        echo ".env.php is MISSING!\n";
    }
    echo "Server Host: " . $_SERVER['SERVER_NAME'] . "\n";
    echo "</pre>";
}
