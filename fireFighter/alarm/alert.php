<?php
// Database connection variables
$host = 'localhost';
$dbname = 'u520834156_DBBagofire';
$user = 'u520834156_userBagofire';
$pass = 'i[#[GQ!+=C9';

// Create connection
$mysqli = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($mysqli->connect_errno) {
    echo '<div class="alert alert-danger" role="alert">Failed to connect to database: ' . htmlspecialchars($mysqli->connect_error) . '</div>';
    exit();
}

// Get initial status data
$sql = "SELECT status, building_type, smoke, temp, heat, flame_detected FROM fire_data ORDER BY timestamp DESC LIMIT 1";
$result = $mysqli->query($sql);

$showAlert = false;
$status = "SAFE"; // Default status
$shouldRefresh = false; // Flag to determine if we should auto-refresh

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
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
} else {
    $building_type = "";
    $smoke = "";
    $temp = "";
    $heat = "";
    $flame_detected = "";
}

$mysqli->close();

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
  /* Simplified color themes */
  .alert-danger { 
    background-color: #ffebee; 
    color: #c62828;
    border-left: 4px solid #c62828;
  }
  .alert-warning { 
    background-color: #fff8e1; 
    color: #ff8f00;
    border-left: 4px solid #ff8f00;
  }
  .alert-success { 
    background-color: #e8f5e9; 
    color: #2e7d32;
    border-left: 4px solid #2e7d32;
  }
  .alert-info { 
    background-color: #e3f2fd; 
    color: #1565c0;
    border-left: 4px solid #1565c0;
  }

  /* Basic alert styling */
  .status-alert {
    border-radius: 4px;
    margin: 1rem 0;
    padding: 1rem;
    font-size: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: white;
    animation: pulse 3s infinite ease-in-out;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
  }

  /* Enhanced pulse animation */
  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.005); }
    100% { transform: scale(1); }
  }

  /* Enhanced speech controls */
  .speech-controls {
    display: flex;
    gap: 0.75rem;
    margin-left: 1rem;
  }

  .speech-btn {
    background: #f8f9fa;
    border: 2px solid #007bff;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #007bff;
    margin-top: 5px;
  }



  .speech-btn i {
    font-size: 0.9rem;
  }

  /* Enhanced close button */
  .btn-close {
    background: transparent;
    border: 2px solid #dc3545;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #dc3545;
  }



  .btn-close i {
    font-size: 0.9rem;
  }

  /* Data items */
  .data-item {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem;
  }

  .data-item i {
    margin-right: 0.5rem;
  }
  
  /* Simple badge */
  .status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.85rem;
  }
</style>

</head>
<body>
    <!-- Bootstrap Alert with Close Button - Only shown for EMERGENCY, MONITORING, ACKNOWLEDGED -->
    <?php if ($showAlert): ?>
    <div id="fireAlert" class="alert <?php echo $alertClass; ?> status-alert mb-4 <?php echo $status === 'EMERGENCY' ? 'pulse-emergency' : ''; ?>">
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
      <div class="speech-controls">
        <button id="stopSpeechBtn" class="speech-btn" title="Stop announcement">
          <i class="fas fa-stop"></i>
        </button>
        <button class="btn-close" aria-label="Close" onclick="dismissAlert()">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    <?php endif; ?>

  <!-- Bootstrap JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  // Speech synthesis variables
  let speechSynthesis = window.speechSynthesis;
  let currentUtterance = null;
  let hasSpoken = false;

  // Function to speak the alert message
  function speakAlert(status, building, smoke, temp, flame) {
    // Stop any current speech
    stopSpeech();
    
    // Only speak for alert-worthy statuses
    if (!['EMERGENCY', 'MONITORING', 'ACKNOWLEDGED'].includes(status)) {
      return;
    }
    
    // Don't speak if we've already spoken for this alert
    if (hasSpoken) {
      return;
    }
    
    // Create the message to speak
    let message = '';
    if (status === 'EMERGENCY') {
      message = `Emergency! Fire detected in ${building}. `;
      message += `Smoke level at ${smoke} PPM. `;
      message += `Temperature at ${temp} degrees. `;
      message += flame === 'Detected' ? 'Flame detected. ' : 'No flame detected. ';
      message += 'Immediate action required.';
    } else if (status === 'MONITORING') {
      message = `Monitoring alert in ${building}. `;
      message += `Smoke level at ${smoke} PPM. `;
      message += `Temperature at ${temp} degrees. `;
      message += 'Situation being monitored.';
    } else if (status === 'ACKNOWLEDGED') {
      message = `Alert acknowledged for ${building}. `;
      message += `Smoke level at ${smoke} PPM. `;
      message += `Temperature at ${temp} degrees. `;
      message += `Flame ${flame}. `;
      message += 'Response team notified.';
    }
    
    // Create and speak the utterance
    currentUtterance = new SpeechSynthesisUtterance(message);
    currentUtterance.rate = 0.9;
    currentUtterance.pitch = 1.1;
    currentUtterance.volume = 1;
    
    currentUtterance.onend = function() {
      hasSpoken = true;
    };
    
    speechSynthesis.speak(currentUtterance);
  }

  // Function to stop speech
  function stopSpeech() {
    if (speechSynthesis.speaking) {
      speechSynthesis.cancel();
      hasSpoken = true; // Set hasSpoken to true to prevent re-speaking
    }
  }

  // Function to dismiss the alert
  function dismissAlert() {
    const alertDiv = document.getElementById('fireAlert');
    if (alertDiv) {
      alertDiv.style.display = 'none';
    }
    stopSpeech();
    
    // Request location access after alarm is dismissed (only if not already granted)
    requestLocationAccess();
  }

  // Function to request location access
  function requestLocationAccess() {
    // Check if geolocation is supported
    if (!navigator.geolocation) {
      console.log('Geolocation is not supported by this browser.');
      return;
    }

    // Check if we already have location permission
    navigator.permissions.query({ name: 'geolocation' }).then(function(permissionStatus) {
      if (permissionStatus.state === 'granted') {
        console.log('Location access already granted.');
        return;
      }
      
      // If permission is not granted, request it
      if (permissionStatus.state === 'denied' || permissionStatus.state === 'prompt') {
        console.log('Requesting location access...');
        
        // Show a user-friendly message before requesting location
        if (confirm('For emergency response purposes, this system needs access to your location. Would you like to allow location access?')) {
          navigator.geolocation.getCurrentPosition(
            function(position) {
              console.log('Location access granted successfully!');
              alert('Location access granted! This will help emergency responders locate you quickly in case of an emergency.');
            },
            function(error) {
              console.log('Location access denied or error occurred:', error.message);
              switch(error.code) {
                case error.PERMISSION_DENIED:
                  alert('Location access was denied. You can enable it later in your browser settings for better emergency response.');
                  break;
                case error.POSITION_UNAVAILABLE:
                  alert('Location information is currently unavailable. Please check your device settings.');
                  break;
                case error.TIMEOUT:
                  alert('Location request timed out. Please try again later.');
                  break;
                default:
                  alert('An error occurred while getting your location. Please try again later.');
                  break;
              }
            },
            {
              enableHighAccuracy: true,
              timeout: 10000,
              maximumAge: 300000 // 5 minutes
            }
          );
        } else {
          console.log('User declined location access request.');
          alert('Location access was declined. You can enable it later in your browser settings for better emergency response.');
        }
      }
    }).catch(function(error) {
      console.log('Error checking location permission:', error);
      // Fallback: try to get location directly
      navigator.geolocation.getCurrentPosition(
        function(position) {
          console.log('Location access granted successfully!');
          alert('Location access granted! This will help emergency responders locate you quickly in case of an emergency.');
        },
        function(error) {
          console.log('Location access denied or error occurred:', error.message);
          alert('Location access was not granted. You can enable it later in your browser settings for better emergency response.');
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 300000 // 5 minutes
        }
      );
    });
  }

  // Initialize when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    // Set up stop speech button event listener
    document.addEventListener('click', function(e) {
      if (e.target && (e.target.id === 'stopSpeechBtn' || e.target.closest('#stopSpeechBtn'))) {
        stopSpeech();
      }
    });

    // Handle page unload (logout or navigation)
    window.addEventListener('beforeunload', function() {
      stopSpeech();
    });

    <?php if ($showAlert): ?>
    // Speak the initial alert
    speakAlert(
      '<?php echo $status; ?>',
      '<?php echo $building_type ?: 'Unknown'; ?>',
      '<?php echo $smoke ?: '0'; ?>',
      '<?php echo $temp ?: '0'; ?>',
      '<?php echo $flame_detected ? 'Detected' : 'None'; ?>'
    );
    <?php endif; ?>
  });
  </script>
</body>
</html>