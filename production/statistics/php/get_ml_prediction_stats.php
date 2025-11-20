<?php
require_once '../../../db/db.php';

header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    
    // Get filter parameters
    $barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $mlConfidence = isset($_GET['ml_confidence']) ? $_GET['ml_confidence'] : '';
    
    // Build the base query for ML prediction analysis
    $sql = "SELECT 
                fd.id,
                fd.status,
                fd.ml_prediction,
                fd.ml_confidence,
                fd.ml_fire_probability,
                fd.ml_timestamp,
                fd.timestamp,
                fd.temp,
                fd.heat,
                fd.smoke,
                fd.flame_detected,
                fd.building_type,
                COALESCE(b.barangay_name, 'Unknown') as barangay_name,
                CASE 
                    WHEN fd.status IN ('EMERGENCY', 'ACKNOWLEDGED') THEN 1
                    ELSE 0
                END as actual_fire,
                CASE 
                    WHEN fd.ml_prediction = 1 AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED') THEN 'True Positive'
                    WHEN fd.ml_prediction = 1 AND fd.status NOT IN ('EMERGENCY', 'ACKNOWLEDGED') THEN 'False Positive'
                    WHEN fd.ml_prediction = 0 AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED') THEN 'False Negative'
                    WHEN fd.ml_prediction = 0 AND fd.status NOT IN ('EMERGENCY', 'ACKNOWLEDGED') THEN 'True Negative'
                    ELSE 'Unknown'
                END as prediction_result
            FROM fire_data fd
            LEFT JOIN barangay b ON fd.barangay_id = b.id
            WHERE fd.timestamp IS NOT NULL 
            AND fd.timestamp != ''
            AND fd.ml_timestamp IS NOT NULL
            AND fd.ml_prediction IS NOT NULL";
    
    $params = [];
    
    // Add barangay filter
    if (!empty($barangay)) {
        $sql .= " AND fd.barangay_id = :barangay";
        $params[':barangay'] = $barangay;
    }
    
    // Add date filters (handle varchar timestamp)
    if (!empty($startDate)) {
        $sql .= " AND DATE(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) >= :start_date";
        $params[':start_date'] = $startDate;
    }
    
    if (!empty($endDate)) {
        $sql .= " AND DATE(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    
    // Add ML confidence filter
    if (!empty($mlConfidence)) {
        $sql .= " AND fd.ml_confidence >= :ml_confidence";
        $params[':ml_confidence'] = $mlConfidence;
    }
    
    $sql .= " ORDER BY fd.ml_timestamp DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Calculate ML performance metrics
    $totalPredictions = count($results);
    $truePositives = 0;
    $falsePositives = 0;
    $trueNegatives = 0;
    $falseNegatives = 0;
    
    $confidenceRanges = [
        '0-20' => 0,
        '21-40' => 0,
        '41-60' => 0,
        '61-80' => 0,
        '81-100' => 0
    ];
    
    $predictionTimeline = [];
    $barangayAccuracy = [];
    
    foreach ($results as $row) {
        // Count prediction results
        switch ($row['prediction_result']) {
            case 'True Positive':
                $truePositives++;
                break;
            case 'False Positive':
                $falsePositives++;
                break;
            case 'True Negative':
                $trueNegatives++;
                break;
            case 'False Negative':
                $falseNegatives++;
                break;
        }
        
        // Count confidence ranges
        $confidence = (float)$row['ml_confidence'];
        if ($confidence >= 0 && $confidence <= 20) {
            $confidenceRanges['0-20']++;
        } elseif ($confidence >= 21 && $confidence <= 40) {
            $confidenceRanges['21-40']++;
        } elseif ($confidence >= 41 && $confidence <= 60) {
            $confidenceRanges['41-60']++;
        } elseif ($confidence >= 61 && $confidence <= 80) {
            $confidenceRanges['61-80']++;
        } elseif ($confidence >= 81 && $confidence <= 100) {
            $confidenceRanges['81-100']++;
        }
        
        // Build timeline data
        $date = date('Y-m-d', strtotime($row['ml_timestamp']));
        if (!isset($predictionTimeline[$date])) {
            $predictionTimeline[$date] = [
                'fire_predicted' => 0,
                'no_fire_predicted' => 0,
                'actual_fires' => 0,
                'actual_no_fires' => 0
            ];
        }
        
        if ($row['ml_prediction'] == 1) {
            $predictionTimeline[$date]['fire_predicted']++;
        } else {
            $predictionTimeline[$date]['no_fire_predicted']++;
        }
        
        if ($row['actual_fire'] == 1) {
            $predictionTimeline[$date]['actual_fires']++;
        } else {
            $predictionTimeline[$date]['actual_no_fires']++;
        }
        
        // Build barangay accuracy data
        $barangayName = $row['barangay_name'];
        if (!isset($barangayAccuracy[$barangayName])) {
            $barangayAccuracy[$barangayName] = [
                'total_predictions' => 0,
                'correct_predictions' => 0,
                'fire_predicted' => 0,
                'actual_fires' => 0
            ];
        }
        
        $barangayAccuracy[$barangayName]['total_predictions']++;
        if ($row['prediction_result'] == 'True Positive' || $row['prediction_result'] == 'True Negative') {
            $barangayAccuracy[$barangayName]['correct_predictions']++;
        }
        
        if ($row['ml_prediction'] == 1) {
            $barangayAccuracy[$barangayName]['fire_predicted']++;
        }
        
        if ($row['actual_fire'] == 1) {
            $barangayAccuracy[$barangayName]['actual_fires']++;
        }
    }
    
    // Calculate accuracy metrics
    $precision = ($truePositives + $falsePositives) > 0 ? ($truePositives / ($truePositives + $falsePositives)) * 100 : 0;
    $recall = ($truePositives + $falseNegatives) > 0 ? ($truePositives / ($truePositives + $falseNegatives)) * 100 : 0;
    $accuracy = $totalPredictions > 0 ? (($truePositives + $trueNegatives) / $totalPredictions) * 100 : 0;
    $f1Score = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0;
    
    // Format timeline data
    $timelineData = [];
    $timelineLabels = [];
    foreach ($predictionTimeline as $date => $data) {
        $timelineLabels[] = date('M j', strtotime($date));
        $timelineData['fire_predicted'][] = $data['fire_predicted'];
        $timelineData['no_fire_predicted'][] = $data['no_fire_predicted'];
        $timelineData['actual_fires'][] = $data['actual_fires'];
        $timelineData['actual_no_fires'][] = $data['actual_no_fires'];
    }
    
    // Format barangay accuracy data
    $barangayData = [];
    $barangayLabels = [];
    foreach ($barangayAccuracy as $barangayName => $data) {
        $barangayLabels[] = $barangayName;
        $accuracy = $data['total_predictions'] > 0 ? ($data['correct_predictions'] / $data['total_predictions']) * 100 : 0;
        $barangayData['accuracy'][] = round($accuracy, 2);
        $barangayData['total_predictions'][] = $data['total_predictions'];
        $barangayData['fire_predicted'][] = $data['fire_predicted'];
        $barangayData['actual_fires'][] = $data['actual_fires'];
    }
    
    // Prepare response data
    $responseData = [
        'summary' => [
            'total_predictions' => $totalPredictions,
            'true_positives' => $truePositives,
            'false_positives' => $falsePositives,
            'true_negatives' => $trueNegatives,
            'false_negatives' => $falseNegatives,
            'accuracy' => round($accuracy, 2),
            'precision' => round($precision, 2),
            'recall' => round($recall, 2),
            'f1_score' => round($f1Score, 2)
        ],
        'confidence_distribution' => [
            'labels' => array_keys($confidenceRanges),
            'data' => array_values($confidenceRanges)
        ],
        'timeline' => [
            'labels' => $timelineLabels,
            'fire_predicted' => $timelineData['fire_predicted'] ?? [],
            'no_fire_predicted' => $timelineData['no_fire_predicted'] ?? [],
            'actual_fires' => $timelineData['actual_fires'] ?? [],
            'actual_no_fires' => $timelineData['actual_no_fires'] ?? []
        ],
        'barangay_accuracy' => [
            'labels' => $barangayLabels,
            'accuracy' => $barangayData['accuracy'] ?? [],
            'total_predictions' => $barangayData['total_predictions'] ?? [],
            'fire_predicted' => $barangayData['fire_predicted'] ?? [],
            'actual_fires' => $barangayData['actual_fires'] ?? []
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $responseData,
        'message' => 'ML prediction statistics loaded successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_ml_prediction_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load ML prediction statistics',
        'error' => $e->getMessage()
    ]);
}
?>
