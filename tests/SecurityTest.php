<?php
/**
 * Security Class Unit Tests
 * Bright Light Ministry Int'l Partners Portal
 * 
 * These tests demonstrate that the Security class properly neutralizes
 * various attack vectors including XSS, SQL injection, path traversal,
 * and malicious file uploads.
 * 
 * @author Security Team
 * @version 1.0.0
 */

// Load the Security class
require_once __DIR__ . '/../src/config/Security.php';

/**
 * Test class for Security
 */
class SecurityTest {
    
    private $testsRun = 0;
    private $testsPassed = 0;
    private $testsFailed = 0;
    
    /**
     * Run all tests
     */
    public function runAll() {
        echo "========================================\n";
        echo "Security Class Unit Tests\n";
        echo "========================================\n\n";
        
        $this->testSanitizeString();
        $this->testSanitizeEmail();
        $this->testSanitizeSubject();
        $this->testSanitizeMessage();
        $this->testSanitizeCategory();
        $this->testSanitizeInteger();
        $this->testFileUploadValidation();
        $this->testRateLimiting();
        $this->testOutputEscaping();
        
        echo "\n========================================\n";
        echo "Test Summary\n";
        echo "========================================\n";
        echo "Tests Run: {$this->testsRun}\n";
        echo "Passed: {$this->testsPassed}\n";
        echo "Failed: {$this->testsFailed}\n";
        echo "========================================\n";
        
        return $this->testsFailed === 0;
    }
    
    /**
     * Assert that a condition is true
     */
    private function assertTrue($condition, $message) {
        $this->testsRun++;
        if ($condition) {
            $this->testsPassed++;
            echo "✓ PASS: $message\n";
        } else {
            $this->testsFailed++;
            echo "✗ FAIL: $message\n";
        }
    }
    
    /**
     * Assert that a condition is false
     */
    private function assertFalse($condition, $message) {
        $this->testsRun++;
        if (!$condition) {
            $this->testsPassed++;
            echo "✓ PASS: $message\n";
        } else {
            $this->testsFailed++;
            echo "✗ FAIL: $message\n";
        }
    }
    
    /**
     * Assert that two values are equal
     */
    private function assertEquals($expected, $actual, $message) {
        $this->testsRun++;
        if ($expected === $actual) {
            $this->testsPassed++;
            echo "✓ PASS: $message\n";
        } else {
            $this->testsFailed++;
            echo "✗ FAIL: $message\n";
            echo "  Expected: " . var_export($expected, true) . "\n";
            echo "  Actual: " . var_export($actual, true) . "\n";
        }
    }
    
    /**
     * Test XSS prevention in string sanitization
     */
    private function testSanitizeString() {
        echo "--- Testing sanitizeString() ---\n";
        
        // Test 1: Basic XSS script tag
        $input = '<script>alert("XSS")</script>';
        $output = Security::sanitizeString($input);
        $this->assertFalse(
            strpos($output, '<script>') !== false,
            'Script tags should be removed'
        );
        
        // Test 2: javascript: protocol
        $input = '<a href="javascript:alert(\'XSS\')">Click</a>';
        $output = Security::sanitizeString($input);
        $this->assertFalse(
            strpos($output, 'javascript:') !== false,
            'javascript: protocol should be removed'
        );
        
        // Test 3: Event handlers
        $input = '<img src="x" onerror="alert(\'XSS\')">';
        $output = Security::sanitizeString($input);
        $this->assertFalse(
            strpos($output, 'onerror') !== false,
            'Event handlers should be removed'
        );
        
        // Test 4: HTML entities should be encoded
        $input = '<div onclick="evil()">Test</div>';
        $output = Security::sanitizeString($input);
        $this->assertTrue(
            strpos($output, '<') !== false,
            'HTML special characters should be encoded'
        );
        
        // Test 5: Null byte injection
        $input = 'test%00.php';
        $output = Security::sanitizeString($input);
        $this->assertFalse(
            strpos($output, '%00') !== false && strpos($output, "\0") === false,
            'Null bytes should be removed'
        );
        
        // Test 6: Length limiting
        $input = str_repeat('a', 2000);
        $output = Security::sanitizeString($input, 100);
        $this->assertTrue(
            mb_strlen($output) <= 100,
            'Input should be limited to max length'
        );
        
        // Test 7: Whitespace trimming
        $input = '   test value   ';
        $output = Security::sanitizeString($input);
        $this->assertEquals(
            'test value',
            $output,
            'Whitespace should be trimmed'
        );
        
        // Test 8: Valid input should pass through
        $input = 'Hello, World!';
        $output = Security::sanitizeString($input);
        $this->assertEquals(
            'Hello, World!',
            $output,
            'Valid input should pass through'
        );
        
        echo "\n";
    }
    
    /**
     * Test email validation
     */
    private function testSanitizeEmail() {
        echo "--- Testing sanitizeEmail() ---\n";
        
        // Test 1: Valid email
        $input = 'user@example.com';
        $output = Security::sanitizeEmail($input);
        $this->assertEquals(
            'user@example.com',
            $output,
            'Valid email should pass'
        );
        
        // Test 2: Valid email with subdomain
        $input = 'user@mail.example.com';
        $output = Security::sanitizeEmail($input);
        $this->assertEquals(
            'user@mail.example.com',
            $output,
            'Email with subdomain should pass'
        );
        
        // Test 3: Invalid email (no @)
        $input = 'userexample.com';
        $output = Security::sanitizeEmail($input);
        $this->assertFalse(
            $output,
            'Email without @ should be rejected'
        );
        
        // Test 4: Invalid email (multiple @)
        $input = 'user@@example.com';
        $output = Security::sanitizeEmail($input);
        $this->assertFalse(
            $output,
            'Email with multiple @ should be rejected'
        );
        
        // Test 5: Invalid email (special chars)
        $input = 'user<script>@example.com';
        $output = Security::sanitizeEmail($input);
        $this->assertFalse(
            $output,
            'Email with script tags should be rejected'
        );
        
        // Test 6: Case normalization
        $input = 'USER@EXAMPLE.COM';
        $output = Security::sanitizeEmail($input);
        $this->assertEquals(
            'user@example.com',
            $output,
            'Email should be lowercased'
        );
        
        // Test 7: Whitespace trimming
        $input = '  user@example.com  ';
        $output = Security::sanitizeEmail($input);
        $this->assertEquals(
            'user@example.com',
            $output,
            'Email whitespace should be trimmed'
        );
        
        // Test 8: Too long email
        $input = str_repeat('a', 250) . '@example.com';
        $output = Security::sanitizeEmail($input);
        $this->assertFalse(
            $output,
            'Email exceeding 254 chars should be rejected'
        );
        
        echo "\n";
    }
    
    /**
     * Test subject sanitization
     */
    private function testSanitizeSubject() {
        echo "--- Testing sanitizeSubject() ---\n";
        
        // Test 1: Normal subject
        $input = 'Support Request';
        $output = Security::sanitizeSubject($input);
        $this->assertEquals(
            'Support Request',
            $output,
            'Normal subject should pass'
        );
        
        // Test 2: Subject with line breaks (header injection attempt)
        $input = "Subject\r\nBCC: attacker@evil.com";
        $output = Security::sanitizeSubject($input);
        $this->assertFalse(
            strpos($output, "\r") !== false || strpos($output, "\n") !== false,
            'Line breaks should be removed to prevent header injection'
        );
        
        // Test 3: Subject with script tag
        $input = '<script>alert("XSS")</script> Support';
        $output = Security::sanitizeSubject($input);
        $this->assertFalse(
            strpos($output, '<script>') !== false,
            'Script tags should be removed'
        );
        
        // Test 4: Length limiting
        $input = str_repeat('a', 300);
        $output = Security::sanitizeSubject($input);
        $this->assertTrue(
            mb_strlen($output) <= 200,
            'Subject should be limited to 200 chars'
        );
        
        echo "\n";
    }
    
    /**
     * Test message sanitization
     */
    private function testSanitizeMessage() {
        echo "--- Testing sanitizeMessage() ---\n";
        
        // Test 1: Normal message with newlines
        $input = "Line 1\nLine 2\nLine 3";
        $output = Security::sanitizeMessage($input);
        $this->assertEquals(
            "Line 1\nLine 2\nLine 3",
            $output,
            'Newlines should be preserved'
        );
        
        // Test 2: Message with CRLF
        $input = "Line 1\r\nLine 2";
        $output = Security::sanitizeMessage($input);
        $this->assertFalse(
            strpos($output, "\r") !== false,
            'CRLF should be normalized to LF'
        );
        
        // Test 3: Message with script tags
        $input = "Hello<script>alert('XSS')</script>World";
        $output = Security::sanitizeMessage($input);
        $this->assertFalse(
            strpos($output, '<script>') !== false,
            'Script tags should be removed'
        );
        
        // Test 4: Length limiting
        $input = str_repeat('a', 6000);
        $output = Security::sanitizeMessage($input);
        $this->assertTrue(
            mb_strlen($output) <= 5000,
            'Message should be limited to 5000 chars'
        );
        
        echo "\n";
    }
    
    /**
     * Test category sanitization (whitelist validation)
     */
    private function testSanitizeCategory() {
        echo "--- Testing sanitizeCategory() ---\n";
        
        // Test 1: Valid category
        $input = 'technical';
        $output = Security::sanitizeCategory($input);
        $this->assertEquals(
            'technical',
            $output,
            'Valid category should pass'
        );
        
        // Test 2: Case insensitivity
        $input = 'TECHNICAL';
        $output = Security::sanitizeCategory($input);
        $this->assertEquals(
            'technical',
            $output,
            'Category should be case-insensitive'
        );
        
        // Test 3: Invalid category (SQL injection attempt)
        $input = "'; DROP TABLE users; --";
        $output = Security::sanitizeCategory($input);
        $this->assertFalse(
            $output,
            'Invalid category should be rejected'
        );
        
        // Test 4: Invalid category (script tag)
        $input = '<script>alert("XSS")</script>';
        $output = Security::sanitizeCategory($input);
        $this->assertFalse(
            $output,
            'Script tag in category should be rejected'
        );
        
        // Test 5: All valid categories
        $validCategories = ['technical', 'payment', 'account', 'project', 'receipt', 'other'];
        foreach ($validCategories as $cat) {
            $output = Security::sanitizeCategory($cat);
            $this->assertTrue(
                $output === $cat,
                "Category '$cat' should be valid"
            );
        }
        
        echo "\n";
    }
    
    /**
     * Test integer sanitization
     */
    private function testSanitizeInteger() {
        echo "--- Testing sanitizeInteger() ---\n";
        
        // Test 1: Valid integer
        $input = '123';
        $output = Security::sanitizeInteger($input);
        $this->assertEquals(
            123,
            $output,
            'Valid integer should pass'
        );
        
        // Test 2: Integer with range
        $input = '50';
        $output = Security::sanitizeInteger($input, 0, 100);
        $this->assertEquals(
            50,
            $output,
            'Integer within range should pass'
        );
        
        // Test 3: Integer out of range (below)
        $input = '-10';
        $output = Security::sanitizeInteger($input, 0, 100);
        $this->assertFalse(
            $output,
            'Integer below minimum should be rejected'
        );
        
        // Test 4: Integer out of range (above)
        $input = '150';
        $output = Security::sanitizeInteger($input, 0, 100);
        $this->assertFalse(
            $output,
            'Integer above maximum should be rejected'
        );
        
        // Test 5: Non-integer string
        $input = 'abc';
        $output = Security::sanitizeInteger($input);
        $this->assertEquals(
            0,
            $output,
            'Non-integer string should be cast to 0'
        );
        
        echo "\n";
    }
    
    /**
     * Test file upload validation
     */
    private function testFileUploadValidation() {
        echo "--- Testing validateFileUpload() ---\n";
        
        // Create a test directory
        $testDir = sys_get_temp_dir() . '/security_test_uploads';
        if (!file_exists($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        // Test 1: Create a valid test PDF (minimal PDF)
        $validPdf = $testDir . '/valid.pdf';
        file_put_contents($validPdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
        
        $file = [
            'name' => 'document.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $validPdf,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($validPdf)
        ];
        
        $result = Security::validateFileUpload($file, $testDir);
        $this->assertTrue(
            $result['valid'],
            'Valid PDF should pass validation'
        );
        
        // Test 2: Create a valid test image (minimal PNG)
        $validPng = $testDir . '/valid.png';
        file_put_contents($validPng, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde");
        
        $file = [
            'name' => 'image.png',
            'type' => 'image/png',
            'tmp_name' => $validPng,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($validPng)
        ];
        
        $result = Security::validateFileUpload($file, $testDir);
        $this->assertTrue(
            $result['valid'],
            'Valid PNG should pass validation'
        );
        
        // Test 3: Invalid file extension
        $file = [
            'name' => 'malicious.php',
            'type' => 'application/x-php',
            'tmp_name' => __FILE__,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize(__FILE__)
        ];
        
        $result = Security::validateFileUpload($file, $testDir);
        $this->assertFalse(
            $result['valid'],
            'PHP file extension should be rejected'
        );
        
        // Test 4: File too large
        $file = [
            'name' => 'large.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $validPdf,
            'error' => UPLOAD_ERR_OK,
            'size' => Security::MAX_FILE_SIZE + 1
        ];
        
        $result = Security::validateFileUpload($file, $testDir);
        $this->assertFalse(
            $result['valid'],
            'File exceeding size limit should be rejected'
        );
        
        // Test 5: Empty file
        $emptyFile = $testDir . '/empty.pdf';
        file_put_contents($emptyFile, '');
        
        $file = [
            'name' => 'empty.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $emptyFile,
            'error' => UPLOAD_ERR_OK,
            'size' => 0
        ];
        
        $result = Security::validateFileUpload($file, $testDir);
        $this->assertFalse(
            $result['valid'],
            'Empty file should be rejected'
        );
        
        // Test 6: Path traversal attempt
        $file = [
            'name' => '../../../etc/passwd.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $validPdf,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($validPdf)
        ];
        
        $result = Security::validateFileUpload($file, $testDir);
        $this->assertTrue(
            $result['valid'],
            'Path traversal in filename should be handled'
        );
        $this->assertFalse(
            strpos($result['safeFilename'], '..') !== false,
            'Safe filename should not contain ..'
        );
        
        // Test 7: Extension mismatch (PNG pretending to be PDF)
        $file = [
            'name' => 'fake.pdf',
            'type' => 'image/png',
            'tmp_name' => $validPng,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($validPng)
        ];
        
        $result = Security::validateFileUpload($file, $testDir);
        $this->assertFalse(
            $result['valid'],
            'Extension/MIME mismatch should be rejected'
        );
        
        // Cleanup
        unlink($validPdf);
        unlink($validPng);
        unlink($emptyFile);
        rmdir($testDir);
        
        echo "\n";
    }
    
    /**
     * Test rate limiting
     */
    private function testRateLimiting() {
        echo "--- Testing checkRateLimit() ---\n";
        
        // Test 1: First request should be allowed
        $result = Security::checkRateLimit('test_user_1', 5, 60, 'session');
        $this->assertTrue(
            $result['allowed'],
            'First request should be allowed'
        );
        
        // Test 2: Subsequent requests within limit should be allowed
        for ($i = 0; $i < 4; $i++) {
            $result = Security::checkRateLimit('test_user_2', 5, 60, 'session');
            $this->assertTrue(
                $result['allowed'],
                "Request $i within limit should be allowed"
            );
        }
        
        // Test 3: Request count should be tracked
        $this->assertTrue(
            $result['currentCount'] >= 1,
            'Request count should be tracked'
        );
        
        echo "\n";
    }
    
    /**
     * Test output escaping
     */
    private function testOutputEscaping() {
        echo "--- Testing output escaping ---\n";
        
        // Test 1: HTML escaping
        $input = '<script>alert("XSS")</script>';
        $output = Security::escapeHtml($input);
        $this->assertTrue(
            strpos($output, '<script>') !== false,
            'HTML escaping should encode script tags'
        );
        
        // Test 2: URL escaping
        $input = 'hello world & test';
        $output = Security::escapeUrl($input);
        $this->assertTrue(
            strpos($output, 'hello%20world') !== false,
            'URL escaping should encode spaces'
        );
        
        // Test 3: JavaScript escaping
        $input = 'hello "world" \n';
        $output = Security::escapeJs($input);
        $this->assertTrue(
            strpos($output, '"') !== false,
            'JS escaping should handle quotes'
        );
        
        echo "\n";
    }
}

// Run tests if this file is executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === 'SecurityTest.php') {
    $test = new SecurityTest();
    $success = $test->runAll();
    exit($success ? 0 : 1);
}