<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/../../db/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get database connection
$pdo = getDatabaseConnection();

// Get initial status data
$sql = "SELECT status, building_type, smoke, temp, heat, flame_detected FROM fire_data ORDER BY timestamp DESC LIMIT 1";
$stmt = $pdo->query($sql);

$showAlert = false;
$status = "SAFE"; // Default status
$shouldRefresh = false; // Flag to determine if we should auto-refresh
$shouldSpeak = false; // Flag to determine if we should trigger TTS

if ($stmt && $stmt->rowCount() > 0) {
    $row = $stmt->fetch();
    $status = htmlspecialchars($row['status']);
    $building_type = htmlspecialchars($row['building_type']);
    $smoke = htmlspecialchars($row['smoke']);
    $temp = htmlspecialchars($row['temp']);
    $heat = htmlspecialchars($row['heat']);
    $flame_detected = htmlspecialchars($row['flame_detected']);
    
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <style>
  /* Modern font and base styles */
  * {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }
  
  /* Red border themes */
  .alert-danger { 
    background-color: white; 
    color: #dc3545;
    border: 2px solid #dc3545;
    animation: pulse 1.5s infinite;
  }
  .alert-warning { 
    background-color: white; 
    color: #dc3545;
    border: 2px solid #dc3545;
  }
  .alert-success { 
    background-color: white; 
    color: #dc3545;
    border: 2px solid #dc3545;
  }
  .alert-info { 
    background-color: white; 
    color: #dc3545;
    border: 2px solid #dc3545;
  }

  /* Compact alert styling */
  .status-alert {
    margin: 0.5rem 0;
    padding: 0.75rem;
    font-size: 0.85rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: white;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
  }

  .alert-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
  }

  /* Compact badge */
  .status-badge {
    padding: 0.3rem 0.5rem;
    font-weight: 600;
    font-size: 0.75rem;
    background-color: rgba(220,53,69,0.1);
    color: #dc3545;
  }

  /* Compact data items */
  .data-item {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.5rem;
    background-color: rgba(220,53,69,0.05);
    font-size: 0.75rem;
    color: #333;
  }

  .data-item i {
    margin-right: 0.3rem;
    font-size: 0.8rem;
    color: #dc3545;
  }
  
  /* Compact close button */
  .btn-close {
    width: 28px;
    height: 28px;
    border: 1px solid #dc3545;
    background-color: transparent;
    color: #dc3545;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    opacity: 0.8;
  }

  .btn-close:hover {
    opacity: 1;
    background-color: #dc3545;
    color: white;
  }

  .btn-close i {
    font-size: 0.8rem;
  }

  /* Compact stop button */
  .btn-stop {
    margin-left: 0.5rem;
    width: 28px;
    height: 28px;
    border: 1px solid #dc3545;
    background-color: transparent;
    color: #dc3545;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    opacity: 0.8;
  }

  .btn-stop:hover {
    opacity: 1;
    background-color: #dc3545;
    color: white;
  }

  .btn-stop i {
    font-size: 0.8rem;
  }

  /* Button container */
  .alert-buttons {
    display: flex;
    align-items: center;
    gap: 0.3rem;
  }
  
  /* Pulse animation for emergency */
  @keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
  }

  /* Responsive design */
  @media (max-width: 768px) {
    .status-alert {
      flex-direction: column;
      align-items: stretch;
      gap: 1rem;
    }
    
    .alert-content {
      justify-content: center;
      gap: 1rem;
    }
    
    .alert-buttons {
      justify-content: center;
    }
    
    .data-item {
      font-size: 0.7rem;
      padding: 0.25rem 0.4rem;
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
      <div class="alert-buttons">
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
    // Determine the correct path based on current page location
    const currentPath = window.location.pathname;
    let checkLoginPath = 'check_login.php';
    
    // If we're in the mapping/php directory, check_login.php is in the same directory
    if (currentPath.includes('/mapping/php/')) {
      checkLoginPath = 'check_login.php';
    } else if (currentPath.includes('/mapping/') && !currentPath.includes('/php/')) {
      // If we're in mapping but not in php subdirectory, go to php subdirectory
      checkLoginPath = 'php/check_login.php';
    } else if (currentPath.includes('/alarm/')) {
      checkLoginPath = 'check_login.php';
    } else {
      // Default: try the mapping php directory
      checkLoginPath = '../mapping/php/check_login.php';
    }
    
    fetch(checkLoginPath)
      .then(response => {
        if (!response.ok) {
          // Silently fail if file doesn't exist (404)
          return null;
        }
        return response.json();
      })
      .then(data => {
        if (data && !data.logged_in) {
          stopSpeech();
        }
      })
      .catch(error => {
        // Silently fail - don't spam console with errors
        // console.error('Error checking login status:', error);
      });
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
    fetch('get_fire_status.php')
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
      <div class="alert-buttons">
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
  
  // Start auto-hide timer for initial alert
  if (document.getElementById('fireAlert')) {
    autoHideAlert();
  }
  
  // Trigger text-to-speech on initial load if needed
  <?php if ($shouldSpeak): ?>
  document.addEventListener('DOMContentLoaded', function() {
    let speechText = '';
    <?php if ($status === 'EMERGENCY'): ?>
      speechText = 'Emergency! Fire detected in <?php echo addslashes($building_type ?: 'the building'); ?>. ';
      speechText += 'Smoke level is <?php echo $smoke ?: '0'; ?> PPM. ';
      speechText += 'Temperature is <?php echo $temp ?: '0'; ?> degrees Celsius. ';
      speechText += '<?php echo $flame_detected ? 'Flame detected. ' : 'No flame detected. '; ?>';
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