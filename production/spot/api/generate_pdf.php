<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../db/db.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../index.php');
    exit();
}

// Get report ID from URL
$report_id = $_GET['id'] ?? null;

if (!$report_id) {
    header('Location: ../php/index.php');
    exit();
}

$conn = getDatabaseConnection();

// Get the report details
$stmt = $conn->prepare("
    SELECT sir.*, 
           fd.smoke, fd.temp, fd.heat, fd.flame_detected, fd.ml_confidence, fd.ml_prediction, fd.ai_prediction,
           b.building_name, b.address as building_address, b.building_type,
           u.username as user_name,
           br.barangay_name
    FROM spot_investigation_reports sir
    LEFT JOIN fire_data fd ON sir.fire_data_id = fd.id
    LEFT JOIN buildings b ON fd.building_id = b.id
    LEFT JOIN users u ON fd.user_id = u.user_id
    LEFT JOIN barangay br ON fd.barangay_id = br.id
    WHERE sir.id = ?
");
$stmt->bindParam(1, $report_id, PDO::PARAM_INT);
$stmt->execute();
$report = $stmt->fetch();

if (!$report) {
    header('Location: ../php/index.php');
    exit();
}

// Check if report status is final
if ($report['reports_status'] !== 'final') {
    header('Location: ../php/view.php?id=' . $report_id);
    exit();
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="SIR-' . str_pad($report['id'], 4, '0', STR_PAD_LEFT) . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Generate HTML content for PDF
$html = generateReportHTML($report);

// For now, we'll output HTML that can be printed to PDF
// In a production environment, you would use a library like TCPDF or mPDF
echo $html;

function generateReportHTML($report) {
    $dateOccurrence = date('F d, Y', strtotime($report['date_occurrence']));
    $timeOccurrence = date('g:i A', strtotime($report['time_occurrence']));
    $dateCompleted = date('F d, Y', strtotime($report['date_completed']));
    
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Spot Investigation Report #' . str_pad($report['id'], 4, '0', STR_PAD_LEFT) . '</title>
        <style>
            body {
                font-family: "Times New Roman", serif;
                margin: 0;
                padding: 20px;
                background: white;
                color: #000;
                line-height: 1.6;
                font-size: 12px;
            }
            .report-header {
                text-align: center;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .report-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .report-subtitle {
                font-size: 14px;
                margin-bottom: 5px;
            }
            .report-content {
                margin-bottom: 30px;
            }
            .section-title {
                font-size: 14px;
                font-weight: bold;
                margin: 20px 0 10px 0;
                text-decoration: underline;
            }
            .detail-row {
                margin-bottom: 8px;
                display: flex;
            }
            .detail-label {
                font-weight: bold;
                min-width: 200px;
            }
            .detail-value {
                flex: 1;
            }
            .signature-section {
                margin-top: 50px;
                text-align: right;
            }
            .signature-line {
                border-bottom: 1px solid #000;
                width: 300px;
                margin-left: auto;
                margin-bottom: 5px;
            }
            .footer {
                margin-top: 50px;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
            @media print {
                body { margin: 0; padding: 15px; }
            }
        </style>
    </head>
    <body>
        <div class="report-header">
            <div class="report-title">REPUBLIC OF THE PHILIPPINES</div>
            <div class="report-subtitle">DEPARTMENT OF THE INTERIOR AND LOCAL GOVERNMENT</div>
            <div class="report-subtitle">BUREAU OF FIRE PROTECTION</div>
            <div class="report-subtitle">NATIONAL HEADQUARTERS</div>
            <div style="margin-top: 15px; font-size: 16px; font-weight: bold;">
                SPOT INVESTIGATION REPORT (SIR)
            </div>
            <div style="margin-top: 10px;">
                Report No: SIR-' . str_pad($report['id'], 4, '0', STR_PAD_LEFT) . '
            </div>
            <div>
                Generated: ' . date('F d, Y \a\t g:i A') . '
            </div>
        </div>
        
        <div class="report-content">
            <div class="section-title">MEMORANDUM</div>
            <div class="detail-row">
                <div class="detail-label">FOR:</div>
                <div class="detail-value">' . htmlspecialchars($report['report_for']) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">SUBJECT:</div>
                <div class="detail-value">' . htmlspecialchars($report['subject']) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">DATE:</div>
                <div class="detail-value">' . $dateCompleted . '</div>
            </div>
            
            <div class="section-title">INCIDENT DETAILS</div>
            <div class="detail-row">
                <div class="detail-label">Date & Time of Occurrence:</div>
                <div class="detail-value">' . $dateOccurrence . ' at ' . $timeOccurrence . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Location:</div>
                <div class="detail-value">' . htmlspecialchars($report['place_occurrence']) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Establishment:</div>
                <div class="detail-value">' . htmlspecialchars($report['establishment_name']) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Property Owner:</div>
                <div class="detail-value">' . htmlspecialchars($report['owner']) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Occupant:</div>
                <div class="detail-value">' . (!empty($report['occupant']) ? htmlspecialchars($report['occupant']) : 'Not specified') . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Fatalities:</div>
                <div class="detail-value">' . $report['fatalities'] . ' person(s)</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Injured:</div>
                <div class="detail-value">' . $report['injured'] . ' person(s)</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Estimated Damage:</div>
                <div class="detail-value">₱' . number_format($report['estimated_damage'], 2) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Alarm Level:</div>
                <div class="detail-value">' . (!empty($report['highest_alarm_level']) ? htmlspecialchars($report['highest_alarm_level']) : 'Not specified') . '</div>
            </div>
            
            <div class="section-title">INVESTIGATION FINDINGS</div>';
    
    if (!empty($report['investigation_details'])) {
        $html .= '<div class="detail-row">
            <div class="detail-label">Investigation Details:</div>
            <div class="detail-value">' . nl2br(htmlspecialchars($report['investigation_details'])) . '</div>
        </div>';
    }
    
    if (!empty($report['weather_condition'])) {
        $html .= '<div class="detail-row">
            <div class="detail-label">Weather Conditions:</div>
            <div class="detail-value">' . htmlspecialchars($report['weather_condition']) . '</div>
        </div>';
    }
    
    $html .= '<div class="detail-row">
        <div class="detail-label">Properties Affected:</div>
        <div class="detail-value">' . $report['establishments_affected'] . ' establishment(s)</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">Area Affected:</div>
        <div class="detail-value">' . $report['estimated_area_sqm'] . ' square meters</div>
    </div>';
    
    if (!empty($report['location_of_fatalities'])) {
        $html .= '<div class="detail-row">
            <div class="detail-label">Fatalities Location:</div>
            <div class="detail-value">' . htmlspecialchars($report['location_of_fatalities']) . '</div>
        </div>';
    }
    
    if (!empty($report['other_info'])) {
        $html .= '<div class="detail-row">
            <div class="detail-label">Additional Information:</div>
            <div class="detail-value">' . htmlspecialchars($report['other_info']) . '</div>
        </div>';
    }
    
    $html .= '<div class="section-title">DISPOSITION</div>';
    
    if (!empty($report['disposition'])) {
        $html .= '<div class="detail-row">
            <div class="detail-label">Disposition:</div>
            <div class="detail-value">' . nl2br(htmlspecialchars($report['disposition'])) . '</div>
        </div>';
    }
    
    $html .= '<div class="detail-row">
        <div class="detail-label">Investigation Status:</div>
        <div class="detail-value">Final Report - Ready for Submission</div>
    </div>
    <div class="detail-row">
        <div class="detail-label">Report Turned Over:</div>
        <div class="detail-value">' . ($report['turned_over'] ? 'Yes' : 'No') . '</div>
    </div>
</div>

<div class="signature-section">
    <div class="signature-line"></div>
    <div style="font-size: 12px; margin-top: 5px;">
        Fire Arson Investigator
    </div>
    <div style="margin-top: 10px; font-weight: bold;">
        ' . (!empty($report['investigator_name']) ? htmlspecialchars($report['investigator_name']) : 'Fire Arson Investigator') . '
    </div>
</div>

<div class="footer">
    <div>BFP-QSF-FAID-002 Rev. 02 (02.03.25)</div>
    <div>This is an official document generated by the Fire Detection System</div>
    <div>Generated on: ' . date('F d, Y \a\t g:i A') . '</div>
</div>
</body>
</html>';
    
    return $html;
}
?>
