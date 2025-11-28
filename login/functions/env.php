<?php
/**
 * Lightweight configuration loader for the login module.
 * UPDATED: Now uses centralized root-level .env file ONLY
 * Values are sourced from root-level `.env` file with OS env vars as fallback.
 */

if (!function_exists('loginEnv')) {
    /**
     * Parse centralized .env file once and cache the result.
     */
    function loadLoginEnv(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        
        // Try to use centralized environment loader first
        $coreEnvPath = __DIR__ . '/../../core/config/env.php';
        if (file_exists($coreEnvPath)) {
            require_once $coreEnvPath;
            // Environment variables are already loaded by core/env.php
            // Return cache will use $_ENV or getenv() below
            return $cache;
        }
        
        // Fallback: Load root-level .env file directly
        $envFile = __DIR__ . '/../../.env';
        if (!is_readable($envFile)) {
            return $cache;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $value = trim($value);
            $value = trim($value, "\"'");
            $cache[$key] = $value;
        }

        return $cache;
    }

    /**
     * Fetch a configuration value from centralized .env or OS environment variables.
     */
    function loginEnv(string $key, $default = null) {
        // Try centralized loader first
        $coreEnvPath = __DIR__ . '/../../core/config/env.php';
        if (file_exists($coreEnvPath)) {
            require_once $coreEnvPath;
            // Use centralized env() function if available
            if (function_exists('env')) {
                return env($key, $default);
            }
            // Otherwise check $_ENV or getenv()
            $value = $_ENV[$key] ?? getenv($key);
            if ($value !== false) {
                return $value;
            }
            return $default;
        }
        
        // Fallback: Use cached config
        $config = loadLoginEnv();
        if (array_key_exists($key, $config)) {
            return $config[$key];
        }

        $system = getenv($key);
        if ($system !== false) {
            return $system;
        }

        return $default;
    }

    /**
     * Convenience helper for boolean env flags.
     */
    function loginEnvBool(string $key, $default = false): bool {
        $value = loginEnv($key, null);
        if ($value === null) {
            return (bool)$default;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered === null) {
            return (bool)$default;
        }
        return $filtered;
    }
}

