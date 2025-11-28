<?php
/**
 * Unit Tests for CSRF Protection
 */

use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    /**
     * Test CSRF token generation
     */
    public function testCsrfTokenGeneration()
    {
        // Start session for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        require_once __DIR__ . '/../../../core/security/csrf.php';
        
        // Generate token
        $token = csrfGenerateToken('test_form');
        
        // Token should be a string
        $this->assertIsString($token);
        
        // Token should be 64 characters (32 bytes in hex)
        $this->assertEquals(64, strlen($token));
        
        // Token should be stored in session
        $this->assertArrayHasKey('csrf_tokens', $_SESSION);
        $this->assertArrayHasKey('test_form', $_SESSION['csrf_tokens']);
    }
    
    /**
     * Test CSRF token validation
     */
    public function testCsrfTokenValidation()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        require_once __DIR__ . '/../../../core/security/csrf.php';
        
        // Generate and validate token
        $token = csrfGenerateToken('validation_test');
        $isValid = csrfValidateToken($token, 'validation_test');
        
        $this->assertTrue($isValid);
        
        // Invalid token should fail
        $isValid = csrfValidateToken('invalid_token', 'validation_test');
        $this->assertFalse($isValid);
        
        // Wrong form should fail
        $token2 = csrfGenerateToken('another_form');
        $isValid = csrfValidateToken($token2, 'validation_test');
        $this->assertFalse($isValid);
    }
    
    /**
     * Test CSRF token expiration
     */
    public function testCsrfTokenExpiration()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        require_once __DIR__ . '/../../../core/security/csrf.php';
        
        // Generate token with short TTL
        $token = csrfGenerateToken('expiry_test', 1); // 1 second TTL
        
        // Should be valid immediately
        $this->assertTrue(csrfValidateToken($token, 'expiry_test'));
        
        // Wait for expiration
        sleep(2);
        
        // Should be invalid after expiration
        $this->assertFalse(csrfValidateToken($token, 'expiry_test'));
    }
}

