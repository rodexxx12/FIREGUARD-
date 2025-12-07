<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../db/db.php';
require_once 'classes/ErrorHandler.php';
require_once 'classes/SecurityHeaders.php';
require_once 'classes/CsrfProtection.php';

// Initialize error handler
$isProduction = (getenv('APP_ENV') === 'production');
ErrorHandler::init($isProduction);

// Set security headers
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
SecurityHeaders::setAll($isHttps);

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../../index.php');
    exit();
}

// CSRF Protection
CsrfProtection::getToken(); // Ensure token exists

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !CsrfProtection::validateToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

$conn = getDatabaseConnection();
$success_message = '';
$error_message = '';
$fireData = null;

// Enhanced input validation and sanitization functions
function validateAndSanitizeInput($input, $type = 'string', $maxLength = null) {
    if ($input === null || $input === '') {
        return $input;
    }
    
    switch ($type) {
        case 'string':
            $sanitized = trim($input);
            $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
            if ($maxLength && strlen($sanitized) > $maxLength) {
                $sanitized = substr($sanitized, 0, $maxLength);
            }
            return $sanitized;
            
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT, array(
                "options" => array("min_range" => 0)
            ));
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT, array(
                "options" => array("min_range" => 0)
            ));
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
            
        case 'date':
            $date = DateTime::createFromFormat('Y-m-d', $input);
            return $date && $date->format('Y-m-d') === $input ? $input : false;
            
        case 'datetime':
            $datetime = DateTime::createFromFormat('Y-m-d\TH:i', $input);
            return $datetime && $datetime->format('Y-m-d\TH:i') === $input ? $input : false;
            
        case 'time':
            $time = DateTime::createFromFormat('H:i', $input);
            return $time && $time->format('H:i') === $input ? $input : false;
            
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// Validate allowed values for select fields
function validateSelectValue($value, $allowedValues) {
    return in_array($value, $allowedValues) ? $value : false;
}

// Note: LIKE wildcard escaping is now handled in SecureQueryBuilder when needed

// Function to generate unique IR number with enhanced security
function generateIRNumber($conn) {
    try {
        // Get current year
        $currentYear = date('Y');
        
        // Validate year format
        if (!preg_match('/^\d{4}$/', $currentYear)) {
            throw new Exception('Invalid year format');
        }
        
        // Get the last IR number for this year using prepared statement
        $stmt = $conn->prepare("SELECT ir_number FROM spot_investigation_reports WHERE ir_number LIKE ? ORDER BY ir_number DESC LIMIT 1");
        $yearPrefix = "SIR-{$currentYear}-";
        $searchPattern = $yearPrefix . '%';
        
        // Additional validation for the search pattern
        if (!preg_match('/^SIR-\d{4}-%$/', $searchPattern)) {
            throw new Exception('Invalid search pattern');
        }
        
        $stmt->bindParam(1, $searchPattern, PDO::PARAM_STR);
        $stmt->execute();
        $lastIR = $stmt->fetchColumn();
        
        if ($lastIR) {
            // Validate the retrieved IR number format
            if (!preg_match('/^SIR-\d{4}-\d{4}$/', $lastIR)) {
                throw new Exception('Invalid IR number format in database');
            }
            
            // Extract the number part and increment
            $lastNumber = (int)substr($lastIR, strlen($yearPrefix));
            $newNumber = $lastNumber + 1;
            
            // Prevent integer overflow
            if ($newNumber > 9999) {
                throw new Exception('Maximum IR number reached for this year');
            }
        } else {
            // First report of the year
            $newNumber = 1;
        }
        
        // Format with leading zeros (4 digits)
        $irNumber = $yearPrefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        
        // Final validation of generated IR number
        if (!preg_match('/^SIR-\d{4}-\d{4}$/', $irNumber)) {
            throw new Exception('Generated IR number format validation failed');
        }
        
        return $irNumber;
        
    } catch (Exception $e) {
        error_log("Error generating IR number: " . $e->getMessage());
        throw new Exception('Failed to generate IR number');
    }
}

// Get fire_data_id from URL parameter with enhanced validation
$fireDataId = 0;
if (isset($_GET['fire_data_id'])) {
    $rawFireDataId = $_GET['fire_data_id'];
    
    // Validate that it's a positive integer
    if (is_numeric($rawFireDataId) && $rawFireDataId > 0 && $rawFireDataId == (int)$rawFireDataId) {
        $fireDataId = (int)$rawFireDataId;
        
        // Additional bounds checking
        if ($fireDataId > 999999999) { // Reasonable upper limit
            $fireDataId = 0;
        }
    }
}

if ($fireDataId > 0) {
    try {
        // Fetch the fire data record with enhanced security
        $stmt = $conn->prepare("
            SELECT fd.*, 
                   b.building_name,
                   b.address as building_address,
                   b.building_type,
                   b.contact_person,
                   b.contact_number,
                   u.username as user_name,
                   u.fullname as property_owner_name,
                   u.email_address as owner_email,
                   u.contact_number as owner_contact,
                   br.barangay_name
            FROM fire_data fd
            LEFT JOIN buildings b ON fd.building_id = b.id
            LEFT JOIN users u ON b.user_id = u.user_id
            LEFT JOIN barangay br ON fd.barangay_id = br.id
            WHERE fd.id = ? AND fd.status = 'ACKNOWLEDGED'
        ");
        
        if (!$stmt) {
            throw new Exception('Database query preparation failed');
        }
        
        $stmt->bindParam(1, $fireDataId, PDO::PARAM_INT);
        $stmt->execute();
        $fireData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fireData) {
            $error_message = "Fire incident not found or not acknowledged.";
        }
        
    } catch (Exception $e) {
        error_log("Database error in fire data fetch: " . $e->getMessage());
        $error_message = "Database error occurred while fetching fire incident data.";
        $fireData = null;
    }
}

// Handle POST request for creating/updating report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fireData) {
    try {
        // Initialize validation errors array
        $validation_errors = [];
        
        // Required field validations
        if (empty(trim($_POST['report_for'] ?? ''))) {
            $validation_errors[] = "Report For is required.";
        }
        if (empty(trim($_POST['subject'] ?? ''))) {
            $validation_errors[] = "Subject is required.";
        } elseif (strlen(trim($_POST['subject'])) < 10) {
            $validation_errors[] = "Subject must be at least 10 characters long.";
        }
        if (empty($_POST['date_completed'] ?? '')) {
            $validation_errors[] = "Date Completed is required.";
        }
        if (empty($_POST['date_occurrence'] ?? '')) {
            $validation_errors[] = "Date of Occurrence is required.";
        }
        if (empty($_POST['time_occurrence'] ?? '')) {
            $validation_errors[] = "Time of Occurrence is required.";
        }
        if (empty(trim($_POST['place_occurrence'] ?? ''))) {
            $validation_errors[] = "Location is required.";
        } elseif (strlen(trim($_POST['place_occurrence'])) < 5) {
            $validation_errors[] = "Location must be at least 5 characters long.";
        }
        if (empty(trim($_POST['establishment_name'] ?? ''))) {
            $validation_errors[] = "Establishment Name is required.";
        }
        
        // Date/Time logic validations
        $dateCompleted = $_POST['date_completed'] ?? '';
        $dateOccurrence = $_POST['date_occurrence'] ?? '';
        $timeOccurrence = $_POST['time_occurrence'] ?? '';
        $timeFireStarted = $_POST['time_fire_started'] ?? '';
        $timeFireOut = $_POST['time_fire_out'] ?? '';
        
        // Validate Date Completed vs Date of Occurrence
        if ($dateCompleted && $dateOccurrence) {
            // Parse dates and set to midnight for accurate comparison
            $completedTimestamp = strtotime($dateCompleted . ' 00:00:00');
            $occurrenceTimestamp = strtotime($dateOccurrence . ' 00:00:00');
            
            if ($completedTimestamp === false || $occurrenceTimestamp === false) {
                $validation_errors[] = "Invalid date format detected.";
            } elseif ($completedTimestamp < $occurrenceTimestamp) {
                $validation_errors[] = "Date Completed cannot be before Date of Occurrence.";
            }
        }
        
        // Validate Date of Occurrence constraints
        if ($dateOccurrence) {
            $occurrenceTimestamp = strtotime($dateOccurrence . ' 00:00:00');
            if ($occurrenceTimestamp === false) {
                $validation_errors[] = "Invalid Date of Occurrence format.";
            } else {
                $thirtyDaysAgo = strtotime('-30 days 00:00:00');
                
                if ($occurrenceTimestamp < $thirtyDaysAgo) {
                    $validation_errors[] = "Date of Occurrence cannot be more than 30 days in the past.";
                }
            }
        }
        
        if ($timeFireStarted && $timeFireOut) {
            if (strtotime($timeFireStarted) >= strtotime($timeFireOut)) {
                $validation_errors[] = "Fire Extinguished Time must be after Fire Start Time.";
            }
        }
        
        // Data type and range validations
        $fatalities = (int)($_POST['fatalities'] ?? 0);
        $injured = (int)($_POST['injured'] ?? 0);
        $estimatedDamage = (float)($_POST['estimated_damage'] ?? 0);
        $establishmentsAffected = (int)($_POST['establishments_affected'] ?? 1);
        $estimatedAreaSqm = (float)($_POST['estimated_area_sqm'] ?? 0);
        $damageComputation = (float)($_POST['damage_computation'] ?? 0);
        
        if ($fatalities < 0) {
            $validation_errors[] = "Fatalities cannot be negative.";
        }
        if ($injured < 0) {
            $validation_errors[] = "Injured count cannot be negative.";
        }
        if ($estimatedDamage < 0) {
            $validation_errors[] = "Estimated Damage cannot be negative.";
        }
        if ($establishmentsAffected < 1) {
            $validation_errors[] = "Properties Affected must be at least 1.";
        }
        if ($estimatedAreaSqm < 0) {
            $validation_errors[] = "Area Affected cannot be negative.";
        }
        if ($damageComputation < 0) {
            $validation_errors[] = "Total Damage cannot be negative.";
        }
        
        // Business logic validations
        $validAlarmLevels = ['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5'];
        $highestAlarmLevel = $_POST['highest_alarm_level'] ?? '';
        if (!in_array($highestAlarmLevel, $validAlarmLevels)) {
            $validation_errors[] = "Invalid Alarm Level selected.";
        }
        
        $validWeatherConditions = ['Normal', 'Rainy', 'Sunny', 'Windy', 'Stormy'];
        $weatherCondition = $_POST['weather_condition'] ?? '';
        if (!in_array($weatherCondition, $validWeatherConditions)) {
            $validation_errors[] = "Invalid Weather Condition selected.";
        }
        
        $validStatuses = ['draft', 'pending_review', 'final'];
        $reportsStatus = $_POST['reports_status'] ?? '';
        if (!in_array($reportsStatus, $validStatuses)) {
            $validation_errors[] = "Invalid Report Status selected.";
        }
        
        // Personnel validation
        $personnelSelected = false;
        if (isset($_POST['personnel_investigator']) || isset($_POST['personnel_owner']) || 
            isset($_POST['personnel_occupant']) || isset($_POST['personnel_other'])) {
            $personnelSelected = true;
        }
        if (!$personnelSelected) {
            $validation_errors[] = "At least one Personnel Involved option must be selected.";
        }
        
        // Enhanced input sanitization using validation functions
        $reportFor = validateAndSanitizeInput($_POST['report_for'] ?? '', 'string', 200);
        $subject = validateAndSanitizeInput($_POST['subject'] ?? '', 'string', 200);
        $placeOccurrence = validateAndSanitizeInput($_POST['place_occurrence'] ?? '', 'string', 500);
        $establishmentName = validateAndSanitizeInput($_POST['establishment_name'] ?? '', 'string', 200);
        $owner = validateAndSanitizeInput($_POST['owner'] ?? '', 'string', 100);
        $occupant = validateAndSanitizeInput($_POST['occupant'] ?? '', 'string', 100);
        $locationOfFatalities = validateAndSanitizeInput($_POST['location_of_fatalities'] ?? '', 'string', 1000);
        $otherInfo = validateAndSanitizeInput($_POST['other_info'] ?? '', 'string', 2000);
        $disposition = validateAndSanitizeInput($_POST['disposition'] ?? '', 'string', 2000);
        $investigatorName = validateAndSanitizeInput($_POST['investigator_name'] ?? '', 'string', 100);
        $investigatorSignature = validateAndSanitizeInput($_POST['investigator_signature'] ?? '', 'string', 100);
        $additionalInvolved = validateAndSanitizeInput($_POST['involved'] ?? '', 'string', 500);
        
        // Validate date and time fields
        $dateCompleted = validateAndSanitizeInput($_POST['date_completed'] ?? '', 'date');
        $dateOccurrence = validateAndSanitizeInput($_POST['date_occurrence'] ?? '', 'date');
        $timeOccurrence = validateAndSanitizeInput($_POST['time_occurrence'] ?? '', 'time');
        $timeFireStarted = validateAndSanitizeInput($_POST['time_fire_started'] ?? '', 'datetime');
        $timeFireOut = validateAndSanitizeInput($_POST['time_fire_out'] ?? '', 'datetime');
        
        // Validate numeric fields
        $fatalities = validateAndSanitizeInput($_POST['fatalities'] ?? 0, 'int');
        $injured = validateAndSanitizeInput($_POST['injured'] ?? 0, 'int');
        $estimatedDamage = validateAndSanitizeInput($_POST['estimated_damage'] ?? 0, 'float');
        $establishmentsAffected = validateAndSanitizeInput($_POST['establishments_affected'] ?? 1, 'int');
        $estimatedAreaSqm = validateAndSanitizeInput($_POST['estimated_area_sqm'] ?? 0, 'float');
        $damageComputation = validateAndSanitizeInput($_POST['damage_computation'] ?? 0, 'float');
        
        // Validate select field values
        $validAlarmLevels = ['Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5'];
        $highestAlarmLevel = validateSelectValue($_POST['highest_alarm_level'] ?? '', $validAlarmLevels);
        
        $validWeatherConditions = ['Normal', 'Rainy', 'Sunny', 'Windy', 'Stormy'];
        $weatherCondition = validateSelectValue($_POST['weather_condition'] ?? '', $validWeatherConditions);
        
        $validStatuses = ['draft', 'pending_review', 'final'];
        $reportsStatus = validateSelectValue($_POST['reports_status'] ?? '', $validStatuses);
        
        // Additional validation for failed sanitization
        if ($dateCompleted === false) {
            $validation_errors[] = "Invalid Date Completed format.";
        }
        if ($dateOccurrence === false) {
            $validation_errors[] = "Invalid Date of Occurrence format.";
        }
        if ($timeOccurrence === false) {
            $validation_errors[] = "Invalid Time of Occurrence format.";
        }
        if ($timeFireStarted === false && !empty($_POST['time_fire_started'])) {
            $validation_errors[] = "Invalid Fire Start Time format.";
        }
        if ($timeFireOut === false && !empty($_POST['time_fire_out'])) {
            $validation_errors[] = "Invalid Fire Extinguished Time format.";
        }
        if ($fatalities === false) {
            $validation_errors[] = "Invalid Fatalities value.";
        }
        if ($injured === false) {
            $validation_errors[] = "Invalid Injured count value.";
        }
        if ($estimatedDamage === false) {
            $validation_errors[] = "Invalid Estimated Damage value.";
        }
        if ($establishmentsAffected === false) {
            $validation_errors[] = "Invalid Properties Affected value.";
        }
        if ($estimatedAreaSqm === false) {
            $validation_errors[] = "Invalid Area Affected value.";
        }
        if ($damageComputation === false) {
            $validation_errors[] = "Invalid Total Damage value.";
        }
        if ($highestAlarmLevel === false) {
            $validation_errors[] = "Invalid Alarm Level selected.";
        }
        if ($weatherCondition === false) {
            $validation_errors[] = "Invalid Weather Condition selected.";
        }
        if ($reportsStatus === false) {
            $validation_errors[] = "Invalid Report Status selected.";
        }
        
        // Character limit validations are now handled by validateAndSanitizeInput function
        
        // If there are validation errors, display them and stop processing
        if (!empty($validation_errors)) {
            $error_message = "Please correct the following errors:<br>" . implode("<br>", array_map('htmlspecialchars', $validation_errors));
        } else {
            // Check if a report already exists for this fire_data_id with enhanced security
            try {
                $existingReportStmt = $conn->prepare("SELECT id, ir_number FROM spot_investigation_reports WHERE fire_data_id = ?");
                if (!$existingReportStmt) {
                    throw new Exception('Failed to prepare existing report query');
                }
                $existingReportStmt->bindParam(1, $fireDataId, PDO::PARAM_INT);
                $existingReportStmt->execute();
                $existingReport = $existingReportStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Database error checking existing report: " . $e->getMessage());
                $error_message = "Database error occurred while checking existing reports.";
                $existingReport = null;
            }
            
            // Use sanitized variables (already validated above)
            // Set default values if sanitized values are empty
            if (empty($dateCompleted)) {
                $dateCompleted = date('Y-m-d');
            }
            if (empty($dateOccurrence)) {
                $dateOccurrence = date('Y-m-d', strtotime($fireData['timestamp']));
            }
            if (empty($timeOccurrence)) {
                $timeOccurrence = date('H:i', strtotime($fireData['timestamp']));
            }
            if (empty($timeFireStarted)) {
                $timeFireStarted = date('Y-m-d\TH:i', strtotime($fireData['timestamp']));
            }
            $turnedOver = isset($_POST['turned_over']) ? 1 : 0;
            
            // Set investigator name if not provided
            if (empty($investigatorName)) {
                $investigatorName = $_SESSION['admin_name'] ?? 'Unknown Investigator';
            }
            
            // Process personnel checkboxes and build involved personnel list
            $personnelTypes = [];
            if (isset($_POST['personnel_investigator'])) {
                $personnelTypes[] = 'Investigator';
            }
            if (isset($_POST['personnel_owner'])) {
                $personnelTypes[] = 'Owner';
            }
            if (isset($_POST['personnel_occupant'])) {
                $personnelTypes[] = 'Occupant';
            }
            if (isset($_POST['personnel_other'])) {
                $personnelTypes[] = 'Other Involved Persons';
            }
            
            // Combine personnel types with additional details
            $baseInvolved = !empty($personnelTypes) ? implode(', ', $personnelTypes) : 'Fire Department Personnel';
            $finalInvolved = $additionalInvolved ? $baseInvolved . '; ' . $additionalInvolved : $baseInvolved;
            
            if ($existingReport) {
                // Update existing report with enhanced security
                try {
                    $stmt = $conn->prepare("
                        UPDATE spot_investigation_reports SET
                            reports_status = :reports_status,
                            report_for = :report_for,
                            subject = :subject,
                            date_completed = :date_completed,
                            date_occurrence = :date_occurrence,
                            time_occurrence = :time_occurrence,
                            place_occurrence = :place_occurrence,
                            involved = :involved,
                            establishment_name = :establishment_name,
                            owner = :owner,
                            occupant = :occupant,
                            fatalities = :fatalities,
                            injured = :injured,
                            estimated_damage = :estimated_damage,
                            time_fire_started = :time_fire_started,
                            time_fire_out = :time_fire_out,
                            highest_alarm_level = :highest_alarm_level,
                            establishments_affected = :establishments_affected,
                            estimated_area_sqm = :estimated_area_sqm,
                            damage_computation = :damage_computation,
                            location_of_fatalities = :location_of_fatalities,
                            weather_condition = :weather_condition,
                            other_info = :other_info,
                            disposition = :disposition,
                            turned_over = :turned_over,
                            investigator_name = :investigator_name,
                            investigator_signature = :investigator_signature
                        WHERE fire_data_id = :fire_data_id
                    ");
                    
                    if (!$stmt) {
                        throw new Exception('Failed to prepare update statement');
                    }
                    
                    $stmt->bindParam(':reports_status', $reportsStatus, PDO::PARAM_STR);
                    $stmt->bindParam(':report_for', $reportFor, PDO::PARAM_STR);
                    $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
                    $stmt->bindParam(':date_completed', $dateCompleted, PDO::PARAM_STR);
                    $stmt->bindParam(':date_occurrence', $dateOccurrence, PDO::PARAM_STR);
                    $stmt->bindParam(':time_occurrence', $timeOccurrence, PDO::PARAM_STR);
                    $stmt->bindParam(':place_occurrence', $placeOccurrence, PDO::PARAM_STR);
                    $stmt->bindParam(':involved', $finalInvolved, PDO::PARAM_STR);
                    $stmt->bindParam(':establishment_name', $establishmentName, PDO::PARAM_STR);
                    $stmt->bindParam(':owner', $owner, PDO::PARAM_STR);
                    $stmt->bindParam(':occupant', $occupant, PDO::PARAM_STR);
                    $stmt->bindParam(':fatalities', $fatalities, PDO::PARAM_INT);
                    $stmt->bindParam(':injured', $injured, PDO::PARAM_INT);
                    $stmt->bindParam(':estimated_damage', $estimatedDamage, PDO::PARAM_STR);
                    $stmt->bindParam(':time_fire_started', $timeFireStarted, PDO::PARAM_STR);
                    $stmt->bindParam(':time_fire_out', $timeFireOut, PDO::PARAM_STR);
                    $stmt->bindParam(':highest_alarm_level', $highestAlarmLevel, PDO::PARAM_STR);
                    $stmt->bindParam(':establishments_affected', $establishmentsAffected, PDO::PARAM_INT);
                    $stmt->bindParam(':estimated_area_sqm', $estimatedAreaSqm, PDO::PARAM_STR);
                    $stmt->bindParam(':damage_computation', $damageComputation, PDO::PARAM_STR);
                    $stmt->bindParam(':location_of_fatalities', $locationOfFatalities, PDO::PARAM_STR);
                    $stmt->bindParam(':weather_condition', $weatherCondition, PDO::PARAM_STR);
                    $stmt->bindParam(':other_info', $otherInfo, PDO::PARAM_STR);
                    $stmt->bindParam(':disposition', $disposition, PDO::PARAM_STR);
                    $stmt->bindParam(':turned_over', $turnedOver, PDO::PARAM_INT);
                    $stmt->bindParam(':investigator_name', $investigatorName, PDO::PARAM_STR);
                    $stmt->bindParam(':investigator_signature', $investigatorSignature, PDO::PARAM_STR);
                    $stmt->bindParam(':fire_data_id', $fireDataId, PDO::PARAM_INT);
                    
                    $stmt->execute();
                    
                    $reportId = $existingReport['id'];
                    
                } catch (Exception $e) {
                    error_log("Database error updating report: " . $e->getMessage());
                    throw new Exception('Failed to update report');
                }
            } else {
                // Create new report with enhanced security
                try {
                    $irNumber = generateIRNumber($conn);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO spot_investigation_reports (
                            ir_number, reports_status, report_for, subject, date_completed, date_occurrence, time_occurrence,
                            place_occurrence, involved, establishment_name, owner, occupant,
                            fatalities, injured, estimated_damage, time_fire_started, time_fire_out,
                            highest_alarm_level, establishments_affected, estimated_area_sqm,
                            damage_computation, location_of_fatalities, weather_condition,
                            other_info, disposition, turned_over, investigator_name, investigator_signature,
                            fire_data_id
                        ) VALUES (
                            :ir_number, :reports_status, :report_for, :subject, :date_completed, :date_occurrence, :time_occurrence,
                            :place_occurrence, :involved, :establishment_name, :owner, :occupant,
                            :fatalities, :injured, :estimated_damage, :time_fire_started, :time_fire_out,
                            :highest_alarm_level, :establishments_affected, :estimated_area_sqm,
                            :damage_computation, :location_of_fatalities, :weather_condition,
                            :other_info, :disposition, :turned_over, :investigator_name, :investigator_signature,
                            :fire_data_id
                        )
                    ");
                    
                    if (!$stmt) {
                        throw new Exception('Failed to prepare insert statement');
                    }
                    
                    $stmt->bindParam(':ir_number', $irNumber, PDO::PARAM_STR);
                    $stmt->bindParam(':reports_status', $reportsStatus, PDO::PARAM_STR);
                    $stmt->bindParam(':report_for', $reportFor, PDO::PARAM_STR);
                    $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
                    $stmt->bindParam(':date_completed', $dateCompleted, PDO::PARAM_STR);
                    $stmt->bindParam(':date_occurrence', $dateOccurrence, PDO::PARAM_STR);
                    $stmt->bindParam(':time_occurrence', $timeOccurrence, PDO::PARAM_STR);
                    $stmt->bindParam(':place_occurrence', $placeOccurrence, PDO::PARAM_STR);
                    $stmt->bindParam(':involved', $finalInvolved, PDO::PARAM_STR);
                    $stmt->bindParam(':establishment_name', $establishmentName, PDO::PARAM_STR);
                    $stmt->bindParam(':owner', $owner, PDO::PARAM_STR);
                    $stmt->bindParam(':occupant', $occupant, PDO::PARAM_STR);
                    $stmt->bindParam(':fatalities', $fatalities, PDO::PARAM_INT);
                    $stmt->bindParam(':injured', $injured, PDO::PARAM_INT);
                    $stmt->bindParam(':estimated_damage', $estimatedDamage, PDO::PARAM_STR);
                    $stmt->bindParam(':time_fire_started', $timeFireStarted, PDO::PARAM_STR);
                    $stmt->bindParam(':time_fire_out', $timeFireOut, PDO::PARAM_STR);
                    $stmt->bindParam(':highest_alarm_level', $highestAlarmLevel, PDO::PARAM_STR);
                    $stmt->bindParam(':establishments_affected', $establishmentsAffected, PDO::PARAM_INT);
                    $stmt->bindParam(':estimated_area_sqm', $estimatedAreaSqm, PDO::PARAM_STR);
                    $stmt->bindParam(':damage_computation', $damageComputation, PDO::PARAM_STR);
                    $stmt->bindParam(':location_of_fatalities', $locationOfFatalities, PDO::PARAM_STR);
                    $stmt->bindParam(':weather_condition', $weatherCondition, PDO::PARAM_STR);
                    $stmt->bindParam(':other_info', $otherInfo, PDO::PARAM_STR);
                    $stmt->bindParam(':disposition', $disposition, PDO::PARAM_STR);
                    $stmt->bindParam(':turned_over', $turnedOver, PDO::PARAM_INT);
                    $stmt->bindParam(':investigator_name', $investigatorName, PDO::PARAM_STR);
                    $stmt->bindParam(':investigator_signature', $investigatorSignature, PDO::PARAM_STR);
                    $stmt->bindParam(':fire_data_id', $fireDataId, PDO::PARAM_INT);
                    
                    $stmt->execute();
                    
                    $reportId = $conn->lastInsertId();
                    
                } catch (Exception $e) {
                    error_log("Database error creating report: " . $e->getMessage());
                    throw new Exception('Failed to create report');
                }
            }
        
            // Check status and redirect accordingly
            if ($reportsStatus === 'final') {
                // Set success message for final status and redirect to index
                header("Location: index.php?status=report_saved&report_status=$reportsStatus&report_id=$reportId&final=true");
                exit();
            } else {
                // For draft/pending status, redirect to index with success message
                header("Location: index.php?status=report_saved&report_status=$reportsStatus&report_id=$reportId");
                exit();
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Error processing report: " . $e->getMessage();
    }
}

// Include header after all PHP processing is complete
require_once '../../components/header.php';
?>
<style>
    .required {
        color: #dc3545;
    }
    
    /* Gentelella Form Step Styling */
    .form-step {
        transition: opacity 0.3s ease-in-out;
        margin-bottom: 30px;
        clear: both;
        overflow: visible !important;
        height: auto !important;
        max-height: none !important;
    }
    
    .form-step.x_panel {
        overflow: visible !important;
        height: auto !important;
        max-height: none !important;
        background: #fff;
        border: 1px solid #E6E9ED;
        padding: 10px 17px;
        display: inline-block;
        width: 100%;
        margin-bottom: 20px;
    }
    
    .form-step .x_content {
        overflow: visible !important;
        max-height: none !important;
        height: auto !important;
        padding: 0 5px 6px;
        position: relative;
        width: 100%;
        float: left;
        clear: both;
        margin-top: 5px;
    }
    
    .form-step.previous-step {
        opacity: 0.8;
    }
    
    /* Gentelella Form Group Styling */
    .form-step .form-group {
        margin-bottom: 20px;
    }
    
    .form-step .form-group label {
        font-weight: bold !important;
        color: #758697;
        margin-bottom: 10px;
        display: block;
        font-size: 15px;
    }
    
    .form-step .form-group > div {
        color: #333;
        font-size: 15px;
        font-weight: 500;
        padding: 5px 0;
        word-wrap: break-word;
        min-height: 22px;
    }
    
    .form-step .form-group .control-label {
        font-weight: bold !important;
        font-size: 15px;
    }
    
    /* Incident Reference Data specific styling */
    .form-step .row {
        margin-left: -15px;
        margin-right: -15px;
    }
    
    .form-step .row > [class*="col-"] {
        padding-left: 15px;
        padding-right: 15px;
        margin-bottom: 15px;
    }
    
    .form-step .data-field {
        margin-bottom: 20px;
    }
    
    .form-step .data-field label {
        font-weight: bold !important;
        color: #758697;
        margin-bottom: 10px;
        display: block;
        font-size: 16px;
    }
    
    .form-step .data-field .data-value {
        color: #333;
        font-size: 16px;
        font-weight: 600;
        padding: 5px 0;
        word-wrap: break-word;
        min-height: 24px;
    }
    
    /* Form Navigation */
    .form-navigation {
        padding: 20px 0;
        border-top: 1px solid #E6E9ED;
        margin-top: 20px;
        text-align: center;
        clear: both;
    }
    
    .form-navigation .btn {
        margin: 0 5px;
        min-width: 120px;
        padding: 8px 20px;
        font-size: 14px;
    }
    
    /* Gentelella Input Styling */
    .form-control {
        border: 1px solid #D7DCE2;
        border-radius: 0;
        padding: 8px 12px;
        font-size: 14px;
        color: #555;
        background-color: #fff;
    }
    
    .form-control:focus {
        border-color: #26B99A;
        outline: 0;
        box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(38, 185, 154, 0.3);
    }
    
    /* Gentelella Panel Title - Bold Red */
    .x_title {
        border-bottom: 2px solid #E6E9ED;
        padding: 1px 5px 6px;
        margin-bottom: 10px;
    }
    
    .x_title h2,
    .form-step .x_title h2 {
        margin: 5px 0 6px;
        float: left;
        display: block;
        font-size: 18px;
        font-weight: bold !important;
        color: #dc3545 !important;
    }
</style>
</head>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
            <div class="clearfix"></div>
            <div class="row">
                <div class="col-md-12 col-sm-12">
              
                        <!-- <div class="x_title">
                            <h2>Fire Incident Report <small>Spot Investigation Report</small></h2>
                            <div class="clearfix"></div>
                        </div> -->
                        <div class="x_content">
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger" id="error-alert" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success_message): ?>
                                <div class="alert alert-success" id="success-alert" style="display: none;">
                                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            <?php endif; ?>
        
<?php
// Parse existing involved field to determine checkbox states
$existingInvolved = '';
$personnelInvestigatorChecked = false;
$personnelOwnerChecked = false;
$personnelOccupantChecked = false;
$personnelOtherChecked = false;

if ($fireDataId > 0) {
    // Check if there's an existing report with enhanced security
    try {
        $existingReportStmt = $conn->prepare("SELECT involved FROM spot_investigation_reports WHERE fire_data_id = ?");
        if (!$existingReportStmt) {
            throw new Exception('Failed to prepare existing report query');
        }
        $existingReportStmt->bindParam(1, $fireDataId, PDO::PARAM_INT);
        $existingReportStmt->execute();
        $existingInvolved = $existingReportStmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Database error fetching existing involved data: " . $e->getMessage());
        $existingInvolved = '';
    }
    
    if ($existingInvolved) {
        $personnelInvestigatorChecked = strpos($existingInvolved, 'Investigator') !== false;
        $personnelOwnerChecked = strpos($existingInvolved, 'Owner') !== false;
        $personnelOccupantChecked = strpos($existingInvolved, 'Occupant') !== false;
        $personnelOtherChecked = strpos($existingInvolved, 'Other Involved Persons') !== false;
        
        // Extract additional details (after semicolon)
        if (strpos($existingInvolved, ';') !== false) {
            $parts = explode(';', $existingInvolved, 2);
            $existingInvolved = trim($parts[1]);
        } else {
            $existingInvolved = '';
        }
    }
}
?>

                            <?php if ($fireData): ?>
                                <div class="x_panel form-step" data-step="0">
                                    <div class="x_title">
                                        <h2><i class="fa fa-fire"></i> Incident Reference Data (ID: #<?php echo str_pad($fireData['id'], 6, '0', STR_PAD_LEFT); ?>)</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="x_content">
                                        <div class="row">
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Building Name</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($fireData['building_name'] ?: 'Unknown'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Building Type</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($fireData['building_type'] ?: 'Unknown'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Barangay</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($fireData['barangay_name'] ?: 'Unknown'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Smoke Level</label>
                                                    <div class="data-value"><?php echo $fireData['smoke']; ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Temperature</label>
                                                    <div class="data-value"><?php echo $fireData['temp']; ?>°C</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Heat Level</label>
                                                    <div class="data-value"><?php echo $fireData['heat']; ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Flame Detection</label>
                                                    <div class="data-value"><?php echo $fireData['flame_detected'] ? 'Detected' : 'Not Detected'; ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Detection Confidence</label>
                                                    <div class="data-value"><?php echo number_format($fireData['ml_confidence'], 1); ?>%</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Detection Time</label>
                                                    <div class="data-value"><?php echo date('M d, Y H:i', strtotime($fireData['timestamp'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Property Owner</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($fireData['property_owner_name'] ?: 'Unknown'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Contact Person</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($fireData['contact_person'] ?: 'Unknown'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <div class="data-field">
                                                    <label>Contact Number</label>
                                                    <div class="data-value"><?php echo htmlspecialchars($fireData['contact_number'] ?: 'Unknown'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()">
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
        
                            <form method="POST" action="" class="form-horizontal form-label-left" id="reportForm">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <!-- Report Header Section -->
                                <div class="x_panel form-step" data-step="<?php echo $fireData ? '1' : '0'; ?>" style="<?php echo $fireData ? 'display: none;' : ''; ?>">
                                    <div class="x_title">
                                        <h2>Report Header</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="report_for">Fire Chief <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="text" class="form-control" id="report_for" name="report_for" value="<?php echo htmlspecialchars($_POST['report_for'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="reports_status">Status</label>
                                            <div class="col-md-9 col-sm-9">
                                                <select class="form-control" id="reports_status" name="reports_status">
                                                    <option value="draft" <?php echo ($_POST['reports_status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="pending_review" <?php echo ($_POST['reports_status'] ?? '') === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                                                    <option value="final" <?php echo ($_POST['reports_status'] ?? '') === 'final' ? 'selected' : ''; ?>>Final</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="subject">Subject <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? 'Fire Incident Report'); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="date_completed">Date Completed <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="date" class="form-control" id="date_completed" name="date_completed" value="<?php echo $_POST['date_completed'] ?? date('Y-m-d'); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()" style="<?php echo $fireData ? '' : 'display: none;'; ?>">
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()">
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                
                                <!-- Incident Details Section -->
                                <div class="x_panel form-step" data-step="<?php echo $fireData ? '2' : '1'; ?>" style="display: none;">
                                    <div class="x_title">
                                        <h2>Incident Details</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="date_occurrence">Date of Occurrence <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="date" class="form-control" id="date_occurrence" name="date_occurrence" value="<?php echo $_POST['date_occurrence'] ?? ($fireData ? date('Y-m-d', strtotime($fireData['timestamp'])) : ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="time_occurrence">Time of Occurrence <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="time" class="form-control" id="time_occurrence" name="time_occurrence" value="<?php echo $_POST['time_occurrence'] ?? ($fireData ? date('H:i', strtotime($fireData['timestamp'])) : ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="place_occurrence">Location <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="text" class="form-control" id="place_occurrence" name="place_occurrence" value="<?php echo htmlspecialchars($_POST['place_occurrence'] ?? ($fireData ? ($fireData['building_address'] ?? $fireData['barangay_name'] ?? '') : '')); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="establishment_name">Establishment <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="text" class="form-control" id="establishment_name" name="establishment_name" value="<?php echo htmlspecialchars($_POST['establishment_name'] ?? ($fireData ? ($fireData['building_name'] ?? '') : '')); ?>" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="owner">Property Owner</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="text" class="form-control" id="owner" name="owner" value="<?php echo htmlspecialchars($_POST['owner'] ?? ($fireData ? ($fireData['property_owner_name'] ?? '') : '')); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="occupant">Occupant</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="text" class="form-control" id="occupant" name="occupant" value="<?php echo htmlspecialchars($_POST['occupant'] ?? ($fireData ? ($fireData['contact_person'] ?? '') : '')); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()">
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()">
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                
                                <!-- Casualties and Damage Section -->
                                <div class="x_panel form-step" data-step="<?php echo $fireData ? '3' : '2'; ?>" style="display: none;">
                                    <div class="x_title">
                                        <h2>Casualties and Damage Assessment</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="fatalities">Fatalities</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="number" class="form-control" id="fatalities" name="fatalities" value="<?php echo $_POST['fatalities'] ?? 0; ?>" min="0">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="injured">Injured</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="number" class="form-control" id="injured" name="injured" value="<?php echo $_POST['injured'] ?? 0; ?>" min="0">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="estimated_damage">Estimated Damage (P)</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="number" class="form-control" id="estimated_damage" name="estimated_damage" value="<?php echo $_POST['estimated_damage'] ?? 0; ?>" min="0" step="0.01">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="highest_alarm_level">Alarm Level</label>
                                            <div class="col-md-9 col-sm-9">
                                                <select class="form-control" id="highest_alarm_level" name="highest_alarm_level">
                                                    <option value="Level 1" <?php echo ($_POST['highest_alarm_level'] ?? 'Level 1') === 'Level 1' ? 'selected' : ''; ?>>Level 1</option>
                                                    <option value="Level 2" <?php echo ($_POST['highest_alarm_level'] ?? '') === 'Level 2' ? 'selected' : ''; ?>>Level 2</option>
                                                    <option value="Level 3" <?php echo ($_POST['highest_alarm_level'] ?? '') === 'Level 3' ? 'selected' : ''; ?>>Level 3</option>
                                                    <option value="Level 4" <?php echo ($_POST['highest_alarm_level'] ?? '') === 'Level 4' ? 'selected' : ''; ?>>Level 4</option>
                                                    <option value="Level 5" <?php echo ($_POST['highest_alarm_level'] ?? '') === 'Level 5' ? 'selected' : ''; ?>>Level 5</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="time_fire_started">Fire Start Time</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="datetime-local" class="form-control" id="time_fire_started" name="time_fire_started" value="<?php echo $_POST['time_fire_started'] ?? ($fireData ? date('Y-m-d\TH:i', strtotime($fireData['timestamp'])) : ''); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="time_fire_out">Fire Extinguished</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="datetime-local" class="form-control" id="time_fire_out" name="time_fire_out" value="<?php echo $_POST['time_fire_out'] ?? ''; ?>" placeholder="mm/dd/yyyy --:--">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()">
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()">
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                
                                <!-- Assessment Details Section -->
                                <div class="x_panel form-step" data-step="<?php echo $fireData ? '4' : '3'; ?>" style="display: none;">
                                    <div class="x_title">
                                        <h2>Assessment Details</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="establishments_affected">Properties Affected</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="number" class="form-control" id="establishments_affected" name="establishments_affected" value="<?php echo $_POST['establishments_affected'] ?? 1; ?>" min="1">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="estimated_area_sqm">Area Affected (sqm)</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="number" class="form-control" id="estimated_area_sqm" name="estimated_area_sqm" value="<?php echo $_POST['estimated_area_sqm'] ?? 0; ?>" min="0" step="0.01">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="damage_computation">Total Damage (₱)</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="number" class="form-control" id="damage_computation" name="damage_computation" value="<?php echo $_POST['damage_computation'] ?? ($_POST['estimated_damage'] ?? 0); ?>" min="0" step="0.01">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="weather_condition">Weather Conditions</label>
                                            <div class="col-md-9 col-sm-9">
                                                <select class="form-control" id="weather_condition" name="weather_condition">
                                                    <option value="Normal" <?php echo ($_POST['weather_condition'] ?? '') === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                                    <option value="Rainy" <?php echo ($_POST['weather_condition'] ?? '') === 'Rainy' ? 'selected' : ''; ?>>Rainy</option>
                                                    <option value="Sunny" <?php echo ($_POST['weather_condition'] ?? '') === 'Sunny' ? 'selected' : ''; ?>>Sunny</option>
                                                    <option value="Windy" <?php echo ($_POST['weather_condition'] ?? '') === 'Windy' ? 'selected' : ''; ?>>Windy</option>
                                                    <option value="Stormy" <?php echo ($_POST['weather_condition'] ?? '') === 'Stormy' ? 'selected' : ''; ?>>Stormy</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3">Personnel Involved</label>
                                            <div class="col-md-9 col-sm-9">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_investigator" name="personnel_investigator" value="1" <?php echo (isset($_POST['personnel_investigator']) || $personnelInvestigatorChecked) ? 'checked' : ''; ?>> Investigator
                                                    </label>
                                                </div>
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_owner" name="personnel_owner" value="1" <?php echo (isset($_POST['personnel_owner']) || $personnelOwnerChecked) ? 'checked' : ''; ?>> Owner
                                                    </label>
                                                </div>
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_occupant" name="personnel_occupant" value="1" <?php echo (isset($_POST['personnel_occupant']) || $personnelOccupantChecked) ? 'checked' : ''; ?>> Occupant
                                                    </label>
                                                </div>
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_other" name="personnel_other" value="1" <?php echo (isset($_POST['personnel_other']) || $personnelOtherChecked) ? 'checked' : ''; ?>> Other Involved Persons
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="involved">Additional Personnel Details</label>
                                            <div class="col-md-9 col-sm-9">
                                                <input type="text" class="form-control" id="involved" name="involved" value="<?php echo htmlspecialchars($_POST['involved'] ?? $existingInvolved ?: 'Fire Department Personnel'); ?>" placeholder="Specify other personnel involved...">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()">
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()">
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                
                                <!-- Investigation Notes Section -->
                                <div class="x_panel form-step" data-step="<?php echo $fireData ? '5' : '4'; ?>" style="display: none;">
                                    <div class="x_title">
                                        <h2>Investigation Notes</h2>
                                        <div class="clearfix"></div>
                                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="location_of_fatalities">Fatalities Location</label>
                                            <div class="col-md-9 col-sm-9">
                                                <textarea class="form-control" id="location_of_fatalities" name="location_of_fatalities" rows="4" placeholder="Specify exact location where fatalities were found..."><?php echo htmlspecialchars($_POST['location_of_fatalities'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="other_info">Additional Findings</label>
                                            <div class="col-md-9 col-sm-9">
                                                <textarea class="form-control" id="other_info" name="other_info" rows="4" placeholder="Any additional information, observations, or findings..."><?php echo htmlspecialchars($_POST['other_info'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="disposition">Investigation Disposition</label>
                                            <div class="col-md-9 col-sm-9">
                                                <textarea class="form-control" id="disposition" name="disposition" rows="4" placeholder="Final disposition and recommendations..."><?php echo htmlspecialchars($_POST['disposition'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()">
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="ln_solid"></div>
                                <div class="form-group" id="submit-section" style="display: none;">
                                    <div class="col-md-12 col-sm-12 text-center">
                                        <?php 
                                        try {
                                            $existingReportStmt = $conn->prepare("SELECT id FROM spot_investigation_reports WHERE fire_data_id = ?");
                                            if ($existingReportStmt) {
                                                $existingReportStmt->bindParam(1, $fireDataId, PDO::PARAM_INT);
                                                $existingReportStmt->execute();
                                                $hasExistingReport = $existingReportStmt->fetch();
                                            } else {
                                                $hasExistingReport = false;
                                            }
                                        } catch (Exception $e) {
                                            error_log("Database error checking existing report for button: " . $e->getMessage());
                                            $hasExistingReport = false;
                                        }
                                        ?>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fa fa-<?php echo $hasExistingReport ? 'save' : 'file-alt'; ?>"></i> <?php echo $hasExistingReport ? 'Update Report' : 'Generate Report'; ?>
                                        </button>
                                        <a href="index.php" class="btn btn-primary">
                                            <i class="fa fa-arrow-left"></i> Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../components/scripts.php'; ?>
    <script>
    // Form step navigation
    let currentStep = 0;
    const totalSteps = document.querySelectorAll('.form-step').length;
    
    function showStep(step) {
        const steps = document.querySelectorAll('.form-step');
        steps.forEach((s, index) => {
            // Show all steps from 0 to current step (including current)
            if (index <= step) {
                s.style.display = 'block';
                // Add visual class to distinguish previous steps
                s.classList.remove('previous-step');
                if (index < step) {
                    s.classList.add('previous-step');
                }
            } else {
                s.style.display = 'none';
                s.classList.remove('previous-step');
            }
        });
        
        // Scroll to current step
        if (steps[step]) {
            setTimeout(() => {
                steps[step].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
        
        // Show/hide submit section on last step
        const submitSection = document.getElementById('submit-section');
        if (submitSection) {
            if (step === totalSteps - 1) {
                submitSection.style.display = 'block';
            } else {
                submitSection.style.display = 'none';
            }
        }
        
        // Update previous button visibility for current step
        const currentStepPanel = steps[step];
        if (currentStepPanel) {
            const prevButton = currentStepPanel.querySelector('.btn-prev');
            if (prevButton) {
                prevButton.style.display = step === 0 ? 'none' : 'inline-block';
            }
        }
        
        // Hide Next buttons on all previous steps, show only on current step
        steps.forEach((s, index) => {
            const nextButton = s.querySelector('.btn-next');
            if (nextButton) {
                if (index === step) {
                    nextButton.style.display = 'inline-block';
                } else {
                    nextButton.style.display = 'none';
                }
            }
        });
        
        currentStep = step;
    }
    
    function nextStep() {
        if (currentStep < totalSteps - 1) {
            // Validate current step before proceeding
            if (validateCurrentStep(currentStep)) {
                showStep(currentStep + 1);
            }
        }
    }
    
    function prevStep() {
        if (currentStep > 0) {
            showStep(currentStep - 1);
        }
    }
    
    function validateCurrentStep(step) {
        const stepPanel = document.querySelectorAll('.form-step')[step];
        if (!stepPanel) return true;
        
        const stepData = parseInt(stepPanel.getAttribute('data-step'));
        
        // Get required fields in current step
        const requiredFields = stepPanel.querySelectorAll('[required]');
        let isValid = true;
        const errors = [];
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                const fieldName = field.getAttribute('name') || field.getAttribute('id');
                errors.push(`${fieldName} is required`);
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Additional validations for specific steps based on data-step attribute
        // Report Header step (step 1 if fireData exists, step 0 if not)
        const reportHeaderStep = <?php echo $fireData ? '1' : '0'; ?>;
        if (stepData === reportHeaderStep) {
            const subject = document.getElementById('subject');
            if (subject && subject.value.trim().length > 0 && subject.value.trim().length < 10) {
                isValid = false;
                subject.classList.add('is-invalid');
                errors.push('Subject must be at least 10 characters long.');
            }
        }
        
        // Incident Details step (step 2 if fireData exists, step 1 if not)
        const incidentDetailsStep = <?php echo $fireData ? '2' : '1'; ?>;
        if (stepData === incidentDetailsStep) {
            const placeOccurrence = document.getElementById('place_occurrence');
            if (placeOccurrence && placeOccurrence.value.trim().length > 0 && placeOccurrence.value.trim().length < 5) {
                isValid = false;
                placeOccurrence.classList.add('is-invalid');
                errors.push('Location must be at least 5 characters long.');
            }
            
            // Validate date logic
            if (!validateDateLogic()) {
                isValid = false;
            }
        }
        
        if (!isValid && errors.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: 'Please complete all required fields before proceeding:<br>' + errors.join('<br>'),
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        }
        
        return isValid;
    }
    
    // Initialize: show first step
    document.addEventListener('DOMContentLoaded', function() {
        showStep(0);
    });
    
    // Real-time validation feedback
    document.addEventListener('DOMContentLoaded', function() {
        // Show SweetAlert2 modals for PHP messages
        <?php if ($error_message): ?>
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            html: '<?php echo addslashes($error_message); ?>',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545',
            allowOutsideClick: false
        });
        <?php endif; ?>
        
        <?php if ($success_message): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            html: '<?php echo addslashes($success_message); ?>',
            confirmButtonText: 'OK',
            confirmButtonColor: '#28a745',
            allowOutsideClick: false
        });
        <?php endif; ?>
        // Required field validation
        const requiredFields = ['report_for', 'subject', 'date_completed', 'date_occurrence', 'time_occurrence', 'place_occurrence', 'establishment_name'];
        
        requiredFields.forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('blur', function() {
                    validateRequiredField(this);
                });
                field.addEventListener('input', function() {
                    clearFieldError(this);
                });
            }
        });
        
        // Subject length validation
        const subjectField = document.getElementById('subject');
        if (subjectField) {
            subjectField.addEventListener('input', function() {
                const value = this.value.trim();
                if (value.length > 0 && value.length < 10) {
                    showFieldError(this, 'Subject must be at least 10 characters long.');
                } else {
                    clearFieldError(this);
                }
            });
        }
        
        // Location length validation
        const locationField = document.getElementById('place_occurrence');
        if (locationField) {
            locationField.addEventListener('input', function() {
                const value = this.value.trim();
                if (value.length > 0 && value.length < 5) {
                    showFieldError(this, 'Location must be at least 5 characters long.');
                } else {
                    clearFieldError(this);
                }
            });
        }
        
        // Numeric field validation
        const numericFields = ['fatalities', 'injured', 'estimated_damage', 'establishments_affected', 'estimated_area_sqm', 'damage_computation'];
        numericFields.forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', function() {
                    validateNumericField(this);
                });
            }
        });
        
        // Date validation
        const dateCompletedField = document.getElementById('date_completed');
        const dateOccurrenceField = document.getElementById('date_occurrence');
        
        if (dateCompletedField && dateOccurrenceField) {
            [dateCompletedField, dateOccurrenceField].forEach(function(field) {
                field.addEventListener('change', function() {
                    validateDateLogic();
                });
                field.addEventListener('input', function() {
                    // Clear error when user starts typing/changing
                    clearFieldError(field);
                    // Re-validate after a short delay to avoid validation while typing
                    setTimeout(function() {
                        validateDateLogic();
                    }, 100);
                });
            });
        }
        
        // Fire time validation
        const timeFireStartedField = document.getElementById('time_fire_started');
        const timeFireOutField = document.getElementById('time_fire_out');
        
        if (timeFireStartedField && timeFireOutField) {
            [timeFireStartedField, timeFireOutField].forEach(function(field) {
                field.addEventListener('change', function() {
                    validateFireTimeLogic();
                });
                field.addEventListener('input', function() {
                    // Clear error when user starts typing
                    clearFieldError(field);
                });
            });
        }
        
        // Personnel validation
        const personnelCheckboxes = document.querySelectorAll('input[name^="personnel_"]');
        personnelCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                validatePersonnelSelection();
            });
        });
        
        // Form submission validation
        const form = document.getElementById('reportForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate all steps before submission
                let allStepsValid = true;
                for (let i = 0; i < totalSteps; i++) {
                    if (!validateCurrentStep(i)) {
                        allStepsValid = false;
                        showStep(i); // Show the step with errors
                        break;
                    }
                }
                
                if (!allStepsValid) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Failed',
                        html: 'Please complete all required fields in all sections before submitting.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }
                
                if (!validateForm()) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Failed',
                        html: 'Please correct the errors highlighted in red before submitting.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }
                
                // Show confirmation modal
                Swal.fire({
                    title: 'Confirm Report Submission',
                    html: 'Are you sure you want to submit this fire incident report?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Submit Report',
                    cancelButtonText: 'Cancel',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading modal
                        Swal.fire({
                            title: 'Processing...',
                            html: 'Please wait while we save your report.',
                            icon: 'info',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        form.submit();
                    }
                });
            });
        }
    });
    
    function validateRequiredField(field) {
        const value = field.value.trim();
        if (value === '') {
            showFieldError(field, 'This field is required.');
            return false;
        }
        clearFieldError(field);
        return true;
    }
    
    function validateNumericField(field) {
        const value = parseFloat(field.value);
        const fieldName = field.getAttribute('name');
        
        if (isNaN(value) || value < 0) {
            showFieldError(field, 'This field must be a non-negative number.');
            return false;
        }
        
        if (fieldName === 'establishments_affected' && value < 1) {
            showFieldError(field, 'Properties Affected must be at least 1.');
            return false;
        }
        
        clearFieldError(field);
        return true;
    }
    
    function validateDateLogic() {
        const dateCompleted = document.getElementById('date_completed');
        const dateOccurrence = document.getElementById('date_occurrence');
        
        // Clear any existing errors first
        if (dateCompleted) clearFieldError(dateCompleted);
        if (dateOccurrence) clearFieldError(dateOccurrence);
        
        // Only validate if both dates are present
        if (dateCompleted && dateOccurrence && dateCompleted.value && dateOccurrence.value) {
            // Parse dates as local dates (YYYY-MM-DD format) to avoid timezone issues
            const completedDate = new Date(dateCompleted.value + 'T00:00:00');
            const occurrenceDate = new Date(dateOccurrence.value + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to midnight for accurate date comparison
            
            // Check if Date Completed is before Date of Occurrence
            if (completedDate < occurrenceDate) {
                showFieldError(dateCompleted, 'Date Completed cannot be before Date of Occurrence.');
                return false;
            }
            
            // Validate Date of Occurrence is not more than 30 days in the past
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            thirtyDaysAgo.setHours(0, 0, 0, 0);
            
            if (occurrenceDate < thirtyDaysAgo) {
                showFieldError(dateOccurrence, 'Date of Occurrence cannot be more than 30 days in the past.');
                return false;
            }
        }
        
        return true;
    }
    
    function validateFireTimeLogic() {
        const timeFireStarted = document.getElementById('time_fire_started');
        const timeFireOut = document.getElementById('time_fire_out');
        
        if (timeFireStarted && timeFireOut && timeFireStarted.value && timeFireOut.value) {
            const fireStartedTime = new Date(timeFireStarted.value);
            const fireOutTime = new Date(timeFireOut.value);
            
            if (fireStartedTime >= fireOutTime) {
                showFieldError(timeFireOut, 'Fire Extinguished Time must be after Fire Start Time.');
                return false;
            } else {
                clearFieldError(timeFireOut);
            }
        }
        
        return true;
    }
    
    function validatePersonnelSelection() {
        const personnelCheckboxes = document.querySelectorAll('input[name^="personnel_"]:checked');
        const personnelSection = document.querySelector('input[name^="personnel_"]').closest('.col-md-12');
        
        if (personnelCheckboxes.length === 0) {
            showSectionError(personnelSection, 'At least one Personnel Involved option must be selected.');
            return false;
        } else {
            clearSectionError(personnelSection);
        }
        
        return true;
    }
    
    function validateForm() {
        let isValid = true;
        
        // Validate all required fields
        const requiredFields = ['report_for', 'subject', 'date_completed', 'date_occurrence', 'time_occurrence', 'place_occurrence', 'establishment_name'];
        requiredFields.forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (field && !validateRequiredField(field)) {
                isValid = false;
            }
        });
        
        // Validate numeric fields
        const numericFields = ['fatalities', 'injured', 'estimated_damage', 'establishments_affected', 'estimated_area_sqm', 'damage_computation'];
        numericFields.forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (field && !validateNumericField(field)) {
                isValid = false;
            }
        });
        
        // Validate dates
        if (!validateDateLogic()) {
            isValid = false;
        }
        
        // Validate fire times
        if (!validateFireTimeLogic()) {
            isValid = false;
        }
        
        // Validate personnel
        if (!validatePersonnelSelection()) {
            isValid = false;
        }
        
        return isValid;
    }
    
    function showFieldError(field, message) {
        clearFieldError(field);
        field.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
        
        // Show SweetAlert2 toast for field errors
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
        
        // Show success toast for cleared errors (optional - can be removed if too noisy)
        // Swal.fire({
        //     icon: 'success',
        //     title: 'Valid',
        //     text: 'Field is now valid',
        //     toast: true,
        //     position: 'top-end',
        //     showConfirmButton: false,
        //     timer: 1500,
        //     timerProgressBar: true
        // });
    }
    
    function showSectionError(section, message) {
        clearSectionError(section);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-2';
        errorDiv.textContent = message;
        
        section.appendChild(errorDiv);
        
        // Show SweetAlert2 toast for section errors
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    function clearSectionError(section) {
        const errorDiv = section.querySelector('.alert.alert-danger');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    </script>
</body>
</html>
