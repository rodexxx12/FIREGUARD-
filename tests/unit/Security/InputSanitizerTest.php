<?php
/**
 * Unit Tests for Input Sanitizer Module
 * 
 * Tests sanitization functions for security
 */

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

class InputSanitizerTest extends TestCase
{
    /**
     * Test sanitizeString removes dangerous characters
     */
    public function testSanitizeStringRemovesNullBytes(): void
    {
        $input = "test\0string";
        $result = sanitizeString($input);
        $this->assertStringNotContainsString("\0", $result);
        $this->assertEquals("teststring", $result);
    }
    
    /**
     * Test sanitizeString handles null input
     */
    public function testSanitizeStringHandlesNull(): void
    {
        $result = sanitizeString(null);
        $this->assertEquals('', $result);
    }
    
    /**
     * Test sanitizeString handles non-scalar input
     */
    public function testSanitizeStringHandlesNonScalar(): void
    {
        $result = sanitizeString(['array']);
        $this->assertEquals('', $result);
    }
    
    /**
     * Test sanitizeString trims whitespace
     */
    public function testSanitizeStringTrimsWhitespace(): void
    {
        $input = "  test string  ";
        $result = sanitizeString($input);
        $this->assertEquals("test string", $result);
    }
    
    /**
     * Test sanitizeEmail validates email format
     */
    public function testSanitizeEmailValidFormat(): void
    {
        $email = "test@example.com";
        $result = sanitizeEmail($email);
        $this->assertEquals("test@example.com", $result);
    }
    
    /**
     * Test sanitizeEmail rejects invalid format
     */
    public function testSanitizeEmailInvalidFormat(): void
    {
        $email = "not-an-email";
        $result = sanitizeEmail($email);
        $this->assertEquals('', $result);
    }
    
    /**
     * Test sanitizeInt converts to integer
     */
    public function testSanitizeIntConvertsString(): void
    {
        $input = "123";
        $result = sanitizeInt($input);
        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
    }
    
    /**
     * Test sanitizeInt handles invalid input
     */
    public function testSanitizeIntHandlesInvalid(): void
    {
        $input = "not-a-number";
        $result = sanitizeInt($input);
        $this->assertEquals(0, $result);
    }
    
    /**
     * Test sanitizeInt handles null
     */
    public function testSanitizeIntHandlesNull(): void
    {
        $result = sanitizeInt(null);
        $this->assertEquals(0, $result);
    }
    
    /**
     * Test sanitizeFloat converts to float
     */
    public function testSanitizeFloatConvertsString(): void
    {
        $input = "123.45";
        $result = sanitizeFloat($input);
        $this->assertIsFloat($result);
        $this->assertEquals(123.45, $result);
    }
    
    /**
     * Test sanitizeFloat handles invalid input
     */
    public function testSanitizeFloatHandlesInvalid(): void
    {
        $input = "not-a-number";
        $result = sanitizeFloat($input);
        $this->assertEquals(0.0, $result);
    }
}













