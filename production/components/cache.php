<?php
/**
 * Lightweight caching helper to keep data/API responses warm without
 * changing existing business logic. Falls back to filesystem caching
 * when APCu is unavailable so it can run on shared hosting or CLI.
 */

class CacheHelper
{
    private const DEFAULT_TTL = 60;
    private const CACHE_DIR = __DIR__ . '/../../secure_storage/cache';

    /**
     * Wrap expensive operations (DB queries, API calls) with caching.
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        $ttl = $ttl > 0 ? $ttl : self::DEFAULT_TTL;
        if ($data = self::fetch($key)) {
            return $data;
        }

        $data = $callback();
        self::store($key, $data, $ttl);
        return $data;
    }

    /**
     * Generate deterministic cache keys.
     */
    public static function buildKey(string $namespace, array $payload = []): string
    {
        ksort($payload);
        return $namespace . ':' . hash('sha256', json_encode($payload));
    }

    public static function forget(string $key): void
    {
        if (self::apcuAvailable()) {
            apcu_delete($key);
        }

        $path = self::getFilePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function fetch(string $key)
    {
        if (self::apcuAvailable()) {
            $success = false;
            $data = apcu_fetch($key, $success);
            if ($success) {
                return $data;
            }
        }

        $path = self::getFilePath($key);
        if (!is_file($path)) {
            return null;
        }

        $payload = json_decode(@file_get_contents($path), true);
        if (!is_array($payload) || ($payload['expires_at'] ?? 0) < time()) {
            @unlink($path);
            return null;
        }

        return $payload['value'] ?? null;
    }

    private static function store(string $key, $value, int $ttl): void
    {
        if (self::apcuAvailable()) {
            apcu_store($key, $value, $ttl);
        }

        self::ensureCacheDir();
        $payload = [
            'expires_at' => time() + $ttl,
            'value' => $value
        ];

        @file_put_contents(self::getFilePath($key), json_encode($payload), LOCK_EX);
    }

    private static function getFilePath(string $key): string
    {
        return rtrim(self::CACHE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    private static function ensureCacheDir(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0750, true);
        }
    }

    private static function apcuAvailable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }
}

