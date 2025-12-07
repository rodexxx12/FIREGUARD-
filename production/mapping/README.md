# Fire Detection System - Mapping Module

## Overview

The Mapping Module is a comprehensive real-time fire detection and emergency response system designed for Bago City Fire Station. It provides an interactive web-based dashboard for monitoring fire incidents, managing emergency responses, and visualizing fire data across multiple buildings in the city.

## System Architecture

### Frontend Components
- **Interactive Map**: Leaflet-based mapping system with real-time fire incident visualization
- **Status Dashboard**: Real-time monitoring of fire incidents by status (Safe, Monitoring, Emergency, etc.)
- **Emergency Controls**: Route calculation, device location, and emergency response tools
- **Building Management**: Building information display with safety features and device status

### Backend Components
- **Database Integration**: MySQL database with PDO for secure data access
- **API Endpoints**: RESTful APIs for data retrieval and processing
- **Real-time Updates**: Polling-based system for live data updates
- **Fallback System**: Sample data fallback when database is unavailable

## Key Features

### 🔥 Fire Detection & Monitoring
- **Real-time Fire Data**: Live monitoring of temperature, smoke, heat, and flame detection
- **Status Classification**: Automatic categorization (Safe, Monitoring, Pre-Dispatch, Emergency)
- **Multi-sensor Integration**: Support for various fire detection devices
- **Historical Data**: 24-hour data retention with trend analysis

### 🗺️ Interactive Mapping
- **Leaflet Map Integration**: High-performance mapping with multiple base layers
- **Real-time Markers**: Dynamic fire incident markers with status-based coloring
- **Building Visualization**: Building markers with type-specific icons
- **Heat Map Overlay**: Visual representation of fire intensity across areas
- **Marker Clustering**: Efficient handling of multiple incidents

### 🚨 Emergency Response
- **Route Calculation**: Automatic route planning to emergency locations
- **Voice Navigation**: Text-to-speech turn-by-turn directions
- **Emergency Location**: Quick location of critical incidents
- **Fire Station Integration**: Direct routing to Bago City Fire Station

### 📊 Dashboard & Analytics
- **Status Cards**: Real-time counts of incidents by status
- **Alert System**: Recent alerts with detailed information
- **Building Statistics**: Device counts, fire status history, and safety features
- **User Management**: Multi-user support with session handling

## Technical Specifications

### Database Schema
- **fire_data**: Core fire incident data with sensor readings
- **buildings**: Building information and safety features
- **devices**: Fire detection device management
- **users**: User authentication and session management

### API Endpoints
- `server_enhanced.php`: Main data API with fire incident data
- `get_building_stats.php`: Building-specific statistics and details
- `get_most_recent_critical.php`: Critical incident retrieval
- `get_emergency_buildings.php`: Emergency building data

### Technologies Used
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **Mapping**: Leaflet.js, Leaflet Routing Machine, Marker Clustering
- **Backend**: PHP 7.4+, PDO, MySQL
- **Libraries**: SweetAlert2, Chart.js, Font Awesome
- **Real-time**: Polling-based updates (2-minute intervals)

## File Structure

```
mapping/
├── php/                          # Main PHP application files
│   ├── components/              # Reusable UI components
│   │   ├── header.php          # HTML head and meta tags
│   │   ├── status_cards.php    # Dashboard status cards
│   │   ├── map_controls.php    # Map control buttons
│   │   ├── building_modal.php  # Building details modal
│   │   └── alerts_panel.php    # Alert notifications
│   ├── map.php                 # Main mapping interface
│   ├── server_enhanced.php     # Primary data API
│   └── get_*.php              # Various data retrieval APIs
├── functions/                   # Core PHP functions
│   ├── db_connect.php         # Database connection
│   ├── functions.php           # Utility functions
│   └── get_*.php              # Data retrieval functions
├── js/
│   └── script.js              # Main JavaScript application
├── css/
│   └── style.css              # Custom styling
└── README.md                   # This documentation
```

## Core Functions

### Map Management
- `initMap()`: Initialize Leaflet map with base layers
- `createFireMarker()`: Create fire incident markers
- `createBuildingMarker()`: Create building markers
- `filterFiresByStatus()`: Filter incidents by status

### Emergency Response
- `showRouteToStation()`: Calculate route to emergency location
- `locateEmergency()`: Find most recent critical incident
- `speakText()`: Text-to-speech functionality
- `clearRoute()`: Clear active routes

### Data Management
- `fetchFireData()`: Retrieve fire incident data
- `updateDashboard()`: Update UI with new data
- `getStatusCounts()`: Calculate status statistics
- `getRecentAlerts()`: Get recent alert data

## Configuration

### Fire Station Settings
```javascript
fireStation: {
    lat: 10.525467693871333,
    lng: 122.84123838118607,
    name: "Bago City Fire Station",
    contact: "09605105611"
}
```

### Status Thresholds
- **Safe**: Smoke < 20, Temp < 30°C, Heat < 30
- **Monitoring**: Smoke < 50, Temp < 50°C, Heat < 50
- **Pre-Dispatch**: Smoke < 100, Temp < 100°C, Heat < 100
- **Emergency**: Smoke ≥ 200, Temp ≥ 200°C, Heat ≥ 200

### Update Intervals
- **Data Polling**: 2 minutes (120,000ms)
- **Map Updates**: Real-time on data change
- **Status Refresh**: Automatic with new data

## Security Features

- **Session Management**: Secure user authentication
- **SQL Injection Prevention**: PDO prepared statements
- **Input Validation**: Server-side data validation
- **Error Handling**: Graceful fallback to sample data
- **CORS Headers**: Proper cross-origin resource sharing

## Browser Compatibility

- **Modern Browsers**: Chrome 80+, Firefox 75+, Safari 13+, Edge 80+
- **Mobile Support**: Responsive design for tablets and smartphones
- **Speech Synthesis**: Cross-browser text-to-speech support
- **Geolocation**: User location services (optional)

## Installation & Setup

1. **Database Configuration**: Update `db_connect.php` with your MySQL credentials
2. **Web Server**: Deploy to Apache/Nginx with PHP 7.4+ support
3. **Dependencies**: Ensure all CDN resources are accessible
4. **Permissions**: Set appropriate file permissions for uploads directory

## Usage

1. **Access the System**: Navigate to `map.php` in your web browser
2. **Monitor Incidents**: View real-time fire data on the interactive map
3. **Emergency Response**: Use "Emergency Route" for critical incidents
4. **Building Details**: Click building markers for detailed information
5. **Status Filtering**: Use legend panel to filter by incident status

## Troubleshooting

- **No Data Display**: Check database connection and table existence
- **Map Not Loading**: Verify Leaflet CDN accessibility
- **Speech Not Working**: Check browser speech synthesis support
- **Route Calculation**: Ensure internet connectivity for routing services

## Support

For technical support or feature requests, contact the development team or refer to the main Fire Detection System documentation.

---

**Version**: 1.0  
**Last Updated**: 2024  
**Compatibility**: PHP 7.4+, MySQL 5.7+, Modern Browsers
