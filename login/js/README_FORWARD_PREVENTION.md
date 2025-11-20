# Forward Navigation Prevention

This script prevents users from using the browser's forward button after logging in and then clicking the back button. It shows a toast notification when forward navigation is attempted.

## Features

- ✅ Disables browser forward button after login
- ✅ Shows toast notification when forward is attempted
- ✅ Works automatically once included
- ✅ Mobile responsive toast notifications
- ✅ No dependencies required

## Usage

Include the script in your dashboard pages (after login redirect):

```html
<!-- Include this script in dashboard pages -->
<script src="login/js/prevent-forward-navigation.js"></script>
```

### Example for different user types:

**For Admin Dashboard** (`production/mapping/php/map.php`):
```html
<script src="../../login/js/prevent-forward-navigation.js"></script>
```

**For User Dashboard** (`userdashboard/mapping/php/main.php`):
```html
<script src="../../login/js/prevent-forward-navigation.js"></script>
```

**For Firefighter Dashboard** (`fireFighter/mapping/php/main.php`):
```html
<script src="../../login/js/prevent-forward-navigation.js"></script>
```

**For Superadmin Dashboard** (`superadmin/statistics/php/index.php`):
```html
<script src="../../../login/js/prevent-forward-navigation.js"></script>
```

## How It Works

1. When a user logs in and is redirected to a dashboard page, the script automatically initializes
2. It creates a "barrier" in the browser history to prevent forward navigation
3. If the user clicks the back button and then tries to go forward, the forward navigation is blocked
4. A toast notification appears informing the user that forward navigation is disabled

## Toast Notification

The script includes a built-in toast notification system. You can also use it programmatically:

```javascript
// Show a toast notification
showToast('Your message here', 'warning', 'Title');
// Types: 'info', 'success', 'warning', 'error'
```

## Notes

- The script only activates on pages that are NOT the login/index page
- It automatically detects when to activate based on the page URL
- The toast notification auto-dismisses after 5 seconds
- Users can manually close the toast by clicking the × button

