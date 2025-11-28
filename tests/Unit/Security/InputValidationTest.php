<?php
/**
 * Unit Tests for Input Validation
 * 
 * Tests the input validation and sanitization functions
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../core/security/input_sanitizer.php';
require_once __DIR__ . '/../../../login/functions/security.php';

class InputValidationTest extends TestCase
{
    /**
     * Test string sanitization
     */
    public function testSanitizeString()
    {
        // Normal string
        $input = "Hello World";
        $result = sanitizeString($input);
        $this->assertEquals("Hello World", $result);
        
        // String with null bytes
        $input = "Hello\0World";
        $result = sanitizeString($input);
        $this->assertStringNotContainsString("\0", $result);
        
        // String with control characters
        $input = "Hello\x00\x08World";
        $result = sanitizeString($input);
        $this->assertStringNotContainsString("\x00", $result);
        
        // Null input
        $result = sanitizeString(null);
        $this->assertEquals("", $result);
    }
    
    /**
     * Test email sanitization
     */
    public function testSanitizeEmail()
    {
        // Valid email
        $email = "user@example.com";
        $result = sanitizeEmail($email);
        $this->assertEquals("user@example.com", $result);
        
        // Email with uppercase
        $email = "USER@EXAMPLE.COM";
        $result = sanitizeEmail($email);
        $this->assertEquals("user@example.com", $result);
        
        // Invalid email
        $email = "not-an-email";
        $result = sanitizeEmail($email);
        // Should still return something (filter_var sanitizes)
        $this->assertIsString($result);
    }
    
    /**
     * Test username validation
     */
    public function testValidateUsername()
    {
        // Valid username
        $result = validateUsername("testuser123");
        $this->assertTrue($result['valid']);
        
        // Too short
        $result = validateUsername("ab");
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('message', $result);
        
        // Contains invalid characters
        $result = validateUsername("test@user");
        $this->assertFalse($result['valid']);
        
        // Contains script tags (XSS attempt)
        $result = validateUsername("<script>alert('xss')</script>");
        $this->assertFalse($result['valid']);
    }
    
    /**
     * Test password validation
     */
    public function testValidatePassword()
    {
        // Valid password
        $result = validatePassword("SecurePass123!");
        $this->assertTrue($result['valid']);
        
        // Too short
        $result = validatePassword("Short1!");
        $this->assertFalse($result['valid']);
        
        // Missing uppercase
        $result = validatePassword("lowercase123!");
        $this->assertFalse($result['valid']);
        
        // Missing number
        $result = validatePassword("NoNumber!");
        $this->assertFalse($result['valid']);
    }
    
    /**
     * Test SQL injection prevention in validation
     */
    public function testSqlInjectionPrevention()
    {
        // SQL injection attempts should be rejected
        $injections = [
            "admin'--",
            "admin' OR '1'='1",
            "'; DROP TABLE users;--",
            "1' UNION SELECT * FROM users--"
        ];
        
        foreach ($injections as $injection) {
            $result = validateUsername($injection);
            // Should either be invalid or sanitized
            $this->assertTrue(
                !$result['valid'] || strpos($result['username'] ?? '', "'") === false,
                "SQL injection not prevented: {$injection}"
            );
        }
    }
    
    /**
     * Test XSS prevention in validation
     */
    public function testXssPrevention()
    {
        $xssAttempts = [
            "<script>alert('xss')</script>",
            "<img src=x onerror=alert('xss')>",
            "javascript:alert('xss')",
            "<iframe src='evil.com'></iframe>"
        ];
        
        foreach ($xssAttempts as $xss) {
            $result = validateUsername($xss);
            $this->assertFalse($result['valid'], "XSS not prevented: {$xss}");
        }
    }
}

