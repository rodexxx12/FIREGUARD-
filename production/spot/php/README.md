# Spot Investigation Reports System

A comprehensive fire incident investigation documentation system for the Fire Detection System. This module provides formal, modern, and clean interfaces for creating, viewing, editing, and managing spot investigation reports.

## Features

### 📋 Report Management
- **Create Reports**: Comprehensive form for documenting fire incidents
- **View Reports**: Detailed report viewing with print functionality
- **Edit Reports**: Full editing capabilities for existing reports including status updates
- **Delete Reports**: Secure deletion with confirmation dialogs
- **Search & Filter**: Advanced search and filtering capabilities
- **Status Management**: Update report status (draft, completed, submitted) without creating new reports
- **IR Number Display**: View Investigation Report numbers for each report

### 🎨 Modern UI/UX
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile
- **Clean Interface**: Modern, professional appearance with intuitive navigation
- **Interactive Elements**: Smooth animations and hover effects
- **Print Support**: Optimized print layouts for report generation

### 📊 Comprehensive Data Collection
- **Basic Information**: Report metadata and occurrence details
- **Location Details**: Complete establishment and ownership information
- **Casualties & Damage**: Detailed casualty and damage assessment
- **Fire Details**: Fire timeline and alarm level information
- **Additional Information**: Weather, disposition, and other relevant data
- **Investigator Information**: Investigator details and signatures

## File Structure

```
production/spot/
├── index.php                    # Main dashboard listing all reports with IR numbers and status controls
├── create.php                   # Create new spot investigation report
├── create_report.php            # Create report from fire data
├── view.php                     # View detailed report information
├── edit.php                     # Edit existing report with status management
├── api/
│   ├── delete_report.php        # API endpoint for deleting reports
│   ├── get_reports.php          # API endpoint for fetching all reports
│   ├── get_report.php            # API endpoint for fetching single report
│   └── update_report_status.php # API endpoint for updating report status
└── README.md                    # This documentation file
```

## Status Management

The system now includes comprehensive status management for spot investigation reports:

### Available Statuses
- **Draft**: Report is being prepared and edited
- **Completed**: Report is finalized and ready for review
- **Submitted**: Report has been submitted for processing

### Status Update Methods
1. **Quick Status Update**: Use the dropdown menu in the main listing page to quickly change status
2. **Edit Form**: Update status through the comprehensive edit form
3. **API Endpoint**: Programmatic status updates via REST API

### IR Number Display
- Each report displays its unique Investigation Report (IR) number
- IR numbers are automatically generated when reports are created
- IR numbers are prominently displayed in the main listing and edit forms

### Report Management Behavior
- **No Duplicate Reports**: The system prevents creating multiple reports for the same fire incident
- **Update Existing Reports**: When a report already exists for a fire_data_id, the system updates the existing report instead of creating a new one
- **Dynamic Button Behavior**: 
  - Shows "Create Report" for incidents without reports
  - Shows "Edit Report" for incidents with draft/submitted reports
  - Shows "Completed" (disabled) for incidents with completed reports
- **Status-Based Access Control**: Completed reports cannot be edited, ensuring data integrity

## Database Schema

The system uses the `spot_investigation_reports` table with the following structure:

| Field | Type | Description |
|-------|------|-------------|
| id | int(11) | Primary key, auto-increment |
| report_for | varchar(255) | Who the report is for |
| subject | varchar(100) | Report subject (default: "Spot Investigation Report (SIR)") |
| date_completed | date | Date when report was completed |
| date_occurrence | date | Date when incident occurred |
| time_occurrence | time | Time when incident occurred |
| place_occurrence | varchar(255) | Location where incident occurred |
| involved | varchar(255) | Persons involved in the incident |
| establishment_name | varchar(255) | Name of affected establishment |
| owner | varchar(255) | Establishment owner |
| occupant | varchar(255) | Establishment occupant (optional) |
| fatalities | int(11) | Number of fatalities |
| injured | int(11) | Number of injured persons |
| estimated_damage | decimal(15,2) | Estimated damage amount |
| time_fire_started | datetime | When the fire started |
| time_fire_out | datetime | When the fire was extinguished |
| highest_alarm_level | varchar(50) | Highest alarm level reached |
| establishments_affected | int(11) | Number of establishments affected |
| estimated_area_sqm | decimal(10,2) | Estimated affected area in square meters |
| damage_computation | decimal(15,2) | Computed damage amount |
| location_of_fatalities | text | Where fatalities occurred |
| weather_condition | varchar(100) | Weather conditions during incident |
| other_info | text | Additional information |
| disposition | text | Report disposition |
| turned_over | tinyint(1) | Whether report has been turned over |
| investigator_name | varchar(255) | Name of investigating officer |
| investigator_signature | varchar(255) | Investigator signature |
| created_at | timestamp | Record creation timestamp |

## Usage

### Creating a New Report
1. Navigate to the Spot Investigation Reports dashboard
2. Click "Create New Report" button
3. Fill in all required fields (marked with red asterisks)
4. Complete optional fields as needed
5. Click "Create Report" to save

### Viewing Reports
1. From the dashboard, click the eye icon (👁️) next to any report
2. View comprehensive report details
3. Use the print button for generating physical copies
4. Click "Edit Report" to make changes

### Editing Reports
1. From the view page, click "Edit Report"
2. Modify any field as needed
3. Click "Update Report" to save changes
4. Or click "Cancel" to return to view mode

### Deleting Reports
1. From the dashboard, click the trash icon (🗑️) next to any report
2. Confirm deletion in the popup dialog
3. Report will be permanently removed

## Technical Features

### Security
- Session-based authentication
- SQL injection prevention with prepared statements
- Input validation and sanitization
- CSRF protection through session validation

### Performance
- Optimized database queries
- Efficient data pagination
- Responsive image loading
- Minimal external dependencies

### Accessibility
- Semantic HTML structure
- Keyboard navigation support
- Screen reader compatibility
- High contrast color schemes

## Dependencies

### Frontend Libraries
- **Bootstrap 5.3.3**: UI framework and components
- **Font Awesome 6.4.0**: Icons and visual elements
- **SweetAlert2**: Modern alert dialogs
- **DataTables**: Advanced table functionality
- **Chart.js**: Data visualization (if needed)

### Backend Requirements
- **PHP 7.4+**: Server-side scripting
- **MySQL 5.7+**: Database management
- **PDO**: Database abstraction layer

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Installation

1. Ensure the database table `spot_investigation_reports` exists
2. Place files in the `production/spot/` directory
3. Verify database connection in `../db/db.php`
4. Access through the main application navigation

## Customization

### Styling
- Modify CSS variables in each PHP file's `<style>` section
- Update color schemes by changing gradient values
- Adjust spacing and typography as needed

### Functionality
- Add new fields by updating the database schema
- Modify validation rules in JavaScript sections
- Extend API endpoints for additional operations

## Support

For technical support or feature requests, please contact the development team or refer to the main Fire Detection System documentation.

---

**Version**: 1.0.0  
**Last Updated**: December 2024  
**Compatibility**: Fire Detection System v2.0+
