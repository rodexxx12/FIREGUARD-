# Update Devices Building ID - Batch Script

## Overview

This script (`update_all_devices_building_id.php`) automatically updates the `building_id` field in the `devices` table for all devices that currently have `building_id = NULL`, based on whether they are physically located within any building's radius.

## How It Works

1. **Finds all devices** with `building_id = NULL`
2. **Checks GPS data** - Verifies each device has valid GPS coordinates in the `fire_data` table
3. **Validates location** - Uses the existing `autoValidateDeviceLocation()` function to check if the device is within any building's radius
4. **Updates database** - If a device is within a building radius, updates both:
   - `devices.building_id` → The matched building ID
   - `buildings.device_id` → The device ID (bidirectional relationship)

## Requirements

- Devices must have valid GPS coordinates in the `fire_data` table (`gps_latitude`, `gps_longitude`)
- Buildings must have valid coordinates in the `buildings` table (`latitude`, `longitude`)
- Building radius is stored in `building_areas` table (defaults to 100 meters if not specified)

## Usage

### Via Web Browser

Navigate to:
```
http://your-domain/userdashboard/mapping/php/update_all_devices_building_id.php
```

The script will display:
- A table showing the status of each device
- Summary statistics (total processed, updated, skipped)
- Details of successfully updated devices

### Via Command Line

```bash
cd userdashboard/mapping/php
php update_all_devices_building_id.php
```

## Output

The script provides detailed output including:

- **Device ID** - The device identifier
- **Device Name** - The name of the device
- **Status** - One of:
  - `UPDATED` - Device was successfully assigned to a building
  - `SKIPPED` - Device has no GPS data
  - `NOT FOUND` - Device is not within any building radius
- **Building ID** - The building ID assigned (if updated)
- **Distance** - Distance from device to building center in meters

## Example Output

```
=== Batch Update: Devices Building ID ===

Processing 2 device(s)...

[SUCCESS] Device ID 1 (User Device): Assigned to Building ID 5 (Main Building) - Distance: 15.23 meters
[SKIP] Device ID 2 (User Device): Device is not within any building radius.

=== Summary ===
Total Devices Processed: 2
Successfully Updated: 1
Skipped: 1
Errors: 0

Remaining devices with building_id = NULL: 1
```

## Notes

- **Only processes devices with `building_id = NULL`** - Devices that already have a building assignment are not modified
- **Uses latest GPS data** - The script uses the most recent GPS coordinates from the `fire_data` table
- **Bidirectional updates** - Both `devices.building_id` and `buildings.device_id` are updated in a transaction
- **Closest building** - If a device is within multiple building radii, it's assigned to the closest one
- **Safe to run multiple times** - The script only processes devices with `building_id = NULL`, so it's safe to run repeatedly

## Troubleshooting

### Device shows "No GPS data found"
- Ensure the device has sent GPS data to the `fire_data` table
- Check that `gps_latitude` and `gps_longitude` are not NULL in the `fire_data` table

### Device shows "Device is not within any building radius"
- Verify the building has valid coordinates (`latitude`, `longitude` in `buildings` table)
- Check the building radius in `building_areas` table (defaults to 100 meters)
- Ensure the device's GPS coordinates are actually within the building's radius

### No devices found
- All devices already have `building_id` assigned
- Check the `devices` table to see current assignments

## Related Files

- `functions/device_location_validator.php` - Contains the validation logic
- `php/validate_device_location.php` - Manual validation API endpoint
- `php/test_device_building_update.php` - Test script for individual devices

## Database Schema

### Devices Table
```sql
devices (
    device_id INT PRIMARY KEY,
    user_id INT,
    building_id INT NULL,  -- Updated by this script
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
    device_id INT NULL,  -- Updated by this script
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
    timestamp TIMESTAMP,
    ...
)
```

