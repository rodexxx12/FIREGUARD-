<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../db/db.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('<div class="alert alert-danger">User not logged in</div>');
}

$user_id = $_SESSION['user_id'];

// Get database connection
$pdo = getDatabaseConnection();

// Get user-specific status data
$sql = "SELECT status, building_type, smoke, temp, heat, flame_detected 
        FROM fire_data 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$result = $stmt->fetch();

$showAlert = false;
$status = "SAFE"; // Default status
$shouldRefresh = false; // Flag to determine if we should auto-refresh
$shouldSpeak = false; // Flag to determine if we should trigger TTS

if ($result) {
    $status = htmlspecialchars($result['status']);
    $building_type = htmlspecialchars($result['building_type']);
    $smoke = htmlspecialchars($result['smoke']);
    $temp = htmlspecialchars($result['temp']);
    $heat = htmlspecialchars($result['heat']);
    $flame_detected = htmlspecialchars($result['flame_detected']);
    
    // Only show alert for these statuses
    $showAlert = in_array($status, ['EMERGENCY', 'MONITORING', 'ACKNOWLEDGED']);
    // Only auto-refresh for these statuses
    $shouldRefresh = $showAlert;
    // Only speak for these statuses
    $shouldSpeak = in_array($status, ['EMERGENCY']);
} else {
    $building_type = "";
    $smoke = "";
    $temp = "";
    $heat = "";
    $flame_detected = "";
}

// Determine initial alert class
$alertClass = 'alert-warning';
if ($status === 'EMERGENCY') {
    $alertClass = 'alert-danger';
} elseif ($status === 'MONITORING') {
    $alertClass = 'alert-warning';
} elseif ($status === 'ACKNOWLEDGED') {
    $alertClass = 'alert-info';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

  <style>
  body {
    background-color: #fff7ed;
    margin: 0;
    padding: 0;
  }

  /* Light color themes */
  .alert-danger { 
    background-color: #fef2f2; 
    color: #dc2626;
    border: 1px solid #fecaca;
    animation: pulse 2s infinite;
  }
  .alert-warning { 
    background-color: #fffbeb; 
    color: #d97706;
    border: 1px solid #fed7aa;
  }
  .alert-success { 
    background-color: #f0fdf4; 
    color: #16a34a;
    border: 1px solid #bbf7d0;
  }
  .alert-info { 
    background-color: #eff6ff; 
    color: #2563eb;
    border: 1px solid #bfdbfe;
  }

  /* Simple alert styling */
  .status-alert {
    border-radius: 8px;
    margin: 0.5rem;
    padding: 1rem;
    font-size: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: white;
    flex-wrap: wrap;
  }

  .alert-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
  }

  /* Simple badge */
  .status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
    background-color: rgba(255, 255, 255, 0.9);
  }

  /* Data items */
  .data-item {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
    background-color: rgba(255, 255, 255, 0.7);
    border-radius: 4px;
    font-size: 0.9rem;
  }

  .data-item i {
    margin-right: 0.25rem;
    opacity: 0.8;
  }
  
  /* Simple buttons */
  .btn-close, .btn-stop {
    padding: 0.25rem;
    cursor: pointer;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: white;
    transition: background 0.2s ease;
  }

  .btn-close {
    color: #dc2626;
  }

  .btn-stop {
    color: #6b7280;
  }

  .btn-close:hover, .btn-stop:hover {
    background: #f9fafb;
  }
  
  /* Pulse animation */
  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
  }

  /* Alert actions */
  .alert-actions {
    display: flex;
    gap: 0.25rem;
    align-items: center;
  }

  /* Responsive design */
  @media (max-width: 768px) {
    .status-alert {
      flex-direction: column;
      align-items: stretch;
      padding: 0.75rem;
      margin: 0.25rem;
    }
    .alert-content {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }
    .data-item {
      width: 100%;
      justify-content: flex-start;
    }
    .alert-actions {
      margin-top: 0.5rem;
      justify-content: center;
    }
  }
</style>

</head>
<body>
    <!-- Bootstrap Alert with Close Button - Only shown for EMERGENCY, MONITORING, ACKNOWLEDGED -->
    <?php if ($showAlert): ?>
    <div id="fireAlert" class="alert <?php echo $alertClass; ?> status-alert mb-4">
      <div class="alert-content">
        <span class="status-badge">
          <i class="fas fa-<?php 
            echo $status === 'EMERGENCY' ? 'exclamation-triangle' : 
                 ($status === 'MONITORING' ? 'eye' : 'info-circle'); 
          ?>"></i>
          <?php echo $status; ?>
        </span>
        
        <span class="data-item">
          <i class="fas fa-building"></i>
          <?php echo $building_type ?: 'Unknown'; ?>
        </span>
        
        <span class="data-item">
          <i class="fas fa-smog"></i>
          Smoke: <?php echo $smoke ?: '0'; ?> ppm
        </span>
        
        <span class="data-item">
          <i class="fas fa-temperature-high"></i>
          Temp: <?php echo $temp ?: '0'; ?>°C
        </span>
        
        <span class="data-item">
          <i class="fas fa-fire"></i>
          Flame: <?php echo $flame_detected ? 'Detected' : 'None'; ?>
        </span>
      </div>
      <div class="alert-actions">
        <button type="button" class="btn-stop" aria-label="Stop" onclick="stopSpeech()">
          <i class="fas fa-stop"></i>
        </button>
        <button type="button" class="btn-close" aria-label="Close" onclick="dismissAlert()">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    <?php endif; ?>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // Global variable to track if speech has played
  let hasSpoken = false;
  let speechTimeout = null;

  // Speech synthesis function
  function speak(text) {
    if ('speechSynthesis' in window) {
      // Stop any ongoing speech
      window.speechSynthesis.cancel();
      
      // Create a new utterance
      const utterance = new SpeechSynthesisUtterance(text);
      
      // Set voice properties
      utterance.volume = 1; // 0 to 1
      utterance.rate = 1; // 0.1 to 10
      utterance.pitch = 1; // 0 to 2
      
      // Speak the text
      window.speechSynthesis.speak(utterance);
      hasSpoken = true;

      // Clear any existing timeout
      if (speechTimeout) {
        clearTimeout(speechTimeout);
      }

      // Set a timeout to stop speech after 10 seconds
      speechTimeout = setTimeout(() => {
        stopSpeech();
      }, 10000);
    } else {
      console.log('Text-to-speech not supported in this browser.');
    }
  }
  
  // Function to stop speech
  function stopSpeech() {
    if ('speechSynthesis' in window) {
      window.speechSynthesis.cancel();
      if (speechTimeout) {
        clearTimeout(speechTimeout);
        speechTimeout = null;
      }
    }
  }

  // Function to check if user is logged in
  function checkLoginStatus() {
    fetch('../../alarm/check_login.php')
      .then(response => response.json())
      .then(data => {
        if (!data.logged_in) {
          stopSpeech();
          window.location.href = '../../index.php'; // Redirect to login if not logged in
        }
      })
      .catch(error => console.error('Error checking login status:', error));
  }

  // Check login status periodically
  setInterval(checkLoginStatus, 5000);
  
  // Function to dismiss the alert
  function dismissAlert() {
    const alertDiv = document.getElementById('fireAlert');
    if (alertDiv) {
      alertDiv.style.display = 'none';
    }
  }

  // Function to auto-hide alert after 30 seconds
  function autoHideAlert() {
    const alertDiv = document.getElementById('fireAlert');
    if (alertDiv) {
      setTimeout(() => {
        dismissAlert();
      }, 30000); // 30 seconds
    }
  }

  // Function to update the alert status
  function updateFireStatus() {
    fetch('../../alarm/php/get_fire_status.php')
      .then(response => response.json())
      .then(data => {
        const alertDiv = document.getElementById('fireAlert');
        const showAlert = ['EMERGENCY', 'MONITORING', 'ACKNOWLEDGED'].includes(data.status);
        const shouldSpeak = ['EMERGENCY'].includes(data.status);
        
        if (!alertDiv && showAlert) {
          // Create alert div if it doesn't exist but should be shown
          createAlertDiv(data);
          return;
        }
        
        if (alertDiv) {
          if (showAlert) {
            const alertContent = alertDiv.querySelector('.alert-content');
            
            // Update content
            alertContent.innerHTML = `
              <span class="status-badge">
                <i class="fas fa-${
                  data.status === 'EMERGENCY' ? 'exclamation-triangle' : 
                  data.status === 'MONITORING' ? 'eye' : 'info-circle'
                }"></i>
                ${data.status}
              </span>
              
              <span class="data-item">
                <i class="fas fa-building"></i>
                ${data.building_type || 'Unknown'}
              </span>
              
              <span class="data-item">
                <i class="fas fa-smog"></i>
                Smoke: ${data.smoke || '0'} ppm
              </span>
              
              <span class="data-item">
                <i class="fas fa-temperature-high"></i>
                Temp: ${data.temp || '0'}°C
              </span>
              
              <span class="data-item">
                <i class="fas fa-fire"></i>
                Flame: ${data.flame_detected ? 'Detected' : 'None'}
              </span>
            `;
            
            // Update alert class based on status
            alertDiv.className = `alert ${data.status === 'EMERGENCY' ? 'alert-danger' : 
                                data.status === 'MONITORING' ? 'alert-warning' : 'alert-info'} status-alert mb-4`;
            
            // Add pulse animation for emergency
            if (data.status === 'EMERGENCY') {
              alertDiv.style.animation = 'pulse 1.5s infinite';
            } else {
              alertDiv.style.animation = 'none';
            }
            
            // Show the alert
            alertDiv.style.display = 'flex';
            
            // Start auto-hide timer
            autoHideAlert();
            
            // Trigger text-to-speech for emergency only if not spoken before
            if (shouldSpeak && !hasSpoken) {
              let speechText = '';
              if (data.status === 'EMERGENCY') {
                speechText = `Emergency! Fire detected in ${data.building_type || 'the building'}. `;
                speechText += `Smoke level is ${data.smoke || '0'} PPM. `;
                speechText += `Temperature is ${data.temp || '0'} degrees Celsius. `;
                speechText += data.flame_detected ? 'Flame detected. ' : 'No flame detected. ';
                speechText += 'Please evacuate immediately.';
                speak(speechText);
              }
            }
          } else {
            // Hide the alert and stop speech
            alertDiv.style.display = 'none';
            stopSpeech();
          }
        }
      })
      .catch(error => console.error('Error fetching fire status:', error));
  }

  // Function to create alert div if it doesn't exist
  function createAlertDiv(data) {
    const alertClass = data.status === 'EMERGENCY' ? 'alert-danger' : 
                      data.status === 'MONITORING' ? 'alert-warning' : 'alert-info';
    
    const icon = data.status === 'EMERGENCY' ? 'exclamation-triangle' : 
                data.status === 'MONITORING' ? 'eye' : 'info-circle';
    
    const alertDiv = document.createElement('div');
    alertDiv.id = 'fireAlert';
    alertDiv.className = `alert ${alertClass} status-alert mb-4`;
    alertDiv.style.display = 'flex';
    
    // Add pulse animation for emergency
    if (data.status === 'EMERGENCY') {
      alertDiv.style.animation = 'pulse 1.5s infinite';
    }
    
    alertDiv.innerHTML = `
      <div class="alert-content">
        <span class="status-badge">
          <i class="fas fa-${icon}"></i>
          ${data.status}
        </span>
        
        <span class="data-item">
          <i class="fas fa-building"></i>
          ${data.building_type || 'Unknown'}
        </span>
        
        <span class="data-item">
          <i class="fas fa-smog"></i>
          Smoke: ${data.smoke || '0'} ppm
        </span>
        
        <span class="data-item">
          <i class="fas fa-temperature-high"></i>
          Temp: ${data.temp || '0'}°C
        </span>
        
        <span class="data-item">
          <i class="fas fa-fire"></i>
          Flame: ${data.flame_detected ? 'Detected' : 'None'}
        </span>
      </div>
      <div class="alert-actions">
        <button type="button" class="btn-stop" aria-label="Stop" onclick="stopSpeech()">
          <i class="fas fa-stop"></i>
        </button>
        <button type="button" class="btn-close" aria-label="Close" onclick="dismissAlert()">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `;
    
    document.body.insertBefore(alertDiv, document.body.firstChild);
    
    // Start auto-hide timer
    autoHideAlert();
    
    // Trigger text-to-speech for emergency only if not spoken before
    if (shouldSpeak && !hasSpoken) {
      let speechText = '';
      if (data.status === 'EMERGENCY') {
        speechText = `Emergency! Fire detected in ${data.building_type || 'the building'}. `;
        speechText += `Smoke level is ${data.smoke || '0'} PPM. `;
        speechText += `Temperature is ${data.temp || '0'} degrees Celsius. `;
        speechText += data.flame_detected ? 'Flame detected. ' : 'No flame detected. ';
        speechText += 'Please evacuate immediately.';
        speak(speechText);
      }
    }
  }

  // Add event listener for page unload
  window.addEventListener('beforeunload', function() {
    stopSpeech();
  });

  <?php if ($shouldRefresh): ?>
  // Initial update
  updateFireStatus();
  
  // Set interval to update status periodically
  setInterval(updateFireStatus, 5000);
  
  // Start auto-hide timer for initial alert if it exists
  document.addEventListener('DOMContentLoaded', function() {
    const initialAlert = document.getElementById('fireAlert');
    if (initialAlert) {
      autoHideAlert();
    }
  });
  
  // Trigger text-to-speech on initial load if needed
  <?php if ($shouldSpeak): ?>
  document.addEventListener('DOMContentLoaded', function() {
    let speechText = '';
    <?php if ($status === 'EMERGENCY'): ?>
      speechText = 'Emergency! Fire detected in <?php echo addslashes($building_type ?: 'the building'); ?>. ';
      speechText += 'Smoke level is <?php echo $smoke ?: '0'; ?> PPM. ';
      speechText += 'Temperature is <?php echo $temp ?: '0'; ?> degrees Celsius. ';
      speechText += '<?php echo addslashes($flame_detected ? 'Flame detected. ' : 'No flame detected. '); ?>';
      speechText += 'Please evacuate immediately.';
    <?php endif; ?>
    
    if (speechText && !hasSpoken) {
      setTimeout(function() {
        speak(speechText);
      }, 1000); // Small delay to ensure page is loaded
    }
  });
  <?php endif; ?>
  <?php endif; ?>
  </script>
</body>
</html>