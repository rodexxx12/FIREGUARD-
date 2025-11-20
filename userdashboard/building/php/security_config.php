<?php
/**
 * Building Registration Security Configuration
 * Configure security features for building registration system
 */

return [
    // SQL Injection Protection
    'sql_injection_protection' => [
        'enabled' => true,
        'validate_sql_patterns' => true,
        'validate_parameters' => true,
        'use_prepared_statements' => true,
        'log_dangerous_patterns' => true
    ],
    
    // Input Validation and Sanitization
    'input_validation' => [
        'enabled' => true,
        'sanitize_strings' => true,
        'validate_types' => true,
        'max_string_length' => 255,
        'max_address_length' => 500,
        'allowed_building_types' => [
            'residential', 'commercial', 'industrial', 
            'mixed_use', 'educational', 'healthcare', 'government'
        ],
        'coordinate_range' => [
            'min_latitude' => -90,
            'max_latitude' => 90,
            'min_longitude' => -180,
            'max_longitude' => 180
        ],
        'numeric_limits' => [
            'min_floors' => 1,
            'max_floors' => 200,
            'min_construction_year' => 1800,
            'max_construction_year' => null, // Will be set to current year
            'min_building_area' => 0,
            'max_building_area' => 999999.99
        ]
    ],
    
    // CSRF Protection
    'csrf_protection' => [
        'enabled' => true,
        'token_lifetime' => 3600, // 1 hour
        'regenerate_on_login' => true,
        'validate_on_all_forms' => true,
        'exclude_test_requests' => true
    ],
    
    // Rate Limiting
    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 5,
        'time_window' => 300, // 5 minutes
        'track_by_user_id' => true,
        'track_by_ip' => true,
        'separate_limits' => [
            'building_registration' => 3,
            'building_update' => 5,
            'building_deletion' => 2,
            'api_calls' => 10
        ]
    ],
    
    // Security Headers
    'security_headers' => [
        'enabled' => true,
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'DENY',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
    ],
    
    // File Upload Security
    'file_upload_security' => [
        'enabled' => true,
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
        'max_file_size' => 5242880, // 5MB
        'scan_for_malware' => false, // Requires additional setup
        'validate_mime_types' => true,
        'sanitize_filenames' => true,
        'upload_directory' => 'uploads/',
        'restrict_upload_path' => true
    ],
    
    // Security Logging
    'security_logging' => [
        'enabled' => true,
        'log_file' => 'security.log',
        'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'log_events' => [
            'sql_injection_attempts' => true,
            'csrf_violations' => true,
            'rate_limit_exceeded' => true,
            'validation_errors' => true,
            'authentication_failures' => true,
            'file_upload_attempts' => true,
            'dangerous_patterns' => true
        ],
        'max_log_size' => 10485760, // 10MB
        'rotate_logs' => true,
        'retention_days' => 30
    ],
    
    // Database Security
    'database_security' => [
        'enabled' => true,
        'use_secure_connection' => true,
        'validate_sql_queries' => true,
        'use_parameterized_queries' => true,
        'connection_timeout' => 30,
        'max_connections' => 100,
        'enable_query_logging' => false, // Only for debugging
        'sql_mode' => 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'
    ],
    
    // Session Security
    'session_security' => [
        'enabled' => true,
        'secure_cookies' => true,
        'httponly_cookies' => true,
        'session_regenerate_id' => true,
        'session_timeout' => 1800, // 30 minutes
        'validate_session_data' => true
    ],
    
    // Error Handling
    'error_handling' => [
        'enabled' => true,
        'hide_database_errors' => true,
        'log_all_errors' => true,
        'custom_error_pages' => false,
        'error_reporting_level' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED
    ],
    
    // Monitoring and Alerts
    'monitoring' => [
        'enabled' => false, // Requires additional setup
        'alert_on_multiple_failures' => true,
        'failure_threshold' => 5,
        'alert_email' => null,
        'monitor_suspicious_activity' => true,
        'track_user_behavior' => false
    ],
    
    // Development vs Production Settings
    'environment' => [
        'mode' => 'production', // development, staging, production
        'debug_mode' => false,
        'show_errors' => false,
        'log_verbose' => false,
        'enable_profiling' => false
    ]
];
?>
