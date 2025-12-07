# Registration Flow - Updated with Confirmation Step

## What Changed

The registration system now **caches all data** (including credentials) and only inserts into the database when the user confirms on the final review page.

## New Registration Flow

### Step 1: Personal Information
- Collects: Full name, birthdate, age, email, contact number
- **Action:** Validates and stores in `$_SESSION['reg_data']`
- **Database:** NO insertion

### Step 2: Location & Building
- Collects: Address, building name, building type, coordinates, barangay
- **Action:** Validates and stores in `$_SESSION['reg_data']`
- **Database:** NO insertion

### Step 3: Device Information
- Collects: Device number, serial number, device barangay
- **Action:** Validates and stores in `$_SESSION['reg_data']`
- **Database:** NO insertion

### Step 4: Credentials
- Collects: Username, password, reCAPTCHA verification
- **Action:** Validates, hashes password, stores in `$_SESSION['reg_data']`
- **Database:** NO insertion
- **Next:** Redirects to confirmation step

### Step 5: Confirmation (NEW!)
- **Shows:** Review of all entered information
- **Options:** Edit any section or complete registration
- **Action:** When "Complete Registration" is clicked:
  1. Inserts user into `users` table
  2. Stores device/building data in `pending_registrations` table
  3. Sends verification email
  4. Clears session data
- **Database:** YES - inserts into `users` and `pending_registrations`

## Benefits

### 1. No Premature Data Insertion
- User data is NOT inserted until they explicitly confirm
- Prevents database pollution from incomplete registrations
- Users can review and edit before committing

### 2. Better User Experience
- Users can review all their information
- Easy to go back and edit any section
- Clear confirmation before account creation

### 3. Data Integrity
- All validation happens before database insertion
- Reduces orphaned records
- Cleaner database

### 4. Session-Based Caching
- All data stored in PHP session
- Survives page refreshes
- Automatically cleared after successful registration

## User Interface

### Confirmation Page Features

#### Review Sections
- **Personal Information** - Name, birthdate, email, contact
- **Location & Building** - Address, building details, coordinates
- **Device Information** - Device number, serial number
- **Login Credentials** - Username (password hidden)

#### Edit Buttons
Each section has an "Edit" button that takes the user back to that specific step.

#### Final Confirmation
- Warning message about data accuracy
- Green "Complete Registration" button
- Back button to return to credentials

## Technical Implementation

### Session Data Structure
```php
$_SESSION['reg_data'] = [
    // Personal
    'fullname' => 'John Doe',
    'birthdate' => '1990-01-01',
    'age' => 34,
    'email' => 'john@example.com',
    'contact' => '09123456789',
    
    // Location
    'address' => '123 Main St, Bago City',
    'building_name' => 'My Building',
    'building_type' => 'Residential',
    'latitude' => 10.1234,
    'longitude' => 122.5678,
    'barangay_id' => 1,
    
    // Device
    'device_number' => 'DEV123',
    'serial_number' => 'SN456',
    'device_barangay_id' => 1,
    
    // Credentials
    'username' => 'johndoe',
    'password_hash' => '$2y$10$...',
    'registration_ready' => true
];
```

### Database Insertion (Confirmation Step)
```php
// Only happens when user clicks "Complete Registration" on confirmation page
INSERT INTO users (...) VALUES (...);
INSERT INTO pending_registrations (...) VALUES (...);
```

## Step Sequence

```
Personal → Location → Device → Credentials → Confirmation → Success
   ↓          ↓          ↓          ↓             ↓
 Cache      Cache      Cache      Cache      INSERT DB
```

## Navigation

### Forward Navigation
- Each step validates and caches data
- Redirects to next step on success
- Cannot skip steps

### Backward Navigation
- "Back" buttons on each step
- "Edit" buttons on confirmation page
- Session data preserved when going back

### Step Protection
- Cannot access later steps without completing earlier ones
- Session validation on each step
- Redirects to appropriate step if data missing

## Error Handling

### Validation Errors
- Shown on the current step
- User can fix and resubmit
- Data remains in session

### Database Errors
- Only occur on confirmation step
- Specific error messages (duplicate email, username, device)
- Session data preserved for retry
- User can go back and modify data

### Session Expiration
- If session expires, user must start over
- Clear error message displayed
- Redirected to step 1

## Security Features

### Password Handling
- Password hashed immediately after validation
- Only hash stored in session (not plain password)
- Hash used for database insertion

### reCAPTCHA
- Verified on credentials step (before caching)
- Prevents bot registrations
- Required before proceeding to confirmation

### CSRF Protection
- Session-based validation
- Prevents cross-site request forgery
- All forms use POST method

## Testing Checklist

- [ ] Complete all 5 steps successfully
- [ ] Verify no database insertion until confirmation
- [ ] Test "Edit" buttons on confirmation page
- [ ] Test "Back" buttons on each step
- [ ] Verify session data persists across steps
- [ ] Test validation on each step
- [ ] Test database insertion on confirmation
- [ ] Verify email verification sent
- [ ] Test error handling (duplicate email, username)
- [ ] Verify session cleared after success

## Database Tables

### users
- Inserted on confirmation step
- Contains: personal info, credentials, verification token
- Status: 'Inactive' until email verified

### pending_registrations
- Inserted on confirmation step
- Contains: device and building data as JSON
- Deleted after email verification

## Files Modified

1. **registration.php**
   - Added confirmation step handler (`confirm_submit`)
   - Modified credentials handler (cache only, no DB insert)
   - Added confirmation page UI
   - Updated step sequence and navigation
   - Added CSS for confirmation page

## Migration Notes

### For Existing Users
- No impact on existing users
- Only affects new registrations
- Database schema unchanged

### For Administrators
- Monitor `pending_registrations` table
- Old pending records auto-deleted after 7 days
- No manual intervention needed

## Troubleshooting

### Issue: Session data lost
**Solution:** Check PHP session configuration, ensure cookies enabled

### Issue: Can't proceed to confirmation
**Solution:** Verify all previous steps completed, check session data

### Issue: Database error on confirmation
**Solution:** Check for duplicate email/username/device, verify database connection

### Issue: Edit button doesn't preserve data
**Solution:** Session data should persist, check session handling

## Future Enhancements

1. **Save Draft** - Allow users to save progress and resume later
2. **Email Draft** - Send draft link to user's email
3. **Progress Indicator** - Show percentage complete
4. **Auto-save** - Automatically save to session on field blur
5. **Timeout Warning** - Warn user before session expires

---

**Version:** 2.0  
**Last Updated:** 2024  
**Status:** ✅ Implemented and Ready







