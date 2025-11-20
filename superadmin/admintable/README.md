# Admin Table Management System

This module provides a comprehensive admin management interface for the Fire Detection System.

## Features

### 📊 Data Table
- **Server-side processing** with DataTables for optimal performance
- **Real-time filtering** by status, name, and email
- **Search functionality** across all fields
- **Sortable columns** with custom ordering
- **Responsive design** for mobile and desktop
- **Pagination** with customizable page sizes

### 👤 Admin Management
- **Add new admins** with complete profile information
- **Edit existing admin** records
- **Delete admin** accounts with confirmation
- **Profile image upload** support
- **Status management** (Active/Inactive)

### 🔍 Advanced Filtering
- **Status filter** - Filter by Active/Inactive status
- **Name search** - Search by full name
- **Email search** - Search by email address
- **Real-time counts** - Live statistics display
- **Clear filters** - Reset all filters at once

### 🎨 User Interface
- **Modern Bootstrap 5** design
- **Font Awesome icons** for better UX
- **SweetAlert2** for beautiful notifications
- **Custom CSS** styling matching system theme
- **Modal forms** for add/edit operations
- **Confirmation dialogs** for delete operations

## File Structure

```
admintable/
├── index.php              # Redirect to main table
├── css/
│   └── admin.css          # Custom styling
└── php/
    ├── admin_table.php    # Main table interface
    └── admin_api.php      # API endpoints for CRUD operations
```

## Database Schema

The admin table uses the following structure:

```sql
CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remember_token` varchar(100) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `contact_number` (`contact_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## API Endpoints

### GET_ADMINS
- **Action**: `get_admins`
- **Purpose**: Retrieve paginated admin records
- **Parameters**: 
  - `draw`, `start`, `length` (DataTables standard)
  - `status_filter`, `name_search`, `email_search` (custom filters)

### GET_ADMIN
- **Action**: `get_admin`
- **Purpose**: Retrieve single admin record
- **Parameters**: `admin_id`

### ADD_ADMIN
- **Action**: `add_admin`
- **Purpose**: Create new admin record
- **Parameters**: `username`, `password`, `full_name`, `email`, `contact_number`, `status`, `profile_image`

### UPDATE_ADMIN
- **Action**: `update_admin`
- **Purpose**: Update existing admin record
- **Parameters**: `admin_id`, `username`, `password` (optional), `full_name`, `email`, `contact_number`, `status`, `profile_image`

### DELETE_ADMIN
- **Action**: `delete_admin`
- **Purpose**: Delete admin record
- **Parameters**: `admin_id`

### GET_COUNTS
- **Action**: `get_counts`
- **Purpose**: Get filtered record counts
- **Parameters**: `status_filter`, `name_search`, `email_search`

## Security Features

- **Password hashing** using PHP's `password_hash()`
- **Input validation** for all fields
- **SQL injection protection** with prepared statements
- **File upload validation** for profile images
- **Duplicate prevention** for unique fields
- **Session management** (ready for authentication)

## Usage

1. **Access the table**: Navigate to `/superadmin/admintable/`
2. **View admins**: The table loads automatically with all admin records
3. **Filter data**: Use the filter panel to narrow down results
4. **Add admin**: Click "Add Admin" button to open the form
5. **Edit admin**: Click the edit button (pencil icon) on any row
6. **Delete admin**: Click the delete button (trash icon) and confirm

## Dependencies

- **Bootstrap 5.3.3** - UI framework
- **DataTables 1.13.6** - Table functionality
- **jQuery 3.7.1** - JavaScript library
- **Font Awesome 6.4.0** - Icons
- **SweetAlert2 11** - Notifications
- **PDO MySQL** - Database operations

## Customization

### Styling
- Modify `css/admin.css` for custom styling
- Colors and themes can be adjusted in the CSS file
- Responsive breakpoints can be customized

### Functionality
- Add new fields by updating the database schema and forms
- Modify validation rules in `admin_api.php`
- Add new filter options by extending the API

## Error Handling

- **Database errors** are logged and return user-friendly messages
- **Validation errors** are displayed with specific field information
- **File upload errors** are handled gracefully
- **AJAX errors** show appropriate notifications

## Performance

- **Server-side processing** handles large datasets efficiently
- **Indexed database fields** for fast queries
- **Optimized queries** with proper WHERE clauses
- **Lazy loading** of images and assets
