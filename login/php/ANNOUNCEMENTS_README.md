# Dynamic Announcements System

## Overview
The announcements system has been upgraded to dynamically fetch announcements from the database instead of using static content. The system now pulls announcements from both `admin` and `superadmin` tables where the target type is set to 'all'.

## Features

### 1. Database Integration
- Fetches announcements from `announcements` and `superadmin_announcements` tables
- Filters by `target_type = 'all'` in the respective target tables
- Only shows published announcements (`is_published = 1`)
- Respects date ranges (`start_date` and `end_date`)
- Orders by priority (high, medium, low) and creation date

### 2. Visual Enhancements
- **Priority Badges**: High priority announcements show with red pulsing badges
- **Author Information**: Shows who posted the announcement (Admin or Superadmin)
- **Responsive Design**: Works on all device sizes
- **Loading States**: Shows loading spinner while fetching data
- **Error Handling**: Graceful error messages if data fails to load

### 3. Real-time Updates
- **Auto-refresh**: Updates every 5 minutes automatically
- **Manual Refresh**: Users can click the refresh button
- **AJAX Loading**: No page reload required for updates

## Database Schema

### Required Tables
```sql
-- Admin announcements
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
);

-- Superadmin announcements
CREATE TABLE `superadmin_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
);

-- Target tables for audience control
CREATE TABLE `announcement_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `target_type` enum('all','user','firefighter','all_firefighters','admin','all_admins') NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `superadmin_announcement_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `target_type` enum('all','user','firefighter','all_firefighters','admin','all_admins') NOT NULL,
  PRIMARY KEY (`id`)
);
```

## Files Modified/Created

### New Files
1. `login/php/functions/get_announcements.php` - Main function to fetch announcements
2. `login/js/announcements.js` - JavaScript for dynamic loading and UI management
3. `login/php/test_announcements.php` - Test file to verify functionality

### Modified Files
1. `login/php/components/emergency-overlays.php` - Updated to use dynamic data
2. `login/css/login.css` - Added styles for priority badges and enhanced UI
3. `login/php/components/footer.php` - Added JavaScript file inclusion

## Usage

### For Admins/Superadmins
1. Create announcements in your respective admin panels
2. Set `target_type` to 'all' in the target tables
3. Set `is_published` to 1 to make them visible
4. Set appropriate `start_date` and `end_date` for scheduling

### For Users
1. Click the "Announcements" button in the emergency banner
2. View all public announcements
3. Use the refresh button to get latest updates
4. High priority announcements are highlighted with red badges

## Testing

To test the functionality:
1. Visit `login/php/test_announcements.php` in your browser
2. This will show database connection status and sample announcements
3. Verify that announcements appear correctly on the main page

## Troubleshooting

### Common Issues
1. **No announcements showing**: Check if announcements are published and have target_type='all'
2. **Database connection errors**: Verify database credentials in `db/db.php`
3. **JavaScript errors**: Check browser console for any script errors
4. **Styling issues**: Ensure CSS file is properly loaded

### Debug Mode
The system includes error logging. Check your server's error logs for detailed information about any issues.

## Performance Considerations

- Announcements are cached for 5 minutes to reduce database load
- Only the latest 10 announcements are fetched to maintain performance
- AJAX loading prevents full page reloads
- Database queries are optimized with proper indexing

## Security Features

- All user input is properly escaped to prevent XSS attacks
- SQL queries use prepared statements to prevent injection
- Error messages don't expose sensitive database information
- Content is sanitized before display 