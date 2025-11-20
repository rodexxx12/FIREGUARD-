# Device Validation Implementation Summary

## Overview

This implementation ensures that devices can only be assigned to buildings if they are physically located within the building's radius. The system validates device GPS coordinates against building coordinates and radius before updating the `device_id` in the `buildings` table.

## Key Features

1. **Radius Validation**: Devices must be within the building's radius (default: 100 meters) to be assigned
2. **Bidirectional Updates**: Updates both `buildings.device_id` and `devices.building_id` when validation succeeds
3. **Transaction Safety**: Uses database transactions to ensure both updates succeed or fail together
4. **Automatic Validation**: Can automatically validate and assign devices when new GPS data is received
5. **Manual Validation**: API endpoint for manual device-to-building assignment with validation
6. **Distance Calculation**: Uses Haversine formula for accurate GPS distance measurement

## Files Created/Modified

### New Files

1. **`db/add_device_id_to_buildings.sql`**
   - SQL migration script to add `device_id` column to `buildings` table

2. **`db/migrate_add_device_id.php`**
   - PHP migration script that safely adds `device_id` column if it doesn't exist
   - Can be run via web browser or command line

3. **`IMPLEMENTATION_SUMMARY.md`** (this file)
   - Documentation of the implementation

### Modified Files

1. **`functions/device_location_validator.php`**
   - Added `autoValidateDeviceOnNewGPS()` function for automatic validation when GPS data is received
   - Enhanced validation logic to handle multiple building matches (chooses closest)

2. **`php/validate_device_location.php`**
   - Added check for `device_id` column existence
   - Provides helpful error message if migration hasn't been run

3. **`README_DEVICE_VALIDATION.md`**
   - Updated with new function documentation
   - Added migration instructions
   - Added notes about radius enforcement

## Database Changes

### Buildings Table
- **Column**: `device_id INT(11) DEFAULT NULL` (updated by validation)
- **Index**: `idx_buildings_device_id` on `device_id`
- **Foreign Key**: References `devices(device_id)` with `ON DELETE SET NULL ON UPDATE CASCADE`

### Devices Table
- **Column**: `building_id INT(11) DEFAULT NULL` (updated by validation)
- **Index**: `idx_devices_building_id` on `building_id`

## Validation Flow

### Manual Validation (API Call)
1. User calls `validate_device_location.php` with `device_id` and `building_id`
2. System checks if device belongs to user
3. System checks if building belongs to user
4. System retrieves latest GPS coordinates from `fire_data` table
5. System retrieves building coordinates and radius from `buildings` and `building_areas` tables
6. System calculates distance using Haversine formula
7. **If device is within radius**: Updates both `buildings.device_id` and `devices.building_id` in a transaction
8. **If device is outside radius**: Returns error, does not update either table

### Automatic Validation (On GPS Data)
1. When new GPS data is inserted into `fire_data` table
2. Call `autoValidateDeviceOnNewGPS($device_id, $gps_latitude, $gps_longitude)`
3. System finds all buildings for the device's user
4. System checks which building(s) the device is within
5. If multiple matches, assigns to closest building
6. Updates both `buildings.device_id` and `devices.building_id` for the matched building in a transaction

## Usage

### Step 1: Run Migration
```bash
# Via web browser
http://your-domain/userdashboard/mapping/db/migrate_add_device_id.php

# Or via command line
php userdashboard/mapping/db/migrate_add_device_id.php
```

### Step 2: Manual Validation
```javascript
// JavaScript/AJAX
fetch('php/validate_device_location.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        device_id: 1,
        building_id: 2
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Device validated and assigned!');
    } else {
        console.log('Validation failed:', data.message);
    }
});
```

### Step 3: Automatic Validation (Optional)
```php
// In device API files after inserting fire_data with GPS
require_once __DIR__ . '/userdashboard/mapping/functions/device_location_validator.php';

$result = autoValidateDeviceOnNewGPS($device_id, $gps_latitude, $gps_longitude);

if ($result['success']) {
    error_log("Device {$device_id} assigned to building {$result['building_id']}");
}
```

## Validation Rules

1. **Device must have GPS data**: Device must have valid `gps_latitude` and `gps_longitude` in `fire_data` table
2. **Building must have coordinates**: Building must have valid `latitude` and `longitude` in `buildings` table
3. **Device must be within radius**: Distance from device to building center must be ≤ building radius
4. **User ownership**: Device and building must belong to the same user
5. **Radius default**: If building doesn't have a radius in `building_areas`, defaults to 100 meters
6. **Bidirectional updates**: Both `buildings.device_id` and `devices.building_id` are updated together in a transaction

## Error Handling

The system returns clear error messages for:
- Device GPS coordinates not found
- Building coordinates not found
- Device outside building radius
- Database errors
- Missing required parameters
- Unauthorized access

## Testing

To test the validation:

1. Ensure migration has been run
2. Create a building with coordinates and radius
3. Insert fire_data with GPS coordinates for a device
4. Call validation API or use automatic validation
5. Verify `buildings.device_id` is updated only if device is within radius

## Notes

- The validation is **strict**: Devices outside the radius will NOT be assigned
- **Bidirectional relationship**: Both `buildings.device_id` and `devices.building_id` are kept in sync
- **Transaction safety**: Updates to both tables are done in a transaction to ensure data consistency
- If a device moves outside a building's radius, the relationship will remain until manually cleared or reassigned
- Multiple devices can be assigned to the same building if they're all within radius
- If a device is within multiple building radii, it will be assigned to the closest building

