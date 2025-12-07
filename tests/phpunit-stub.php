<?php
/**
 * PHPUnit Stub File
 * 
 * This file provides type hints for PHPUnit when it's not installed yet.
 * It allows the IDE/linter to recognize PHPUnit classes without having
 * the actual PHPUnit package installed.
 * 
 * This file should be removed or ignored once PHPUnit is properly installed
 * via 'composer install' after enabling the PHP zip extension.
 * 
 * See docs/FIX_PHP_ZIP_EXTENSION.md for installation instructions.
 */

// Only define these if PHPUnit is not actually loaded
if (!class_exists('PHPUnit\Framework\TestCase')) {
    namespace PHPUnit\Framework {
        /**
         * Stub class for PHPUnit TestCase
         * @phpstan-ignore-next-line
         */
        abstract class TestCase
        {
            /**
             * Mark test as incomplete
             * @param string $message
             * @return never
             */
            protected function markTestIncomplete(string $message = ''): void
            {
                // Stub implementation
            }
            
            /**
             * Assert that a condition is true
             * @param bool $condition
             * @param string $message
             */
            protected function assertTrue(bool $condition, string $message = ''): void
            {
                // Stub implementation
            }
            
            /**
             * Assert equals
             * @param mixed $expected
             * @param mixed $actual
             * @param string $message
             */
            protected function assertEquals($expected, $actual, string $message = ''): void
            {
                // Stub implementation
            }
            
            /**
             * Assert instance of
             * @param string $expected
             * @param mixed $actual
             * @param string $message
             */
            protected function assertInstanceOf(string $expected, $actual, string $message = ''): void
            {
                // Stub implementation
            }
            
            /**
             * Setup method called before each test
             */
            protected function setUp(): void
            {
                // Stub implementation
            }
            
            /**
             * Teardown method called after each test
             */
            protected function tearDown(): void
            {
                // Stub implementation
            }
        }
    }
}













