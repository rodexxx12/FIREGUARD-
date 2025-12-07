<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../db/db.php';
require_once 'datetime_helper.php';
require_once 'classes/InputValidator.php';
require_once 'classes/ErrorHandler.php';
require_once 'classes/SecurityHeaders.php';

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

$conn = getDatabaseConnection();
$success_message = '';
$error_message = '';

// Get report ID from URL and validate
$report_id = InputValidator::validateInt($_GET['id'] ?? 0, 1);

if (!$report_id) {
    header('Location: index.php');
    exit();
}

// Fetch report details
$stmt = $conn->prepare("SELECT * FROM spot_investigation_reports WHERE id = ?");
$stmt->bindParam(1, $report_id, PDO::PARAM_INT);
$stmt->execute();
$report = $stmt->fetch();

if (!$report) {
    header('Location: index.php');
    exit();
}

// Parse existing involved field to determine checkbox states
$personnelInvestigatorChecked = false;
$personnelOwnerChecked = false;
$personnelOccupantChecked = false;
$personnelOtherChecked = false;
$existingInvolved = '';

if ($report['involved']) {
    $personnelInvestigatorChecked = strpos($report['involved'], 'Investigator') !== false;
    $personnelOwnerChecked = strpos($report['involved'], 'Owner') !== false;
    $personnelOccupantChecked = strpos($report['involved'], 'Occupant') !== false;
    $personnelOtherChecked = strpos($report['involved'], 'Other Involved Persons') !== false;
    
    // Extract additional details (after semicolon)
    if (strpos($report['involved'], ';') !== false) {
        $parts = explode(';', $report['involved'], 2);
        $existingInvolved = trim($parts[1]);
    } else {
        $existingInvolved = '';
    }
}

// If report status is 'final', redirect to view.php
if ($report['reports_status'] === 'final') {
    header('Location: view.php?id=' . $report_id);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prevent updates if report status is final
    if ($report['reports_status'] === 'final') {
        $error_message = 'Cannot update report: Report has been finalized and cannot be modified.';
    } else {
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
            
            if ($dateCompleted && $dateOccurrence) {
                if (strtotime($dateCompleted) < strtotime($dateOccurrence)) {
                    $validation_errors[] = "Date Completed cannot be before Date of Occurrence.";
                }
            }
            
            if ($dateOccurrence) {
                $occurrenceTimestamp = strtotime($dateOccurrence);
                $thirtyDaysAgo = strtotime('-30 days');
                if ($occurrenceTimestamp < $thirtyDaysAgo) {
                    $validation_errors[] = "Date of Occurrence cannot be more than 30 days in the past.";
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
            
            // Input sanitization
            $reportFor = htmlspecialchars(trim($_POST['report_for'] ?? ''), ENT_QUOTES, 'UTF-8');
            $subject = htmlspecialchars(trim($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
            $placeOccurrence = htmlspecialchars(trim($_POST['place_occurrence'] ?? ''), ENT_QUOTES, 'UTF-8');
            $establishmentName = htmlspecialchars(trim($_POST['establishment_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $owner = htmlspecialchars(trim($_POST['owner'] ?? ''), ENT_QUOTES, 'UTF-8');
            $occupant = htmlspecialchars(trim($_POST['occupant'] ?? ''), ENT_QUOTES, 'UTF-8');
            $locationOfFatalities = htmlspecialchars(trim($_POST['location_of_fatalities'] ?? ''), ENT_QUOTES, 'UTF-8');
            $otherInfo = htmlspecialchars(trim($_POST['other_info'] ?? ''), ENT_QUOTES, 'UTF-8');
            $disposition = htmlspecialchars(trim($_POST['disposition'] ?? ''), ENT_QUOTES, 'UTF-8');
            $investigatorName = htmlspecialchars(trim($_POST['investigator_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $investigatorSignature = htmlspecialchars(trim($_POST['investigator_signature'] ?? ''), ENT_QUOTES, 'UTF-8');
            $additionalInvolved = htmlspecialchars(trim($_POST['involved'] ?? ''), ENT_QUOTES, 'UTF-8');
            
            // Character limit validations
            if (strlen($subject) > 200) {
                $validation_errors[] = "Subject must not exceed 200 characters.";
            }
            if (strlen($placeOccurrence) > 500) {
                $validation_errors[] = "Location must not exceed 500 characters.";
            }
            if (strlen($establishmentName) > 200) {
                $validation_errors[] = "Establishment Name must not exceed 200 characters.";
            }
            if (strlen($owner) > 100) {
                $validation_errors[] = "Property Owner name must not exceed 100 characters.";
            }
            if (strlen($occupant) > 100) {
                $validation_errors[] = "Occupant name must not exceed 100 characters.";
            }
            if (strlen($locationOfFatalities) > 1000) {
                $validation_errors[] = "Fatalities Location must not exceed 1000 characters.";
            }
            if (strlen($otherInfo) > 2000) {
                $validation_errors[] = "Additional Findings must not exceed 2000 characters.";
            }
            if (strlen($disposition) > 2000) {
                $validation_errors[] = "Investigation Disposition must not exceed 2000 characters.";
            }
            if (strlen($investigatorName) > 100) {
                $validation_errors[] = "Investigator Name must not exceed 100 characters.";
            }
            if (strlen($additionalInvolved) > 500) {
                $validation_errors[] = "Additional Personnel Details must not exceed 500 characters.";
            }
            
            // If there are validation errors, display them and stop processing
            if (!empty($validation_errors)) {
                $error_message = "Please correct the following errors:<br>" . implode("<br>", array_map('htmlspecialchars', $validation_errors));
            } else {
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
                    WHERE id = :id
                ");
                
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
                
                // If status is being changed to 'final', set date_completed to current datetime
                if ($reportsStatus === 'final' && $report['reports_status'] !== 'final') {
                    $dateCompleted = date('Y-m-d H:i:s');
                }
                
                $turnedOver = isset($_POST['turned_over']) ? 1 : 0;
                
                $stmt->bindParam(':id', $report_id, PDO::PARAM_INT);
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
                
                $stmt->execute();
                
                // Set success flag for JavaScript handling
                $success_updated = true;
                
                // Refresh report data
                $stmt = $conn->prepare("SELECT * FROM spot_investigation_reports WHERE id = ?");
                $stmt->bindParam(1, $report_id, PDO::PARAM_INT);
                $stmt->execute();
                $report = $stmt->fetch();
            }
            
        } catch (Exception $e) {
            $error_message = 'Error updating report: ' . $e->getMessage();
        }
    }
}

// Include header after all header() calls are done
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
    
    .form-control:disabled {
        background-color: #f5f5f5;
        cursor: not-allowed;
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
                        <div class="x_content">
            <?php if ($error_message): ?>
                <div class="alert alert-danger" id="error-alert" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php 
            $isReadOnlyStatus = $report['reports_status'] === 'final';
            $formDisabled = $isReadOnlyStatus ? 'disabled' : '';
            ?>
            
            <?php if ($isReadOnlyStatus): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This report has been finalized and cannot be edited. All fields are read-only.
                </div>
            <?php endif; ?>
            
                            <form method="POST" action="" class="form-horizontal form-label-left" id="spotReportForm" <?php echo $isReadOnlyStatus ? 'onsubmit="return false;"' : ''; ?>>
                <!-- Report Header Section -->
                                <div class="x_panel form-step" data-step="0">
                                    <div class="x_title">
                                        <h2>Report Header</h2>
                                        <div class="clearfix"></div>
                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="report_for">Report For <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="report_for" name="report_for" value="<?php echo htmlspecialchars($report['report_for']); ?>" required <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="subject">Subject <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo htmlspecialchars($report['subject']); ?>" required <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="reports_status">Status</label>
                                            <div class="col-md-9 col-sm-9">
                                                <select class="form-control" id="reports_status" name="reports_status" <?php echo $formDisabled; ?>>
                                    <option value="draft" <?php echo $report['reports_status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="pending_review" <?php echo $report['reports_status'] === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                                    <option value="final" <?php echo $report['reports_status'] === 'final' ? 'selected' : ''; ?>>Final</option>
                                </select>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="date_completed">Date Completed <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="date" class="form-control" id="date_completed" name="date_completed" value="<?php echo $report['date_completed']; ?>" required <?php echo $formDisabled; ?>>
                            </div>
                        </div>
                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()" <?php echo $formDisabled; ?>>
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                    </div>
                </div>
                
                <!-- Incident Details Section -->
                                <div class="x_panel form-step" data-step="1" style="display: none;">
                                    <div class="x_title">
                                        <h2>Incident Details</h2>
                                        <div class="clearfix"></div>
                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="date_occurrence">Date of Occurrence <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="date" class="form-control" id="date_occurrence" name="date_occurrence" value="<?php echo $report['date_occurrence']; ?>" required <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="time_occurrence">Time of Occurrence <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="time" class="form-control" id="time_occurrence" name="time_occurrence" value="<?php echo $report['time_occurrence']; ?>" required <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="place_occurrence">Location <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="place_occurrence" name="place_occurrence" value="<?php echo htmlspecialchars($report['place_occurrence']); ?>" required <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="establishment_name">Establishment <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="establishment_name" name="establishment_name" value="<?php echo htmlspecialchars($report['establishment_name']); ?>" required <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="owner">Property Owner</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="owner" name="owner" value="<?php echo htmlspecialchars($report['owner']); ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="occupant">Occupant</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="occupant" name="occupant" value="<?php echo htmlspecialchars($report['occupant']); ?>" <?php echo $formDisabled; ?>>
                            </div>
                        </div>
                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()" <?php echo $formDisabled; ?>>
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()" <?php echo $formDisabled; ?>>
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                    </div>
                </div>
                
                <!-- Casualties and Damage Section -->
                                <div class="x_panel form-step" data-step="2" style="display: none;">
                                    <div class="x_title">
                                        <h2>Casualties and Damage Assessment</h2>
                                        <div class="clearfix"></div>
                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="fatalities">Fatalities</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="number" class="form-control" id="fatalities" name="fatalities" min="0" value="<?php echo $report['fatalities']; ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="injured">Injured</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="number" class="form-control" id="injured" name="injured" min="0" value="<?php echo $report['injured']; ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="estimated_damage">Estimated Damage (₱)</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="number" class="form-control" id="estimated_damage" name="estimated_damage" min="0" step="0.01" value="<?php echo $report['estimated_damage']; ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="highest_alarm_level">Alarm Level</label>
                                            <div class="col-md-9 col-sm-9">
                                                <select class="form-control" id="highest_alarm_level" name="highest_alarm_level" <?php echo $formDisabled; ?>>
                                    <option value="Level 1" <?php echo $report['highest_alarm_level'] === 'Level 1' ? 'selected' : ''; ?>>Level 1</option>
                                    <option value="Level 2" <?php echo $report['highest_alarm_level'] === 'Level 2' ? 'selected' : ''; ?>>Level 2</option>
                                    <option value="Level 3" <?php echo $report['highest_alarm_level'] === 'Level 3' ? 'selected' : ''; ?>>Level 3</option>
                                    <option value="Level 4" <?php echo $report['highest_alarm_level'] === 'Level 4' ? 'selected' : ''; ?>>Level 4</option>
                                    <option value="Level 5" <?php echo $report['highest_alarm_level'] === 'Level 5' ? 'selected' : ''; ?>>Level 5</option>
                                </select>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="time_fire_started">Fire Start Time</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="datetime-local" class="form-control" id="time_fire_started" name="time_fire_started" value="<?php echo getDateTimeForFormInput($report['time_fire_started']); ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="time_fire_out">Fire Extinguished</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="datetime-local" class="form-control" id="time_fire_out" name="time_fire_out" value="<?php echo !empty($report['time_fire_out']) ? getDateTimeForFormInput($report['time_fire_out']) : ''; ?>" <?php echo $formDisabled; ?>>
                            </div>
                        </div>
                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()" <?php echo $formDisabled; ?>>
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()" <?php echo $formDisabled; ?>>
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                    </div>
                </div>
                
                <!-- Assessment Details Section -->
                                <div class="x_panel form-step" data-step="3" style="display: none;">
                                    <div class="x_title">
                                        <h2>Assessment Details</h2>
                                        <div class="clearfix"></div>
                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="establishments_affected">Properties Affected</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="number" class="form-control" id="establishments_affected" name="establishments_affected" min="1" value="<?php echo $report['establishments_affected']; ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="estimated_area_sqm">Area Affected (sqm)</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="number" class="form-control" id="estimated_area_sqm" name="estimated_area_sqm" min="0" step="0.01" value="<?php echo $report['estimated_area_sqm']; ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="damage_computation">Total Damage (₱)</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="number" class="form-control" id="damage_computation" name="damage_computation" min="0" step="0.01" value="<?php echo $report['damage_computation']; ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="weather_condition">Weather Conditions</label>
                                            <div class="col-md-9 col-sm-9">
                                                <select class="form-control" id="weather_condition" name="weather_condition" <?php echo $formDisabled; ?>>
                                    <option value="Normal" <?php echo $report['weather_condition'] === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="Rainy" <?php echo $report['weather_condition'] === 'Rainy' ? 'selected' : ''; ?>>Rainy</option>
                                    <option value="Sunny" <?php echo $report['weather_condition'] === 'Sunny' ? 'selected' : ''; ?>>Sunny</option>
                                    <option value="Windy" <?php echo $report['weather_condition'] === 'Windy' ? 'selected' : ''; ?>>Windy</option>
                                    <option value="Stormy" <?php echo $report['weather_condition'] === 'Stormy' ? 'selected' : ''; ?>>Stormy</option>
                                </select>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3">Personnel Involved</label>
                                            <div class="col-md-9 col-sm-9">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_investigator" name="personnel_investigator" value="1" <?php echo $personnelInvestigatorChecked ? 'checked' : ''; ?> <?php echo $formDisabled; ?>> Investigator
                                            </label>
                                        </div>
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_owner" name="personnel_owner" value="1" <?php echo $personnelOwnerChecked ? 'checked' : ''; ?> <?php echo $formDisabled; ?>> Owner
                                            </label>
                                        </div>
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_occupant" name="personnel_occupant" value="1" <?php echo $personnelOccupantChecked ? 'checked' : ''; ?> <?php echo $formDisabled; ?>> Occupant
                                            </label>
                                        </div>
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" id="personnel_other" name="personnel_other" value="1" <?php echo $personnelOtherChecked ? 'checked' : ''; ?> <?php echo $formDisabled; ?>> Other Involved Persons
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="involved">Additional Personnel Details</label>
                                            <div class="col-md-9 col-sm-9">
                                    <input type="text" class="form-control" id="involved" name="involved" value="<?php echo htmlspecialchars($existingInvolved); ?>" placeholder="Specify other personnel involved..." <?php echo $formDisabled; ?>>
                                </div>
                            </div>
                        </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()" <?php echo $formDisabled; ?>>
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()" <?php echo $formDisabled; ?>>
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                    </div>
                </div>
                
                <!-- Investigation Notes Section -->
                                <div class="x_panel form-step" data-step="4" style="display: none;">
                                    <div class="x_title">
                                        <h2>Investigation Notes</h2>
                                        <div class="clearfix"></div>
                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="location_of_fatalities">Fatalities Location</label>
                                            <div class="col-md-9 col-sm-9">
                                <textarea class="form-control" id="location_of_fatalities" name="location_of_fatalities" rows="4" placeholder="Specify exact location where fatalities were found..." <?php echo $formDisabled; ?>><?php echo htmlspecialchars($report['location_of_fatalities']); ?></textarea>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="other_info">Additional Findings</label>
                                            <div class="col-md-9 col-sm-9">
                                <textarea class="form-control" id="other_info" name="other_info" rows="4" placeholder="Any additional information, observations, or findings..." <?php echo $formDisabled; ?>><?php echo htmlspecialchars($report['other_info']); ?></textarea>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="disposition">Investigation Disposition</label>
                                            <div class="col-md-9 col-sm-9">
                                <textarea class="form-control" id="disposition" name="disposition" rows="4" placeholder="Final disposition and recommendations..." <?php echo $formDisabled; ?>><?php echo htmlspecialchars($report['disposition']); ?></textarea>
                            </div>
                        </div>
                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()" <?php echo $formDisabled; ?>>
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                                        <button type="button" class="btn btn-primary btn-next" onclick="nextStep()" <?php echo $formDisabled; ?>>
                                            Next <i class="fa fa-arrow-right"></i>
                                        </button>
                    </div>
                </div>
                
                <!-- Investigator Information Section -->
                                <div class="x_panel form-step" data-step="5" style="display: none;">
                                    <div class="x_title">
                                        <h2>Investigator Information</h2>
                                        <div class="clearfix"></div>
                    </div>
                                    <div class="x_content">
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="investigator_name">Investigator Name <span class="required">*</span></label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="investigator_name" name="investigator_name" value="<?php echo htmlspecialchars($report['investigator_name']); ?>" required <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3" for="investigator_signature">Investigator Signature</label>
                                            <div class="col-md-9 col-sm-9">
                                <input type="text" class="form-control" id="investigator_signature" name="investigator_signature" value="<?php echo htmlspecialchars($report['investigator_signature']); ?>" <?php echo $formDisabled; ?>>
                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="control-label col-md-3 col-sm-3"></label>
                                            <div class="col-md-9 col-sm-9">
                                                <div class="checkbox">
                                                    <label>
                                                        <input type="checkbox" name="turned_over" value="1" <?php echo $report['turned_over'] ? 'checked' : ''; ?> <?php echo $formDisabled; ?>> Report Turned Over
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                                    <div class="form-navigation">
                                        <button type="button" class="btn btn-default btn-prev" onclick="prevStep()" <?php echo $formDisabled; ?>>
                                            <i class="fa fa-arrow-left"></i> Previous
                                        </button>
                    </div>
                </div>
                
                                <div class="ln_solid"></div>
                                <div class="form-group" id="submit-section" style="display: none;">
                                    <div class="col-md-12 col-sm-12 text-center">
                                        <a href="view.php?id=<?php echo $report['id']; ?>" class="btn btn-primary">
                                            <i class="fa fa-arrow-left"></i> Cancel
                    </a>
                    <?php if ($report['reports_status'] === 'final'): ?>
                                            <button type="button" class="btn btn-default" disabled title="Report is Final - Cannot be Updated">
                                                <i class="fa fa-lock"></i> Report Final - Cannot Update
                        </button>
                    <?php else: ?>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fa fa-save"></i> Update Report
                        </button>
                    <?php endif; ?>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        // Additional validations for specific steps
        // Report Header step (step 0)
        if (stepData === 0) {
            const subject = document.getElementById('subject');
            if (subject && subject.value.trim().length > 0 && subject.value.trim().length < 10) {
                isValid = false;
                subject.classList.add('is-invalid');
                errors.push('Subject must be at least 10 characters long.');
            }
        }
        
        // Incident Details step (step 1)
        if (stepData === 1) {
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
        
        // Real-time validation feedback
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
            
            // Check if report was successfully updated
            <?php if (isset($success_updated) && $success_updated): ?>
            Swal.fire({
                title: 'Success!',
                text: 'Spot Investigation Report updated successfully!',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: true
            }).then(() => {
                window.location.href = 'index.php';
            });
            <?php endif; ?>
            
            // Required field validation
            const requiredFields = ['report_for', 'subject', 'date_completed', 'date_occurrence', 'time_occurrence', 'place_occurrence', 'establishment_name', 'investigator_name'];
            
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
            const form = document.getElementById('spotReportForm');
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
                        title: 'Confirm Report Update',
                        html: 'Are you sure you want to update this fire incident report?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#007bff',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Update Report',
                        cancelButtonText: 'Cancel',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Show loading modal
                            Swal.fire({
                                title: 'Processing...',
                                html: 'Please wait while we update your report.',
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
            const firstPersonnelInput = document.querySelector('input[name^="personnel_"]');
            
            if (personnelCheckboxes.length === 0) {
                if (firstPersonnelInput) {
                    const personnelSection = firstPersonnelInput.closest('.form-group');
                    if (personnelSection) {
                        showSectionError(personnelSection, 'At least one Personnel Involved option must be selected.');
                    }
                }
                return false;
            } else {
                if (firstPersonnelInput) {
                    const personnelSection = firstPersonnelInput.closest('.form-group');
                    if (personnelSection) {
                        clearSectionError(personnelSection);
                    }
                }
            }
            
            return true;
        }
        
        function validateForm() {
            let isValid = true;
            
            // Validate all required fields
            const requiredFields = ['report_for', 'subject', 'date_completed', 'date_occurrence', 'time_occurrence', 'place_occurrence', 'establishment_name', 'investigator_name'];
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
        }
        
        function showSectionError(section, message) {
            clearSectionError(section);
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger mt-2';
            errorDiv.textContent = message;
            
            if (section) {
                section.appendChild(errorDiv);
            }
            
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
            if (section) {
                const errorDiv = section.querySelector('.alert.alert-danger');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        }
    </script>
</body>
</html>
