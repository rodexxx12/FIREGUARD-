# Database Connection Guide for FireFighter Components

## Overview
All components in the FireFighter system are now connected to a centralized database configuration located in `fireFighter/db/db.php`. This provides consistent, secure, and efficient database access across all components.

## Quick Start

### For Components That Need Database Access

1. **Include the database connection:**
```php
<?php
require_once('db-connect.php');
?>
```

2. **Use the available helper functions:**
```php
// Get current user data (automatically available)
if ($currentUser) {
    echo "Welcome, " . $currentUser['full_name'];
}

// Get any user's profile
$userProfile = getUserProfile('username');

// Update user profile
$updateData = ['email' => 'new@email.com', 'phone' => '123-456-7890'];
updateUserProfile('username', $updateData);

// Check if user exists
if (checkUserExists('username')) {
    echo "User exists";
}
```

## Available Functions

### Core Database Functions
- `executeQuery($sql, $params)` - Execute prepared statements
- `fetchSingle($sql, $params)` - Get single row
- `fetchAll($sql, $params)` - Get all rows
- `getDatabaseConnection()` - Get PDO connection

### User Management Functions
- `getUserProfile($username)` - Get user profile data
- `updateUserProfile($username, $data)` - Update user profile
- `checkUserExists($username)` - Check if user exists
- `getUserSession()` - Get current session user data

### Security Functions
- `sanitizeInput($input)` - Sanitize user input
- `validateUserAccess($requiredRole)` - Validate user access
- `logActivity($action, $details)` - Log user activity

## Security Features

### Input Sanitization
```php
$cleanInput = sanitizeInput($_POST['user_input']);
```

### Access Validation
```php
// Check if user is logged in
if (!validateUserAccess()) {
    header('Location: login.php');
    exit();
}

// Check specific role
if (!validateUserAccess('admin')) {
    die('Access denied');
}
```

### Activity Logging
```php
logActivity('profile_update', 'Updated email address');
```

## Database Configuration

The database configuration is centralized in `fireFighter/db/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'firedb');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

## Error Handling

- All database operations include proper error handling
- Errors are logged to the server error log
- User-friendly error messages are displayed
- Database connection failures are handled gracefully

## Example Component Usage

### profile.php (Updated)
```php
<?php
require_once('db-connect.php');

$default_image = '../../images/profile1.jpg';
$profile_image_url = $default_image;

if ($currentUser && !empty($currentUser['profile_image']) && 
    file_exists("../../profile/uploads/profile_images/" . $currentUser['profile_image'])) {
    $profile_image_url = '../../profile/uploads/profile_images/' . htmlspecialchars($currentUser['profile_image']);
}
?>
```

### Custom Component Example
```php
<?php
require_once('db-connect.php');

// Validate access
if (!validateUserAccess()) {
    header('Location: ../../login/index.php');
    exit();
}

// Get user data
$userData = getUserSession();

// Log activity
logActivity('component_access', 'Accessed custom component');

// Custom database query
$customData = fetchAll("SELECT * FROM custom_table WHERE user_id = ?", [$userData['id']]);

// Sanitize output
foreach ($customData as $row) {
    echo sanitizeInput($row['data']);
}
?>
```

## Migration Notes

### What Changed
1. **Centralized Configuration**: All database settings are now in one place
2. **PDO Instead of MySQLi**: More secure and modern approach
3. **Helper Functions**: Common operations are now simplified
4. **Security Improvements**: Input sanitization and access validation
5. **Error Handling**: Better error management and logging

### Backward Compatibility
- Old `getDBConnection()` function still works
- Existing code will continue to function
- Gradual migration recommended

## Best Practices

1. **Always use prepared statements** - Never concatenate user input into SQL
2. **Sanitize all user input** - Use `sanitizeInput()` function
3. **Validate access** - Check user permissions before sensitive operations
4. **Log important activities** - Track user actions for security
5. **Handle errors gracefully** - Don't expose database errors to users
6. **Use helper functions** - Leverage the provided functions for common operations

## Troubleshooting

### Common Issues
1. **Database connection fails**: Check credentials in `db.php`
2. **Function not found**: Ensure `db-connect.php` is included
3. **Permission denied**: Check user access with `validateUserAccess()`
4. **Data not displaying**: Verify user session and profile data

### Debug Mode
To enable debug mode, add this to your component:
```php
define('DEVELOPMENT_MODE', true);
require_once('db-connect.php');
```

This will show detailed error messages for debugging.
