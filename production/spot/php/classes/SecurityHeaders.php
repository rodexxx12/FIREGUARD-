<?php
/**
 * Security Headers Helper
 */
class SecurityHeaders {
    /**
     * Set all security headers
     * 
     * @param bool $isHttps Whether connection is HTTPS
     */
    public static function setAll($isHttps = false) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.datatables.net https://unpkg.com https://www.google.com https://www.gstatic.com; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://use.fontawesome.com https://unpkg.com https://cdn.datatables.net https://fonts.googleapis.com https://www.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://netdna.bootstrapcdn.com https://fonts.gstatic.com https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://use.fontawesome.com; " .
               "connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://cdn.datatables.net https://code.jquery.com https://www.google.com https://www.gstatic.com https://fonts.googleapis.com https://fonts.gstatic.com; " .
               "frame-src 'self' https://www.google.com;";
        
        header("Content-Security-Policy: $csp");
    }
}











