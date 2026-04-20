<?php
/**
 * Database Connection Test Script
 * Use this to diagnose database connection issues
 */

// Load environment variables
require_once __DIR__ . '/.env.php';

echo "<h2>Database Connection Test</h2>";
echo "<hr>";

// Display environment variables
echo "<h3>Environment Variables:</h3>";
echo "<ul>";
echo "<li>DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "</li>";
echo "<li>DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "</li>";
echo "<li>DB_NAME: " . (getenv('DB_NAME') ?: 'NOT SET') . "</li>";
echo "<li>DB_USER: " . (getenv('DB_USER') ?: 'NOT SET') . "</li>";
echo "<li>DB_PASSWORD: " . (getenv('DB_PASSWORD') ? 'SET' : 'NOT SET') . "</li>";
echo "</ul>";

// Test connection
echo "<h3>Testing Connection:</h3>";

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'church_partnership';
    $username = getenv('DB_USER') ?: 'church_user';
    $password = getenv('DB_PASSWORD') ?: 'church_password123';
    
    echo "<p>Attempting to connect to MySQL...</p>";
    echo "<p>Host: $host</p>";
    echo "<p>Port: $port</p>";
    echo "<p>Database: $dbname</p>";
    echo "<p>Username: $username</p>";
    
    // First test: connect without database
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p style='color: green;'>✓ Successfully connected to MySQL server!</p>";
    
    // Test: check if database exists
    $stmt = $conn->query("SHOW DATABASES LIKE '$dbname'");
    $dbExists = $stmt->fetch();
    
    if ($dbExists) {
        echo "<p style='color: green;'>✓ Database '$dbname' exists!</p>";
        
        // Test: connect to database
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $conn = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        echo "<p style='color: green;'>✓ Successfully connected to database '$dbname'!</p>";
        
        // Test: check if tables exist
        $tables = ['users', 'email_verifications', 'email_verification_requests'];
        echo "<h3>Table Check:</h3>";
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->fetch()) {
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' does NOT exist</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>✗ Database '$dbname' does NOT exist!</p>";
        echo "<p>You need to create the database first.</p>";
    }
    
    $conn = null;
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Connection failed!</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Check if MySQL/MariaDB is running</li>";
    echo "<li>Verify the database host is correct (mysql for Docker, localhost for local)</li>";
    echo "<li>Check if the database exists</li>";
    echo "<li>Verify username and password are correct</li>";
    echo "<li>Check firewall settings if connecting remotely</li>";
    echo "</ul>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}
h2 { color: #1a1a2e; }
h3 { color: #666; margin-top: 20px; }
hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
ul { line-height: 1.8; }
</style>