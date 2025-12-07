# Registration System Changes

## Overview
The registration system has been updated to only insert data into the `users` table during initial registration. Device and building data is now cached and inserted after successful email verification.

## Changes Made

### 1. Registration Flow
- **Before**: Registration inserted data into `users`, `devices`, and `buildings` tables immediately
- **After**: Registration only inserts into `users` table and caches device/building data

### 2. New Table: `pending_registrations`
A new table has been created to store device and building data temporarily:

```sql
CREATE TABLE `pending_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `pending_data` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
)
```

### 3. Email Verification Process
When a user verifies their email via the verification link:
1. User status changes to 'Active'
2. Email verification flag is set to true
3. Device data is inserted into `devices` table
4. Building data is inserted into `buildings` table
5. Pending registration record is deleted

### 4. Benefits
- **Data Integrity**: Unverified users don't have devices/buildings created
- **Cleaner Database**: No orphaned device/building records for unverified accounts
- **Better User Experience**: Only verified users get full account setup
- **Easier Cleanup**: Unverified accounts can be cleaned without worrying about related records

## Setup Instructions

### Run the Setup SQL
Execute the SQL file to create the pending_registrations table:

```bash
mysql -u your_username -p your_database < setup_pending_registrations.sql
```

Or run it through phpMyAdmin or your MySQL client.

### Automatic Table Creation
The table will also be created automatically on first registration if it doesn't exist.

## Data Flow

### Registration (registration.php)
1. User completes all registration steps
2. Data inserted into `users` table only
3. Device and building data stored in `pending_registrations` as JSON
4. Verification email sent to user

### Email Verification (verify_email.php)
1. User clicks verification link in email
2. System verifies token and checks expiry
3. User account activated (status='Active', email_verified=1)
4. Pending data retrieved from `pending_registrations`
5. Device and building records created
6. Pending registration record deleted

## Error Handling
- If pending data insertion fails during registration, the user account is still created
- If device/building insertion fails during verification, user is notified to contact support
- All errors are logged for debugging

## Maintenance
- Old pending registrations (>7 days) are automatically cleaned up by a scheduled event
- Failed verifications don't leave orphaned records due to CASCADE delete on user_id foreign key

## Testing Checklist
- [ ] New user registration creates only users table entry
- [ ] Pending data is stored in pending_registrations table
- [ ] Email verification creates device and building records
- [ ] Pending data is deleted after successful verification
- [ ] Unverified account deletion also removes pending data (CASCADE)
- [ ] Error messages are appropriate for failed operations




