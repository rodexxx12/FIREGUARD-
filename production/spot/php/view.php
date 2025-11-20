<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../db/db.php';

// Check if user is logged in BEFORE including header.php
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../components/header.php';

$conn = getDatabaseConnection();

// Get report ID from URL
$report_id = $_GET['id'] ?? null;

if (!$report_id) {
    header('Location: index.php');
    exit();
}

// Check if this is a fire_data ID or spot report ID
$stmt = $conn->prepare("SELECT * FROM spot_investigation_reports WHERE id = ?");
$stmt->bindParam(1, $report_id, PDO::PARAM_INT);
$stmt->execute();
$report = $stmt->fetch();

if ($report) {
    // This is a spot report
    $viewType = 'spot_report';
    $fireDataId = $report['fire_data_id'];
    $reportStatus = $report['reports_status'];
} else {
    // Check if this is a fire_data ID
    $statusAck = 'ACKNOWLEDGED';
    $stmt = $conn->prepare("
        SELECT fd.*, 
               b.building_name,
               b.address as building_address,
               u.username as user_name,
               br.barangay_name
        FROM fire_data fd
        LEFT JOIN buildings b ON fd.building_id = b.id
        LEFT JOIN users u ON fd.user_id = u.user_id
        LEFT JOIN barangay br ON fd.barangay_id = br.id
        WHERE fd.id = ? AND fd.status = ?
    ");
    $stmt->bindParam(1, $report_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $statusAck, PDO::PARAM_STR);
    $stmt->execute();
    $fireData = $stmt->fetch();
    
    if (!$fireData) {
        header('Location: index.php');
        exit();
    }
    
    $viewType = 'fire_data';
    $fireDataId = $fireData['id'];
    $reportStatus = 'no_report';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $viewType === 'spot_report' ? 'Spot Investigation Report (SIR)' : 'Fire Incident Data'; ?> #<?php echo str_pad($report_id, 4, '0', STR_PAD_LEFT); ?> - Fire Detection System</title>
    <link rel="stylesheet" href="../css/spot.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #fafafa;
            color: #333 !important;
            line-height: 1.25;
            font-size: 11px;
        }
        
        * {
            color: #333 !important;
        }
        
        .main-container {
            max-width: 8.5in;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            padding: 0.2in;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-radius: 2px;
        }
        
        /* Official Header Section */
        .official-header {
            text-align: center;
            padding: 6px 0;
            border-bottom: 1px solid #666 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            background: white;
        }
        
        .logo-row {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 6px;
            gap: 8px;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            flex-shrink: 0;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
        }
        
        .center-logo {
            width: 55px;
            height: 55px;
            object-fit: contain;
            flex-shrink: 0;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
        }
        
        .official-text {
            margin-bottom: 4px;
        }
        
        .bagong-pilipinas {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 1px;
        }
        
        .republic-philippines {
            font-size: 10px;
            margin-bottom: 1px;
        }
        
        .department-name {
            font-size: 10px;
            margin-bottom: 1px;
        }
        
        .bfp-headquarters {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 1px;
        }
        
        .address {
            font-size: 8px;
        }
        
        .letterhead-placeholder {
            font-size: 8px;
            font-style: italic;
            margin-top: 2px;
        }
        
        /* Memorandum Section */
        .memorandum-section {
            margin: 8px 0;
            padding: 0 20px;
            background: white;
            border-radius: 3px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
            padding: 8px 20px;
        }
        
        .memorandum-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .memorandum-info {
            margin-bottom: 2px;
        }
        
        .memorandum-label {
            font-weight: 600;
            display: inline-block;
            width: 100px;
        }
        
        /* Report Content */
        .report-content {
            padding: 0 20px 20px;
            background: white;
            border-radius: 3px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
            padding: 10px 20px;
        }
        
        .incident-details {
            margin-bottom: 8px;
            background: white;
            padding: 6px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 2px;
            align-items: flex-start;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 200px;
            margin-right: 10px;
            color: #444;
        }
        
        .detail-value {
            flex: 1;
            padding: 2px 0;
            color: #555;
        }
        
        .casualty-section {
            margin: 6px 0;
            background: white;
            padding: 4px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .casualty-row {
            display: flex;
            margin-bottom: 2px;
            align-items: flex-start;
        }
        
        .casualty-label {
            font-weight: 600;
            min-width: 200px;
            margin-right: 10px;
            color: #444;
        }
        
        .casualty-subsection {
            margin-left: 15px;
        }
        
        .casualty-subrow {
            display: flex;
            margin-bottom: 1px;
        }
        
        .casualty-sublabel {
            font-weight: 600;
            min-width: 100px;
            margin-right: 10px;
            color: #444;
        }
        
        /* Investigation Details */
        .investigation-section {
            margin: 8px 0;
            background: white;
            padding: 6px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
            text-decoration: underline;
            color: #444;
        }
        
        .section-subtitle {
            font-size: 10px;
            font-weight: 600;
            margin-bottom: 2px;
            color: #555;
        }
        
        .bullet-list {
            margin-left: 15px;
        }
        
        .bullet-item {
            margin-bottom: 2px;
            color: #555;
        }
        
        .sub-bullet-list {
            margin-left: 15px;
            margin-top: 1px;
        }
        
        .sub-bullet-item {
            margin-bottom: 1px;
            color: #555;
        }
        
        /* Disposition Section */
        .disposition-section {
            margin: 8px 0;
            background: white;
            padding: 6px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        /* Signature Section */
        .signature-section {
            margin-top: 10px;
            text-align: right;
            padding-right: 20px;
            background: white;
            padding: 8px 20px;
            border-radius: 3px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .signature-line {
            border-bottom: 1px solid #666 !important;
            width: 180px;
            margin-left: auto;
            margin-bottom: 2px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .signature-label {
            font-size: 8px;
            font-style: italic;
            color: #555;
        }
        
        /* Document Control */
        .document-control {
            position: fixed;
            bottom: 8px;
            left: 8px;
            font-size: 7px;
            color: #666;
        }
        
        /* Action Buttons */
        .action-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 1000;
        }
        
        .print-button:hover {
            background-color: #c82333 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3) !important;
            color: white !important;
        }
        
        .close-button:hover {
            background-color: #0056b3 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3) !important;
            color: white !important;
        }
        
        
        /* Print Styles */
        @media print {
            .main-container {
                max-width: none;
                margin: 0;
                padding: 0.15in;
                box-shadow: none;
                border-radius: 0;
            }
            
            .official-header {
                box-shadow: none;
                background: white;
                padding: 4px 0;
            }
            
            .memorandum-section,
            .report-content,
            .incident-details,
            .casualty-section,
            .investigation-section,
            .disposition-section,
            .signature-section {
                box-shadow: none;
                background: white;
            }
            
            .logo,
            .center-logo {
                filter: none;
            }
            
            body {
                font-size: 9px;
                line-height: 1.1;
            }
            
            .logo {
                width: 35px;
                height: 35px;
            }
            
            .center-logo {
                width: 45px;
                height: 45px;
            }
            
            .bagong-pilipinas {
                font-size: 11px;
            }
            
            .republic-philippines,
            .department-name {
                font-size: 8px;
            }
            
            .bfp-headquarters {
                font-size: 9px;
            }
            
            .address {
                font-size: 6px;
            }
            
            .memorandum-section {
                margin: 6px 0;
                padding: 0 10px;
            }
            
            .report-content {
                padding: 0 10px 10px;
            }
            
            .detail-row,
            .casualty-row {
                margin-bottom: 1px;
            }
            
            .investigation-section,
            .disposition-section {
                margin: 6px 0;
            }
            
            .signature-section {
                margin-top: 8px;
                padding-right: 10px;
            }
            
            .signature-line {
                width: 160px;
            }
            
            .document-control {
                position: static;
                margin-top: 10px;
                text-align: left;
                font-size: 5px;
            }
            
            .action-buttons {
                display: none !important;
            }
            
            button.print-button,
            button.close-button {
                display: none !important;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .logo-row {
                flex-direction: row;
                gap: 10px;
                justify-content: center;
            }
            
            .logo {
                width: 60px;
                height: 60px;
            }
            
            .center-logo {
                width: 80px;
                height: 80px;
            }
            
            .memorandum-section,
            .report-content {
                padding: 0 15px;
            }
            
            .logo {
                width: 50px;
                height: 50px;
            }
            
            .center-logo {
                width: 70px;
                height: 70px;
            }
            
            .detail-row,
            .casualty-row {
                flex-direction: column;
            }
            
            .detail-label,
            .casualty-label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Official BFP Header -->
        <div class="official-header">
            <div class="logo-row">
                <img src="bfplogo.png" alt="Bureau of Fire Protection" class="logo">
                <img src="philippine.png" alt="Republic of the Philippines" class="center-logo">
                <img src="fireguardlogo.png" alt="Fire Guard" class="logo">
        </div>
        
            <div class="official-text">
                <div class="bagong-pilipinas">BAGONG PILIPINAS</div>
                <div class="republic-philippines">Republic of the Philippines</div>
                <div class="department-name">Department of the Interior and Local Government</div>
                <div class="bfp-headquarters">BUREAU OF FIRE PROTECTION NATIONAL HEADQUARTERS</div>
                <div class="address">Senator Miriam Defensor-Santiago Avenue, Brgy. Bagong Pag-asa, Quezon City</div>
            </div>
            
            <div class="letterhead-placeholder">(Regional/Provincial/District/City/Municipal Letterhead)</div>
        </div>
        
        <!-- Memorandum Section -->
        <div class="memorandum-section">
            <div class="memorandum-title">MEMORANDUM</div>
            <div class="memorandum-info">
                <span class="memorandum-label">FOR :</span> 
                <?php echo $viewType === 'spot_report' ? htmlspecialchars($report['report_for']) : 'Fire Incident Investigation'; ?>
            </div>
            <div class="memorandum-info">
                <span class="memorandum-label">SUBJECT :</span> 
                <strong>Spot Investigation Report (SIR)</strong>
            </div>
            <div class="memorandum-info">
                <span class="memorandum-label">DATE :</span> 
                <?php echo $viewType === 'spot_report' ? date('F d, Y', strtotime($report['date_completed'])) : date('F d, Y'); ?>
            </div>
            </div>
            
        <!-- Report Content -->
            <div class="report-content">
                <?php if ($viewType === 'spot_report'): ?>
                    <!-- Spot Report Content -->
                <div class="incident-details">
                    <div class="detail-row">
                        <div class="detail-label">DTPO :</div>
                        <div class="detail-value"><?php echo date('F d, Y', strtotime($report['date_occurrence'])); ?> at <?php echo date('g:i A', strtotime($report['time_occurrence'])); ?>, <?php echo htmlspecialchars($report['place_occurrence']); ?></div>
                                </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">INVOLVED :</div>
                        <div class="detail-value"><?php echo htmlspecialchars($report['establishment_name']); ?></div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">NAME OF ESTABLISHMENT :</div>
                        <div class="detail-value"><?php echo htmlspecialchars($report['establishment_name']); ?></div>
                                </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">OWNER :</div>
                        <div class="detail-value"><?php echo htmlspecialchars($report['owner']); ?></div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">OCCUPANT :</div>
                        <div class="detail-value"><?php echo !empty($report['occupant']) ? htmlspecialchars($report['occupant']) : 'Not specified'; ?></div>
                            </div>
                    
                    <div class="casualty-section">
                        <div class="casualty-row">
                            <div class="casualty-label">CASUALTY</div>
                            <div class="casualty-subsection">
                                <div class="casualty-subrow">
                                    <div class="casualty-sublabel">Fatality :</div>
                                    <div class="detail-value"><?php echo $report['fatalities']; ?> person(s) who died</div>
                                </div>
                                <div class="casualty-subrow">
                                    <div class="casualty-sublabel">Injured :</div>
                                    <div class="detail-value"><?php echo $report['injured']; ?> person(s) who are injured</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Horizontal separator line -->
                    <div style="border-bottom: 1px solid #ccc !important; margin: 3px 0;"></div>
                    
                    <div class="detail-row">
                        <div class="detail-label">ESTIMATED DAMAGE :</div>
                        <div class="detail-value">₱<?php echo number_format($report['estimated_damage'], 2); ?></div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">TIME FIRE STARTED :</div>
                        <div class="detail-value"><?php echo date('g:i A', strtotime($report['time_occurrence'])); ?></div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">TIME OF FIRE OUT :</div>
                        <div class="detail-value"><?php echo !empty($report['time_fire_out']) ? date('g:i A', strtotime($report['time_fire_out'])) : 'Not specified'; ?></div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">ALARM :</div>
                        <div class="detail-value"><?php echo !empty($report['alarm_level']) ? htmlspecialchars($report['alarm_level']) : 'Not specified'; ?></div>
                        </div>
                    </div>
                    
                <!-- Details of Investigation -->
                <div class="investigation-section">
                    <div class="section-title">DETAILS OF INVESTIGATION:</div>
                    <div class="section-subtitle">This section should contain:</div>
                    <div class="bullet-list">
                        <div class="bullet-item">A complete narration of the details of the fire incident as gathered by the Fire Arson Investigator (FAI) during actual response. Details shall include, but are not limited, to the following:</div>
                        <div class="sub-bullet-list">
                            <div class="sub-bullet-item">a) Number of establishments and / or affected establishments</div>
                            <div class="sub-bullet-item">b) Estimated area in square meters and the estimated amount of damage based on the computation in the 2015 BFP Operational Procedures Manual</div>
                            <div class="sub-bullet-item">c) Location of fatalities and initial details as to identity</div>
                            <div class="sub-bullet-item">d) Weather condition</div>
                            </div>
                        <div class="bullet-item">Other initial information about the involved establishment and the fire incident</div>
                            </div>
                    
                    <?php if (!empty($report['investigation_details'])): ?>
                        <div style="margin-top: 8px; padding: 6px; border: 1px solid #ddd !important; background-color: white; border-radius: 3px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);">
                            <strong>Investigation Details:</strong><br>
                            <?php echo nl2br(htmlspecialchars($report['investigation_details'])); ?>
                            </div>
                    <?php endif; ?>
                        </div>
                
                <!-- Disposition -->
                <div class="disposition-section">
                    <div class="section-title">DISPOSITION:</div>
                    <div class="section-subtitle">This section should contain:</div>
                    <div class="bullet-list">
                        <div class="bullet-item">The disposition and assessment of the FAI regarding the case.</div>
                        <div class="bullet-item">May also contain whether the case will be turned over to the higher office.</div>
                    </div>
                    
                    <?php if (!empty($report['disposition'])): ?>
                        <div style="margin-top: 8px; padding: 6px; border: 1px solid #ddd !important; background-color: white; border-radius: 3px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);">
                            <strong>Disposition:</strong><br>
                            <?php echo nl2br(htmlspecialchars($report['disposition'])); ?>
                            </div>
                    <?php endif; ?>
                                </div>
                
                <!-- Signature -->
                <div class="signature-section">
                    <div class="signature-line"></div>
                    <div class="signature-label">(Name and signature of the FAI)</div>
                    <?php if (!empty($report['investigator_name'])): ?>
                        <div style="margin-top: 3px; font-weight: bold;"><?php echo htmlspecialchars($report['investigator_name']); ?></div>
                    <?php endif; ?>
                            </div>
                
                <?php else: ?>
                    <!-- Fire Data Content -->
                <div class="incident-details">
                    <div class="detail-row">
                        <div class="detail-label">DTPO :</div>
                        <div class="detail-value"><?php echo date('F d, Y', strtotime($fireData['timestamp'])); ?> at <?php echo date('g:i A', strtotime($fireData['timestamp'])); ?>, <?php echo htmlspecialchars($fireData['building_name'] ?? 'Unknown Location'); ?></div>
                                </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">INVOLVED :</div>
                        <div class="detail-value"><?php echo htmlspecialchars($fireData['building_type']); ?> - <?php echo htmlspecialchars($fireData['building_name'] ?? 'Unknown'); ?></div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">NAME OF ESTABLISHMENT :</div>
                        <div class="detail-value"><?php echo htmlspecialchars($fireData['building_name'] ?? 'Unknown'); ?></div>
                                </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">OWNER :</div>
                        <div class="detail-value">Not specified</div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">OCCUPANT :</div>
                        <div class="detail-value">Not specified</div>
                                </div>
                    
                    <div class="casualty-section">
                        <div class="casualty-row">
                            <div class="casualty-label">CASUALTY</div>
                            <div class="casualty-subsection">
                                <div class="casualty-subrow">
                                    <div class="casualty-sublabel">Fatality :</div>
                                    <div class="detail-value">0 person(s) who died</div>
                                </div>
                                <div class="casualty-subrow">
                                    <div class="casualty-sublabel">Injured :</div>
                                    <div class="detail-value">0 person(s) who are injured</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Horizontal separator line -->
                    <div style="border-bottom: 1px solid #ccc !important; margin: 3px 0;"></div>
                    
                    <div class="detail-row">
                        <div class="detail-label">ESTIMATED DAMAGE :</div>
                        <div class="detail-value">To be determined</div>
                                </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">TIME FIRE STARTED :</div>
                        <div class="detail-value"><?php echo date('g:i A', strtotime($fireData['timestamp'])); ?></div>
                            </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">TIME OF FIRE OUT :</div>
                        <div class="detail-value">Not applicable</div>
                                </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">ALARM :</div>
                        <div class="detail-value">Sensor Alert Level</div>
                                </div>
                            </div>
                
                <!-- Sensor Data Details -->
                <div class="investigation-section">
                    <div class="section-title">SENSOR DATA ANALYSIS:</div>
                    <div class="section-subtitle">Fire Detection System Data:</div>
                    <div class="bullet-list">
                        <div class="bullet-item">Smoke Level: <?php echo $fireData['smoke']; ?> (<?php echo $fireData['smoke'] > 50 ? 'High' : ($fireData['smoke'] > 25 ? 'Medium' : 'Low'); ?>)</div>
                        <div class="bullet-item">Temperature: <?php echo $fireData['temp']; ?>°C (<?php echo $fireData['temp'] > 50 ? 'High' : ($fireData['temp'] > 30 ? 'Medium' : 'Normal'); ?>)</div>
                        <div class="bullet-item">Heat Level: <?php echo $fireData['heat']; ?> (<?php echo $fireData['heat'] > 50 ? 'High' : ($fireData['heat'] > 25 ? 'Medium' : 'Low'); ?>)</div>
                        <div class="bullet-item">Flame Detected: <?php echo $fireData['flame_detected'] ? 'Yes' : 'No'; ?></div>
                        <div class="bullet-item">ML Confidence: <?php echo number_format($fireData['ml_confidence'], 1); ?>%</div>
                        <div class="bullet-item">AI Prediction: <?php echo htmlspecialchars($fireData['ai_prediction'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    
                <!-- Disposition -->
                <div class="disposition-section">
                    <div class="section-title">DISPOSITION:</div>
                    <div class="section-subtitle">System Assessment:</div>
                    <div class="bullet-list">
                        <div class="bullet-item">Fire detection system activated and incident acknowledged.</div>
                        <div class="bullet-item">Sensor data indicates <?php echo $fireData['ml_prediction'] ? 'potential fire condition' : 'normal conditions'; ?>.</div>
                        <div class="bullet-item">Further investigation required by Fire Arson Investigator.</div>
                        </div>
                    </div>
                    
                <!-- Signature -->
                <div class="signature-section">
                    <div class="signature-line"></div>
                    <div class="signature-label">(Name and signature of the FAI)</div>
                    </div>
                <?php endif; ?>
            </div>
        
        <!-- Document Control -->
        <div class="document-control">
            BFP-QSF-FAID-002 Rev. 02 (02.03.25) Page 1 of 2
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons" style="position: fixed; bottom: 30px; right: 30px; display: flex; gap: 15px; z-index: 1000;">
        <button class="print-button" onclick="window.print()" title="Print Report" style="background-color: #dc3545; border: none; padding: 8px 12px; border-radius: 4px; color: white; font-size: 0.9rem; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2); transition: all 0.3s ease;">
            🖨️ Print
        </button>
        <button class="close-button" onclick="window.location.href='final_reports.php'" title="Close Report" style="background-color: #007bff; border: none; padding: 8px 12px; border-radius: 4px; color: white; font-size: 0.9rem; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2); transition: all 0.3s ease;">
            ✕ Close
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
