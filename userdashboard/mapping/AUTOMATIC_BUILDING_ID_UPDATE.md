# Automatic Building ID Update Implementation

## Overview

The system now automatically updates `devices.building_id` when devices send GPS data and are located within building areas. This happens in real-time as devices transmit their location data.

## How It Works

### Automatic Update Flow

1. **Device sends GPS data** → Device sends sensor data with GPS coordinates to `smoke_api.php`
2. **Fire data inserted** → GPS coordinates are stored in `fire_data` table
3. **Automatic validation** → System checks if device is within any building's radius
4. **Building assignment** → If device is within a building area, `devices.building_id` is automatically updated

### Implementation Details

#### Modified File: `device/smoke_api.php`

The `insertFireData()` method now automatically validates device location after inserting GPS data:

```php
// After successfully inserting fire_data
if ($this->gps_latitude != 0.0 && $this->gps_longitude != 0.0 && $this->gps_valid) {
    $this->autoValidateDeviceBuilding($this->device_id, $this->gps_latitude, $this->gps_longitude);
}
```

#### New Method: `autoValidateDeviceBuilding()`

This method:
- Connects to the local `firedb` database (where buildings are stored)
- Uses the existing `autoValidateDeviceOnNewGPS()` function
- Updates `devices.building_id` and `buildings.device_id` if device is within building radius
- Logs success/failure without interrupting the main data insertion

## Requirements

1. **GPS Data**: Device must send valid GPS coordinates (`gps_latitude`, `gps_longitude`, `gps_valid = 1`)
2. **Building Areas**: Buildings must have coordinates and radius defined in `building_areas` table
3. **Database**: Buildings and devices must be in the same database (`firedb`)

## Database Schema

### Devices Table
```sql
devices (
    device_id INT PRIMARY KEY,
    user_id INT,
    building_id INT NULL,  -- Automatically updated
    ...
)
```

### Buildings Table
```sql
buildings (
    id INT PRIMARY KEY,
    user_id INT,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    device_id INT NULL,  -- Automatically updated
    ...
)
```

### Building Areas Table
```sql
building_areas (
    building_id INT PRIMARY KEY,
    center_latitude DECIMAL(10,8),
    center_longitude DECIMAL(11,8),
    radius DECIMAL(10,2) DEFAULT 100.00,  -- Radius in meters
    ...
)
```

### Fire Data Table
```sql
fire_data (
    device_id INT,
    gps_latitude DECIMAL(10,8),
    gps_longitude DECIMAL(11,8),
    gps_valid TINYINT(1),
    ...
)
```

## Validation Rules

1. **GPS must be valid**: `gps_valid = 1` and coordinates must not be `0.0`
2. **Building must have coordinates**: Building must have `latitude` and `longitude` in `buildings` table
3. **Device must be within radius**: Distance from device to building center must be ≤ building radius
4. **Default radius**: If building doesn't have a radius in `building_areas`, defaults to 100 meters
5. **Closest building**: If device is within multiple building radii, it's assigned to the closest one

## Logging

The system logs building assignments for monitoring:

- **Success**: `Device {device_id} automatically assigned to building {building_id} (distance: {distance}m)`
- **Errors**: Only logged if validation fails for reasons other than "not within any building radius"

## Manual Update

If you need to manually update devices that already have GPS data:

1. **Batch Update Script**: Run `update_all_devices_building_id.php`
   ```
   http://your-domain/userdashboard/mapping/php/update_all_devices_building_id.php
   ```

2. **Individual Device**: Use the test script
   ```
   http://your-domain/userdashboard/mapping/php/test_device_building_update.php?device_id=1
   ```

## Testing

To test automatic updates:

1. Ensure a device has valid GPS coordinates
2. Ensure a building has coordinates and radius defined
3. Send new GPS data from the device
4. Check `devices.building_id` - it should be automatically updated if device is within building radius

## Troubleshooting

### Device not being assigned to building

1. **Check GPS data**: Verify `gps_valid = 1` and coordinates are not `0.0`
2. **Check building coordinates**: Verify building has `latitude` and `longitude`
3. **Check building radius**: Verify `building_areas` table has radius for the building
4. **Check distance**: Device might be outside the building radius
5. **Check logs**: Look for error messages in PHP error log

### Building assignment not working

1. **Database connection**: Ensure `db/db.php` is accessible from `device/smoke_api.php`
2. **Path issues**: Verify paths to `device_location_validator.php` are correct
3. **Database permissions**: Ensure database user has UPDATE permissions on `devices` and `buildings` tables

## Related Files

- `device/smoke_api.php` - Main API endpoint that triggers automatic validation
- `userdashboard/mapping/functions/device_location_validator.php` - Validation logic
- `userdashboard/mapping/php/update_all_devices_building_id.php` - Batch update script
- `userdashboard/mapping/php/test_device_building_update.php` - Test script

## Notes

- **Non-blocking**: Building validation failures don't prevent fire_data insertion
- **Real-time**: Updates happen immediately when GPS data is received
- **Bidirectional**: Both `devices.building_id` and `buildings.device_id` are updated
- **Transaction-safe**: Updates are done in a database transaction
- **Idempotent**: Safe to run multiple times - only updates if device is within radius

