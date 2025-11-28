<?php
/**
 * Rate Limiting Helper
 */
class RateLimiter {
    private $cacheDir;
    
    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir() . '/rate_limit';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0700, true);
        }
    }
    
    /**
     * Check if request is within rate limit
     * 
     * @param string $key Unique identifier (user ID, IP, etc.)
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $window Time window in seconds
     * @return bool True if within limit
     */
    public function checkLimit($key, $maxAttempts = 10, $window = 60) {
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.json';
        $attempts = [];
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $attempts = $data;
            }
        }
        
        // Remove old attempts
        $now = time();
        $attempts = array_filter($attempts, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $attempts[] = $now;
        file_put_contents($cacheFile, json_encode($attempts), LOCK_EX);
        
        return true;
    }
    
    /**
     * Get remaining attempts
     * 
     * @param string $key Unique identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $window Time window in seconds
     * @return int Remaining attempts
     */
    public function getRemaining($key, $maxAttempts = 10, $window = 60) {
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.json';
        $attempts = [];
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $attempts = $data;
            }
        }
        
        $now = time();
        $attempts = array_filter($attempts, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        return max(0, $maxAttempts - count($attempts));
    }
}






