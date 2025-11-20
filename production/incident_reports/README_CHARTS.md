# Incident Reports Charts Feature

## Overview
This feature adds real-time interactive charts to the incident reports page, providing visual analytics for fire incident data.

## Features Added

### 1. Monthly Incident Trends (Bar Chart)
- **Location**: Top left, large chart
- **Data**: Shows incident counts by month for the last 12 months
- **Categories**: Total incidents, flame incidents, smoke incidents
- **Real-time updates**: Every 30 seconds

### 2. Incidents by Building Type (Pie Chart)
- **Location**: Top right, medium chart
- **Data**: Distribution of incidents across different building types
- **Real-time updates**: Every 30 seconds

### 3. Incidents by Severity Level (Doughnut Chart)
- **Location**: Bottom left, medium chart
- **Data**: Classification of incidents by severity (Critical, High, Medium, Low)
- **Real-time updates**: Every 30 seconds

### 4. Real-time Statistics Panel
- **Location**: Bottom right, small panel
- **Data**: Live statistics including:
  - Incidents in last 24 hours
  - Incidents in last 7 days
  - Average temperature
  - Average smoke level
- **Real-time updates**: Every 10 seconds

## Technical Implementation

### Files Added/Modified

#### New Files:
- `php/chart_data.php` - API endpoint for chart data
- `js/charts.js` - Chart initialization and real-time updates
- `README_CHARTS.md` - This documentation

#### Modified Files:
- `functions/functions.php` - Added chart data functions
- `php/reports.php` - Added chart HTML and script includes
- `css/style.css` - Added chart styling

### Database Functions Added:
- `getIncidentsByMonth()` - Monthly incident trends
- `getIncidentsByBuildingType()` - Building type distribution
- `getIncidentsBySeverity()` - Severity level classification
- `getRealTimeIncidentStats()` - Real-time statistics

### Chart Libraries Used:
- **Chart.js** - Primary charting library
- **Bootstrap** - Layout and styling
- **Font Awesome** - Icons

## Real-time Features

### Update Intervals:
- **Charts**: 30 seconds
- **Statistics**: 10 seconds
- **Real-time indicator**: Pulsing green dot

### Error Handling:
- Network error detection
- Graceful fallback for failed data loads
- Console logging for debugging

## Responsive Design

### Mobile Optimization:
- Charts resize automatically
- Touch-friendly interactions
- Optimized layout for small screens

### Chart Sizes:
- Large: 500px height (monthly trends)
- Medium: 400px height (building type, severity)
- Small: 300px height (statistics panel)

## Usage

### For Users:
1. Navigate to the incident reports page
2. Charts will load automatically
3. Hover over chart elements for detailed information
4. Real-time updates happen automatically

### For Developers:
1. Charts are initialized when the page loads
2. Data is fetched via AJAX calls to `chart_data.php`
3. Updates are handled by JavaScript intervals
4. Error handling is built-in

## Customization

### Adding New Charts:
1. Add new function in `functions.php`
2. Add new case in `chart_data.php`
3. Add chart initialization in `charts.js`
4. Add HTML structure in `reports.php`

### Modifying Update Intervals:
- Edit the `setInterval` calls in `charts.js`
- Default: 30s for charts, 10s for stats

### Styling:
- Chart colors defined in `chartColors` object
- CSS classes in `style.css`
- Responsive breakpoints included

## Troubleshooting

### Common Issues:
1. **Charts not loading**: Check browser console for errors
2. **No data displayed**: Verify database connection and data
3. **Real-time not working**: Check JavaScript console for fetch errors

### Debug Mode:
- Enable browser developer tools
- Check Network tab for API calls
- Monitor Console for error messages

## Performance Considerations

### Optimization:
- Static database connection reuse
- Efficient SQL queries with proper indexing
- Minimal DOM updates
- Debounced chart updates

### Browser Compatibility:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- ES6+ JavaScript features
- Canvas API support required 