# Building Areas Table Setup

This document explains how to set up and use the `building_areas` table to store latitude and longitude coordinates for building areas (100-meter radius circles).

## Table Structure

The `building_areas` table stores:
- **building_id**: Foreign key to the buildings table
- **center_latitude**: Center point latitude of the area
- **center_longitude**: Center point longitude of the area
- **radius**: Radius in meters (default: 100 meters)
- **boundary_coordinates**: Optional JSON array of boundary points
- **created_at**: Timestamp when record was created
- **updated_at**: Timestamp when record was last updated

## Setup Instructions

### Option 1: Run SQL File Directly

1. Open phpMyAdmin or your MySQL client
2. Select your database
3. Go to the SQL tab
4. Copy and paste the contents of `create_building_areas_table.sql`
5. Click "Go" to execute

### Option 2: Use PHP Initialization Script

1. Navigate to: `userdashboard/mapping/php/init_building_areas_table.php`
2. Open in your browser (e.g., `http://localhost/DEFENDED/userdashboard/mapping/php/init_building_areas_table.php`)
3. The table will be created automatically

### Option 3: Manual SQL Execution

Run this SQL command in your database:

```sql
CREATE TABLE IF NOT EXISTS building_areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    building_id INT NOT NULL,
    center_latitude DECIMAL(10, 8) NOT NULL,
    center_longitude DECIMAL(11, 8) NOT NULL,
    radius DECIMAL(10, 2) NOT NULL DEFAULT 100.00 COMMENT 'Radius in meters',
    boundary_coordinates JSON COMMENT 'Optional: Store circle boundary points as JSON array',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_building_id (building_id),
    INDEX idx_center_coordinates (center_latitude, center_longitude),
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_building_area (building_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## Populating Building Areas from Existing Buildings

### Bulk Insert Script

To insert latitude and longitude data for all existing buildings that have coordinates, use the `insert_building_areas.php` script:

**Via Browser:**
1. Navigate to: `http://localhost/DEFENDED/userdashboard/mapping/php/insert_building_areas.php`
2. The script will automatically:
   - Insert new `building_areas` records for buildings that have coordinates but no area record
   - Update existing `building_areas` records if the building coordinates have changed
   - Skip buildings that already have matching coordinates

**Via Command Line:**
```bash
cd userdashboard/mapping/php
php insert_building_areas.php
```

The script will return a JSON response showing:
- Number of records inserted
- Number of records updated
- Number of records skipped
- Any errors encountered

## Automatic Saving

The area coordinates are automatically saved when buildings are displayed on the map. The JavaScript function `saveBuildingArea()` is called for each building when the map loads.

## API Endpoints

### Save Building Area
- **URL**: `save_building_area.php`
- **Method**: POST
- **Content-Type**: application/json
- **Body**:
```json
{
    "building_id": 1,
    "center_latitude": 10.54745970,
    "center_longitude": 122.83393850,
    "radius": 100.00,
    "boundary_coordinates": [[lat1, lng1], [lat2, lng2], ...]
}
```

### Get Building Area
- **URL**: `get_building_area.php?building_id=1`
- **Method**: GET
- **Response**:
```json
{
    "success": true,
    "area": {
        "id": 1,
        "building_id": 1,
        "center_latitude": "10.54745970",
        "center_longitude": "122.83393850",
        "radius": "100.00",
        "boundary_coordinates": [[lat1, lng1], ...],
        "created_at": "2025-01-01 12:00:00",
        "updated_at": "2025-01-01 12:00:00"
    }
}
```

## Notes

- Each building can have only one area record (enforced by UNIQUE constraint)
- If an area already exists for a building, updating the building will update the existing area record
- The boundary_coordinates field is optional and stores an array of [latitude, longitude] pairs representing the circle boundary
- The table uses CASCADE delete, so if a building is deleted, its area record is also deleted

