# Logs Directory

This directory contains application error logs.

## Important Security Notes:

1. **Never commit log files to version control** - They may contain sensitive information
2. **Log files are automatically ignored** via `.gitignore`
3. **Direct access is blocked** via `.htaccess`
4. **Regularly rotate and archive old logs** to prevent disk space issues

## Log Files:

- `php_errors.log` - General PHP errors (production)
- `php_errors_dev.log` - General PHP errors (development)
- `device_api_errors.log` - Device API errors
- `production_errors.log` - Production module errors
- `firefighter_errors.log` - Firefighter module errors

## Log Rotation:

Set up log rotation using:
- Linux: logrotate
- Windows: Task Scheduler with PowerShell script
- Or use your hosting provider's log rotation service

