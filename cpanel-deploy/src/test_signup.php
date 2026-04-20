<?php
/**
 * Test Script for Email Verification Signup
 * Run this to test the signup flow and diagnose issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/EmailVerification.php';
require_once __DIR__ . '/config/ResendService.php';

echo "<h1>Email Verification Signup Test</h1>";

// Test 1: Check if database tables exist
echo "<h2>Test 1: Database Tables</h2>";
$db = Database::getInstance();

$tables = ['users', 'email_verifications', 'email_verification_requests'];
foreach ($tables as $table) {
    $result = $db->fetchOne("SHOW TABLES LIKE '$table'");
    if ($result) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' does NOT exist</p>";
    }
}

// Test 2: Check Resend configuration
echo "<h2>Test 2: Resend Configuration</h2>";
if (defined('RESEND_API_KEY') && RESEND_API_KEY !== '') {
    echo "<p style='color: green;'>✓ Resend API Key is configured</p>";
} else {
    echo "<p style='color: red;'>✗ Resend API Key is NOT configured</p>";
}

// Test 3: Test EmailVerification class
echo "<h2>Test 3: EmailVerification Class</h2>";
try {
    $emailVerif = new EmailVerification();
    echo "<p style='color: green;'>✓ EmailVerification class instantiated successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Test User::sendVerification
echo "<h2>Test 4: User Verification Methods</h2>";
try {
    // Get a test user
    $testUser = $db->fetchOne("SELECT * FROM users WHERE email = 'test@example.com'");
    
    if ($testUser) {
        echo "<p>Found test user: " . htmlspecialchars($testUser['email']) . "</p>";
        
        // Try to send verification
        $result = User::sendVerification($testUser['user_id'], $testUser['email'], $testUser['first_name']);
        
        if ($result['success']) {
            echo "<p style='color: green;'>✓ Verification email sent successfully</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Verification email failed: " . htmlspecialchars($result['message']) . "</p>";
        }
    } else {
        echo "<p>No test user found. Creating one...</p>";
        
        // Create a test user
        try {
            $userId = User::create('test@example.com', 'TestPass123!', 'Test', 'User');
            echo "<p>Created test user with ID: $userId</p>";
            
            // Try to send verification
            $result = User::sendVerification($userId, 'test@example.com', 'Test');
            
            if ($result['success']) {
                echo "<p style='color: green;'>✓ Verification email sent successfully</p>";
            } else {
                echo "<p style='color: red;'>✗ Verification email failed: " . htmlspecialchars($result['message']) . "</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error creating user: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: Generate a verification code manually
echo "<h2>Test 5: Generate Verification Code</h2>";
try {
    $emailVerif = new EmailVerification();
    $code = $emailVerif->generateVerificationCode();
    echo "<p>Generated code: <strong>$code</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error generating code: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>Note:</strong> If tables don't exist, run the migration:</p>";
echo "<pre>mysql -u username -p database_name < database/migrations/add_email_verification_tables.sql</pre>";