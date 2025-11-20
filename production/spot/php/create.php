<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../db/db.php';
require_once 'datetime_helper.php';

// Check if user is logged in BEFORE including header.php
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../../index.php');
    exit();
}


$conn = getDatabaseConnection();
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("
            INSERT INTO spot_investigation_reports (
                report_for, subject, date_completed, date_occurrence, time_occurrence,
                place_occurrence, involved, establishment_name, owner, occupant,
                fatalities, injured, estimated_damage, time_fire_started, time_fire_out,
                highest_alarm_level, establishments_affected, estimated_area_sqm,
                damage_computation, location_of_fatalities, weather_condition,
                other_info, disposition, turned_over, investigator_name, investigator_signature,
                created_at
            ) VALUES (
                :report_for, :subject, :date_completed, :date_occurrence, :time_occurrence,
                :place_occurrence, :involved, :establishment_name, :owner, :occupant,
                :fatalities, :injured, :estimated_damage, :time_fire_started, :time_fire_out,
                :highest_alarm_level, :establishments_affected, :estimated_area_sqm,
                :damage_computation, :location_of_fatalities, :weather_condition,
                :other_info, :disposition, :turned_over, :investigator_name, :investigator_signature,
                :created_at
            )
        ");
        
        // Set date_completed to current datetime if only date is provided
        $dateCompleted = $_POST['date_completed'];
        if (strlen($dateCompleted) === 10) { // Only date provided (YYYY-MM-DD)
            $dateCompleted = date('Y-m-d H:i:s');
        }
        
        // Sanitize and prepare values
        $reportFor = trim($_POST['report_for']);
        $subject = trim($_POST['subject'] ?? 'Spot Investigation Report (SIR)');
        $dateOccurrence = trim($_POST['date_occurrence']);
        $timeOccurrence = trim($_POST['time_occurrence']);
        $placeOccurrence = trim($_POST['place_occurrence']);
        $involved = trim($_POST['involved']);
        $establishmentName = trim($_POST['establishment_name']);
        $owner = trim($_POST['owner']);
        $occupant = !empty($_POST['occupant']) ? trim($_POST['occupant']) : null;
        $fatalities = isset($_POST['fatalities']) ? (int)$_POST['fatalities'] : 0;
        $injured = isset($_POST['injured']) ? (int)$_POST['injured'] : 0;
        $estimatedDamage = isset($_POST['estimated_damage']) ? (float)$_POST['estimated_damage'] : 0.00;
        $timeFireStarted = trim($_POST['time_fire_started']);
        $timeFireOut = !empty($_POST['time_fire_out']) ? trim($_POST['time_fire_out']) : null;
        $highestAlarmLevel = !empty($_POST['highest_alarm_level']) ? trim($_POST['highest_alarm_level']) : null;
        $establishmentsAffected = isset($_POST['establishments_affected']) ? (int)$_POST['establishments_affected'] : 0;
        $estimatedAreaSqm = isset($_POST['estimated_area_sqm']) ? (float)$_POST['estimated_area_sqm'] : 0.00;
        $damageComputation = isset($_POST['damage_computation']) ? (float)$_POST['damage_computation'] : 0.00;
        $locationOfFatalities = !empty($_POST['location_of_fatalities']) ? trim($_POST['location_of_fatalities']) : null;
        $weatherCondition = !empty($_POST['weather_condition']) ? trim($_POST['weather_condition']) : null;
        $otherInfo = !empty($_POST['other_info']) ? trim($_POST['other_info']) : null;
        $disposition = !empty($_POST['disposition']) ? trim($_POST['disposition']) : null;
        $turnedOver = isset($_POST['turned_over']) ? 1 : 0;
        $investigatorName = trim($_POST['investigator_name']);
        $investigatorSignature = !empty($_POST['investigator_signature']) ? trim($_POST['investigator_signature']) : null;
        $createdAt = formatDateTimeForDatabase();
        
        // Bind parameters using bindParam for SQL injection protection
        $stmt->bindParam(':report_for', $reportFor, PDO::PARAM_STR);
        $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindParam(':date_completed', $dateCompleted, PDO::PARAM_STR);
        $stmt->bindParam(':date_occurrence', $dateOccurrence, PDO::PARAM_STR);
        $stmt->bindParam(':time_occurrence', $timeOccurrence, PDO::PARAM_STR);
        $stmt->bindParam(':place_occurrence', $placeOccurrence, PDO::PARAM_STR);
        $stmt->bindParam(':involved', $involved, PDO::PARAM_STR);
        $stmt->bindParam(':establishment_name', $establishmentName, PDO::PARAM_STR);
        $stmt->bindParam(':owner', $owner, PDO::PARAM_STR);
        $stmt->bindParam(':occupant', $occupant, $occupant === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':fatalities', $fatalities, PDO::PARAM_INT);
        $stmt->bindParam(':injured', $injured, PDO::PARAM_INT);
        $stmt->bindParam(':estimated_damage', $estimatedDamage, PDO::PARAM_STR);
        $stmt->bindParam(':time_fire_started', $timeFireStarted, PDO::PARAM_STR);
        $stmt->bindParam(':time_fire_out', $timeFireOut, $timeFireOut === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':highest_alarm_level', $highestAlarmLevel, $highestAlarmLevel === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':establishments_affected', $establishmentsAffected, PDO::PARAM_INT);
        $stmt->bindParam(':estimated_area_sqm', $estimatedAreaSqm, PDO::PARAM_STR);
        $stmt->bindParam(':damage_computation', $damageComputation, PDO::PARAM_STR);
        $stmt->bindParam(':location_of_fatalities', $locationOfFatalities, $locationOfFatalities === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':weather_condition', $weatherCondition, $weatherCondition === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':other_info', $otherInfo, $otherInfo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':disposition', $disposition, $disposition === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':turned_over', $turnedOver, PDO::PARAM_INT);
        $stmt->bindParam(':investigator_name', $investigatorName, PDO::PARAM_STR);
        $stmt->bindParam(':investigator_signature', $investigatorSignature, $investigatorSignature === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':created_at', $createdAt, PDO::PARAM_STR);
        
        $stmt->execute();
        
        $success_message = 'Spot Investigation Report created successfully!';
        
    } catch (Exception $e) {
        $error_message = 'Error creating report: ' . $e->getMessage();
    }
}
?>

<?php include("../../../components/header.php")?>
    <link rel="stylesheet" href="../css/spot.css">
    <style>
        
        .page-header {
            background-color: #dc3545;
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            margin: 10px 0 0 0;
        }
        
        .form-container {
            background: white;
            border-radius: 8px;
            padding: 40px;
        }
        
        .form-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e9ecef;
        }
        
        .required {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 15px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="page-header">
            <h1 class="page-title">Create Spot Investigation Report</h1>
            <p class="page-subtitle">Document fire incident investigation details and findings</p>
        </div>
        
        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="spotReportForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="section-title">Basic Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="report_for">Report For <span class="required">*</span></label>
                            <input type="text" class="form-control" id="report_for" name="report_for" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subject">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" value="Spot Investigation Report (SIR)">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="date_completed">Date Completed <span class="required">*</span></label>
                            <input type="date" class="form-control" id="date_completed" name="date_completed" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="date_occurrence">Date of Occurrence <span class="required">*</span></label>
                            <input type="date" class="form-control" id="date_occurrence" name="date_occurrence" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="time_occurrence">Time of Occurrence <span class="required">*</span></label>
                            <input type="time" class="form-control" id="time_occurrence" name="time_occurrence" required>
                        </div>
                    </div>
                </div>
                
                <!-- Location Details -->
                <div class="form-section">
                    <h3 class="section-title">Location Details</h3>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label class="form-label" for="place_occurrence">Place of Occurrence <span class="required">*</span></label>
                            <input type="text" class="form-control" id="place_occurrence" name="place_occurrence" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="establishment_name">Establishment Name <span class="required">*</span></label>
                            <input type="text" class="form-control" id="establishment_name" name="establishment_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="owner">Owner <span class="required">*</span></label>
                            <input type="text" class="form-control" id="owner" name="owner" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="occupant">Occupant</label>
                            <input type="text" class="form-control" id="occupant" name="occupant">
                        </div>
                    </div>
                </div>
                
                <!-- Casualties and Damage -->
                <div class="form-section">
                    <h3 class="section-title">Casualties and Damage</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="fatalities">Number of Fatalities</label>
                            <input type="number" class="form-control" id="fatalities" name="fatalities" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="injured">Number of Injured</label>
                            <input type="number" class="form-control" id="injured" name="injured" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="estimated_damage">Estimated Damage (₱)</label>
                            <input type="number" class="form-control" id="estimated_damage" name="estimated_damage" min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="establishments_affected">Establishments Affected</label>
                            <input type="number" class="form-control" id="establishments_affected" name="establishments_affected" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="estimated_area_sqm">Estimated Area (sqm)</label>
                            <input type="number" class="form-control" id="estimated_area_sqm" name="estimated_area_sqm" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="damage_computation">Damage Computation (₱)</label>
                            <input type="number" class="form-control" id="damage_computation" name="damage_computation" min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                </div>
                
                <!-- Fire Details -->
                <div class="form-section">
                    <h3 class="section-title">Fire Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="time_fire_started">Time Fire Started <span class="required">*</span></label>
                            <input type="datetime-local" class="form-control" id="time_fire_started" name="time_fire_started" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="time_fire_out">Time Fire Out</label>
                            <input type="datetime-local" class="form-control" id="time_fire_out" name="time_fire_out">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="highest_alarm_level">Highest Alarm Level</label>
                            <select class="form-control" id="highest_alarm_level" name="highest_alarm_level">
                                <option value="">Select Level</option>
                                <option value="1st Alarm">1st Alarm</option>
                                <option value="2nd Alarm">2nd Alarm</option>
                                <option value="3rd Alarm">3rd Alarm</option>
                                <option value="4th Alarm">4th Alarm</option>
                                <option value="5th Alarm">5th Alarm</option>
                                <option value="General Alarm">General Alarm</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="form-section">
                    <h3 class="section-title">Additional Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="involved">Persons Involved <span class="required">*</span></label>
                            <textarea class="form-control" id="involved" name="involved" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="location_of_fatalities">Location of Fatalities</label>
                            <textarea class="form-control" id="location_of_fatalities" name="location_of_fatalities" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="weather_condition">Weather Condition</label>
                            <input type="text" class="form-control" id="weather_condition" name="weather_condition">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="other_info">Other Information</label>
                            <textarea class="form-control" id="other_info" name="other_info" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label class="form-label" for="disposition">Disposition</label>
                            <textarea class="form-control" id="disposition" name="disposition" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Investigator Information -->
                <div class="form-section">
                    <h3 class="section-title">Investigator Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="investigator_name">Investigator Name <span class="required">*</span></label>
                            <input type="text" class="form-control" id="investigator_name" name="investigator_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="investigator_signature">Investigator Signature</label>
                            <input type="text" class="form-control" id="investigator_signature" name="investigator_signature">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="turned_over" value="1"> Report Turned Over
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary me-3">Cancel</a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Create Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Set current date as default for date_completed (Philippines timezone)
        const now = new Date();
        const philippinesOffset = 8 * 60; // UTC+8 in minutes
        const philippinesTime = new Date(now.getTime() + (philippinesOffset * 60 * 1000));
        document.getElementById('date_completed').valueAsDate = philippinesTime;
        
        // Form validation
        document.getElementById('spotReportForm').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = document.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                Swal.fire({
                    title: 'Validation Error',
                    text: 'Please fill in all required fields.',
                    icon: 'error'
                });
            }
        });
        
        // Remove invalid class on input
        document.querySelectorAll('.form-control').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    </script>
     <!-- Include header components -->
 <?php include '../../../../components/scripts.php'; ?>
</body>
</html>
