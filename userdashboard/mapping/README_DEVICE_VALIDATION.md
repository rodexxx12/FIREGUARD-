# Device Location Validation

This module validates that devices are within building radius before updating the building's `device_id`.

## Overview

When assigning a device to a building, the system:
1. Retrieves the device's latest GPS coordinates from the `fire_data` table
2. Gets the building's center coordinates and radius from the `buildings` and `building_areas` tables
3. Calculates the distance between the device and building center using the Haversine formula
4. Validates that the device is within the building's radius (default: 100 meters)
5. Updates both `buildings.device_id` and `devices.building_id` only if the device is inside the radius (bidirectional relationship)

## API Endpoint

### Validate Device Location

**URL:** `validate_device_location.php`

**Method:** `POST`

**Authentication:** Required (user must be logged in)

**Parameters:**
- `device_id` (required): The device ID to validate
- `building_id` (required): The building ID to check against

**Request Format:**
```json
{
  "device_id": 1,
  "building_id": 2
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Device is within building radius. Both building.device_id and device.building_id have been updated.",
  "distance": 15.23,
  "radius": 100.00,
  "device_id": 1,
  "building_id": 2
}
```

**Error Response (Device Outside Radius):**
```json
{
  "success": false,
  "message": "Device is outside building radius. Device must be inside the building radius.",
  "distance": 35.67,
  "radius": 100.00,
  "required_distance": 100.00
}
```

**Error Response (No GPS Data):**
```json
{
  "success": false,
  "message": "Device GPS coordinates not found. Device must have valid GPS data in fire_data table."
}
```

## Usage Examples

### JavaScript/AJAX Example

```javascript
// Validate device location and update building
function validateDeviceLocation(deviceId, buildingId) {
    fetch('php/validate_device_location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            device_id: deviceId,
            building_id: buildingId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Device validated and building updated!');
            console.log('Distance:', data.distance, 'meters');
        } else {
            alert('Validation failed: ' + data.message);
            console.log('Distance:', data.distance, 'meters (required: within', data.radius, 'meters)');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
```

### PHP Direct Usage

```php
require_once __DIR__ . '/functions/device_location_validator.php';
require_once __DIR__ . '/db/db.php';

$pdo = getMappingDBConnection();
$user_id = $_SESSION['user_id'];

// Validate and update
$result = validateAndUpdateDeviceLocation($device_id, $building_id, $pdo, $user_id);

if ($result['success']) {
    echo "Device is within building radius. Distance: " . $result['distance'] . " meters";
} else {
    echo "Validation failed: " . $result['message'];
}
```

### Auto-Validation (Find Building Automatically)

```php
require_once __DIR__ . '/functions/device_location_validator.php';
require_once __DIR__ . '/db/db.php';

$pdo = getMappingDBConnection();

// Automatically find which building the device is in
$result = autoValidateDeviceLocation($device_id, $pdo);

if ($result['success']) {
    echo "Device found in building ID: " . $result['building_id'];
    echo "Distance: " . $result['distance'] . " meters";
} else {
    echo "Device not within any building radius";
}
```

## Functions Available

### `validateAndUpdateDeviceLocation($device_id, $building_id, $pdo, $user_id = null)`

Validates if a device is within a specific building's radius and updates the building's `device_id` if valid.

**Parameters:**
- `$device_id`: Device ID
- `$building_id`: Building ID
- `$pdo`: PDO database connection
- `$user_id`: Optional user ID for validation (if null, skips user check)

**Returns:** Array with success status, message, and distance information

### `autoValidateDeviceLocation($device_id, $pdo)`

Automatically finds which building (if any) the device is within by checking all buildings for the device's user.

**Parameters:**
- `$device_id`: Device ID
- `$pdo`: PDO database connection

**Returns:** Array with success status and matched building information

### `autoValidateDeviceOnNewGPS($device_id, $gps_latitude = null, $gps_longitude = null, $pdo = null)`

Auto-validates device location when new GPS data is received. This function should be called after inserting fire_data with GPS coordinates. It will automatically find the building the device is in and update building.device_id.

**Parameters:**
- `$device_id`: Device ID
- `$gps_latitude`: Device GPS latitude (optional, will fetch latest if not provided)
- `$gps_longitude`: Device GPS longitude (optional, will fetch latest if not provided)
- `$pdo`: PDO database connection (optional, will create if not provided)

**Returns:** Array with success status and matched building information

**Usage Example:**
```php
// After inserting fire_data with GPS coordinates
require_once __DIR__ . '/userdashboard/mapping/functions/device_location_validator.php';

$result = autoValidateDeviceOnNewGPS($device_id, $gps_latitude, $gps_longitude);

if ($result['success']) {
    echo "Device assigned to building: " . $result['building_id'];
} else {
    echo "Device not within any building radius";
}
```

### `isDeviceInBuildingRadius($deviceLat, $deviceLon, $buildingLat, $buildingLon, $radius = 20.0)`

Checks if device coordinates are within building radius (utility function).

**Parameters:**
- `$deviceLat`: Device latitude
- `$deviceLon`: Device longitude
- `$buildingLat`: Building latitude
- `$buildingLon`: Building longitude
- `$radius`: Building radius in meters (default: 20.0)

**Returns:** Array with `inside` boolean and `distance` in meters

### `calculateDistanceMeters($lat1, $lon1, $lat2, $lon2)`

Calculates distance between two GPS coordinates using Haversine formula.

**Parameters:**
- `$lat1`, `$lon1`: First point coordinates
- `$lat2`, `$lon2`: Second point coordinates

**Returns:** Distance in meters

## Requirements

1. **Device GPS Data**: Devices must have valid GPS coordinates stored in the `fire_data` table (`gps_latitude`, `gps_longitude`)
2. **Building Coordinates**: Buildings must have valid coordinates in the `buildings` table (`latitude`, `longitude`)
3. **Building Radius**: Building radius is stored in `building_areas` table (default: 100 meters if not specified)

## Database Schema

### Buildings Table
- `id`: Building ID
- `latitude`: Building center latitude
- `longitude`: Building center longitude
- `device_id`: Assigned device ID (updated by validation)

### Devices Table
- `device_id`: Device ID
- `building_id`: Assigned building ID (updated by validation)

### Building Areas Table
- `building_id`: Building ID (foreign key)
- `center_latitude`: Building center latitude
- `center_longitude`: Building center longitude
- `radius`: Building radius in meters (default: 100.00)

### Fire Data Table
- `device_id`: Device ID
- `gps_latitude`: Device GPS latitude
- `gps_longitude`: Device GPS longitude
- `timestamp`: Timestamp of the reading

## Database Migration

Before using device validation, you must ensure the `buildings` table has a `device_id` column. Run the migration script:

```bash
# Via web browser
http://your-domain/userdashboard/mapping/db/migrate_add_device_id.php

# Or via command line
php userdashboard/mapping/db/migrate_add_device_id.php
```

Or manually run the SQL:
```sql
ALTER TABLE buildings 
ADD COLUMN device_id INT(11) DEFAULT NULL 
AFTER building_area;

ALTER TABLE buildings 
ADD INDEX idx_buildings_device_id (device_id);

ALTER TABLE buildings 
ADD FOREIGN KEY (device_id) 
REFERENCES devices(device_id) 
ON DELETE SET NULL 
ON UPDATE CASCADE;
```

## Notes

- The validation uses the **latest** GPS coordinates from the `fire_data` table for each device
- Building radius defaults to **100 meters** if not specified in `building_areas` table
- Distance calculation uses the **Haversine formula** for accurate GPS distance measurement
- Only devices that belong to the authenticated user can be validated
- Only buildings that belong to the authenticated user can be updated
- **Device must be inside building radius**: The system enforces that devices can only be assigned to buildings if they are physically within the building's radius
- **Bidirectional updates**: When validation succeeds, both `buildings.device_id` and `devices.building_id` are updated simultaneously in a transaction
- If a device is within multiple building radii, it will be assigned to the closest building

