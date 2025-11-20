<?php
// Start session to access user authentication
session_start();

// Include the announcement helper functions
require_once 'announcement_helper.php';

// Check if user is logged in
if (!isUserAuthenticated()) {
    // Redirect to login page if user is not authenticated
    header('Location: ../../../index.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        // Get user-specific announcements using the helper function
        $announcements = getCurrentUserAnnouncements(true);
        
        echo json_encode(['success' => true, 'data' => $announcements]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <!-- Other CSS -->
        <link href="../../../vendors/nprogress/nprogress.css" rel="stylesheet">
    <link href="../../../vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">
    <link href="../../../vendors/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">
    <link href="../../../build/css/custom.min.css" rel="stylesheet">
    <style>
        .priority-high { border-left: 4px solid #dc3545; background-color: #fff5f5; }
        .priority-medium { border-left: 4px solid #ffc107; background-color: #fffcf5; }
        .priority-low { border-left: 4px solid #28a745; background-color: #f5fff7; }
        .announcement-item { cursor: pointer; transition: all 0.2s; margin-bottom: 10px; }
        .announcement-item:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .announcement-date { font-size: 0.85rem; color: #6c757d; }
        #ttsControls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        #voiceSelect { flex-grow: 1; min-width: 200px; }
        .tts-rate-control { display: flex; align-items: center; gap: 5px; }
        .tts-rate-control input { width: 80px; }
        
        /* Latest Announcement Card Styles */
        #latestAnnouncementCard {
            animation: slideInDown 0.6s ease-out;
            border-radius: 15px;
            overflow: hidden;
        }
        
        #latestAnnouncementCard .card-header {
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .announcement-icon {
            animation: pulse 2s infinite;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .priority-badge-high {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(220, 53, 69, 0.3);
        }
        
        .priority-badge-medium {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(255, 193, 7, 0.3);
        }
        
        .priority-badge-low {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
        }
        
        .latest-content-preview {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
        <div class="col-md-3 left_col">
          <div class="left_col scroll-view">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main">        
            <main class="main-content">
                <div class="row">
        <!-- Latest Announcement Highlight -->
        <div id="latestAnnouncementCard" class="card mb-4" style="display: none; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div class="card-header bg-gradient-primary text-black" style="background: white">
                <div class="d-flex align-items-center">
                    <i class="fas fa-star me-2"></i>
                    <h4 class="mb-0">Latest Announcement</h4>
                    <span class="badge bg-warning text-dark ms-auto">
                        <i class="fas fa-clock me-1"></i>Just In
                    </span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-8">
                        <h3 id="latestTitle" class="text-primary mb-3"></h3>
                        <p id="latestContent" class="lead mb-3"></p>
                        <div class="d-flex align-items-center text-muted mb-3">
                            <i class="fas fa-user-circle me-2"></i>
                            <span id="latestAuthor"></span>
                            <i class="fas fa-calendar-alt ms-3 me-2"></i>
                            <span id="latestDate"></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button id="viewLatestBtn" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>View Details
                            </button>
                            <button id="playLatestBtn" class="btn btn-success">
                                <i class="fas fa-volume-up me-2"></i>Listen Now
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div id="latestPriorityBadge" class="mb-3"></div>
                        <div class="announcement-icon">
                            <i class="fas fa-bullhorn fa-4x text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="loadingIndicator" class="text-center my-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading announcements...</p>
        </div>
        
        <!-- Previous Announcements Section -->
        <div id="previousAnnouncementsSection" style="display: none;">
            <h4 class="mb-3 text-muted">
                <i class="fas fa-history me-2"></i>Previous Announcements
            </h4>
            <div id="announcementsList" class="list-group mb-4"></div>
        </div>
        
        <!-- No Announcements Message -->
        <div id="noAnnouncements" class="alert alert-info" style="display: none;">
            <i class="fas fa-info-circle me-2"></i>
            No current announcements available.
        </div>
        

        
        <!-- Announcement Modal -->
        <div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="announcementTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="announcementContent" class="mb-3"></div>
                        <hr>
                        <div class="text-muted small">
                            <div><i class="fas fa-user me-1"></i> Posted by: <span id="announcementAuthor"></span></div>
                            <div><i class="fas fa-calendar-alt me-1"></i> Published on: <span id="announcementDate"></span></div>
                            <div><i class="fas fa-exclamation-circle me-1"></i> Priority: <span id="announcementPriority"></span></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div id="ttsControls" class="me-auto">
                            <select id="voiceSelect" class="form-select form-select-sm">
                                <option value="">Select Voice</option>
                            </select>
                            <div class="tts-rate-control">
                                <label for="rateControl">Speed:</label>
                                <input type="range" id="rateControl" min="0.5" max="2" step="0.1" value="1">
                                <span id="rateValue">1</span>
                            </div>
                            <div class="btn-group">
                                <button id="playTtsBtn" class="btn btn-sm btn-primary">
                                    <i class="fas fa-play"></i> Play
                                </button>
                                <button id="pauseTtsBtn" class="btn btn-sm btn-warning" disabled>
                                    <i class="fas fa-pause"></i> Pause
                                </button>
                                <button id="stopTtsBtn" class="btn btn-sm btn-danger" disabled>
                                    <i class="fas fa-stop"></i> Stop
                                </button>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoPlaySwitch">
                                <label class="form-check-label" for="autoPlaySwitch">Auto-play</label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery (must be loaded first) -->
    <script src="../../../vendors/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap (must be loaded before custom scripts) -->
    <script src="../../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const announcementsList = document.getElementById('announcementsList');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const noAnnouncements = document.getElementById('noAnnouncements');
            const refreshBtn = document.getElementById('refreshBtn');
            const latestAnnouncementCard = document.getElementById('latestAnnouncementCard');
            const previousAnnouncementsSection = document.getElementById('previousAnnouncementsSection');
            const latestTitle = document.getElementById('latestTitle');
            const latestContent = document.getElementById('latestContent');
            const latestAuthor = document.getElementById('latestAuthor');
            const latestDate = document.getElementById('latestDate');
            const latestPriorityBadge = document.getElementById('latestPriorityBadge');
            const viewLatestBtn = document.getElementById('viewLatestBtn');
            const playLatestBtn = document.getElementById('playLatestBtn');
            const announcementModal = new bootstrap.Modal(document.getElementById('announcementModal'));
            const announcementTitle = document.getElementById('announcementTitle');
            const announcementContent = document.getElementById('announcementContent');
            const announcementAuthor = document.getElementById('announcementAuthor');
            const announcementDate = document.getElementById('announcementDate');
            const announcementPriority = document.getElementById('announcementPriority');
            const playTtsBtn = document.getElementById('playTtsBtn');
            const pauseTtsBtn = document.getElementById('pauseTtsBtn');
            const stopTtsBtn = document.getElementById('stopTtsBtn');
            const voiceSelect = document.getElementById('voiceSelect');
            const autoPlaySwitch = document.getElementById('autoPlaySwitch');
            const rateControl = document.getElementById('rateControl');
            const rateValue = document.getElementById('rateValue');

            
            // TTS Variables
            let synth = window.speechSynthesis;
            let utterance = null;
            let voices = [];
            let currentAnnouncement = null;
            let isPlaying = false;
            let retryCount = 0;
            const MAX_RETRIES = 2;
            
            // Initialize the page
            loadAnnouncements();
            setupEventListeners();
            
            // Auto-play the latest announcement immediately when announcements are loaded
            // This will be called from loadAnnouncements when data is received
            
            // Load announcements from server
            function loadAnnouncements() {
                // Hide all sections initially
                latestAnnouncementCard.style.display = 'none';
                previousAnnouncementsSection.style.display = 'none';
                noAnnouncements.style.display = 'none';
                loadingIndicator.style.display = 'block';
                
                fetch('?action=get_announcements')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            renderAnnouncements(data.data);
                            // Store announcements for auto-play
                            window.latestAnnouncements = data.data;
                            // Auto-play the latest announcement immediately
                            setTimeout(() => {
                                autoPlayLatestAnnouncement();
                            }, 500);
                        } else {
                            noAnnouncements.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        noAnnouncements.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Failed to load announcements. Please try again.';
                        noAnnouncements.style.display = 'block';
                    })
                    .finally(() => {
                        loadingIndicator.style.display = 'none';
                    });
            }
            
            // Render announcements list
            function renderAnnouncements(announcements) {
                if (announcements.length === 0) {
                    noAnnouncements.style.display = 'block';
                    return;
                }

                // Display latest announcement prominently
                const latestAnnouncement = announcements[0];
                displayLatestAnnouncement(latestAnnouncement);

                // Display previous announcements
                if (announcements.length > 1) {
                    displayPreviousAnnouncements(announcements.slice(1));
                }
            }

            // Display latest announcement in the prominent card
            function displayLatestAnnouncement(announcement) {
                const date = new Date(announcement.start_date);
                const formattedDate = date.toLocaleDateString('en-US', {
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // Update latest announcement card
                latestTitle.textContent = announcement.title;
                latestContent.textContent = announcement.content.length > 200 
                    ? announcement.content.substring(0, 200) + '...' 
                    : announcement.content;
                latestAuthor.textContent = announcement.author_name;
                latestDate.textContent = formattedDate;

                // Set priority badge
                const priorityClass = `priority-badge-${announcement.priority}`;
                const priorityText = announcement.priority.charAt(0).toUpperCase() + announcement.priority.slice(1);
                latestPriorityBadge.innerHTML = `<span class="${priorityClass}">${priorityText} Priority</span>`;

                // Show the latest announcement card
                latestAnnouncementCard.style.display = 'block';

                // Add event listeners for latest announcement buttons
                viewLatestBtn.onclick = () => showAnnouncementModal(announcement);
                playLatestBtn.onclick = () => {
                    currentAnnouncement = announcement;
                    playTts();
                };
            }

            // Display previous announcements in the list
            function displayPreviousAnnouncements(announcements) {
                announcementsList.innerHTML = '';
                
                announcements.forEach(announcement => {
                    const item = document.createElement('div');
                    item.className = `list-group-item announcement-item priority-${announcement.priority}`;
                    
                    const date = new Date(announcement.start_date);
                    const formattedDate = date.toLocaleDateString('en-US', {
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    item.innerHTML = `
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">${announcement.title}</h5>
                            <small class="text-muted">${formattedDate}</small>
                        </div>
                        <p class="mb-1 text-truncate">${announcement.content.substring(0, 100)}...</p>
                        <small class="text-muted">By ${announcement.author_name} • Priority: ${announcement.priority}</small>
                    `;
                    
                    item.addEventListener('click', () => {
                        // Show SweetAlert confirmation before opening modal
                        Swal.fire({
                            title: '📢 View Announcement',
                            text: `Would you like to view "${announcement.title}"?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'View Details',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#007bff',
                            cancelButtonColor: '#6c757d'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                showAnnouncementModal(announcement);
                            }
                        });
                    });
                    announcementsList.appendChild(item);
                });

                // Show previous announcements section
                previousAnnouncementsSection.style.display = 'block';
            }
            
            // Show announcement in modal
            function showAnnouncementModal(announcement) {
                currentAnnouncement = announcement;
                
                const date = new Date(announcement.start_date);
                const formattedDate = date.toLocaleDateString('en-US', {
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                announcementTitle.textContent = announcement.title;
                announcementContent.innerHTML = announcement.content.replace(/\n/g, '<br>');
                announcementAuthor.textContent = announcement.author_name;
                announcementDate.textContent = formattedDate;
                announcementPriority.textContent = announcement.priority.charAt(0).toUpperCase() + announcement.priority.slice(1);
                
                // Load voices if not already loaded
                if (voices.length === 0) {
                    loadVoices();
                }
                
                announcementModal.show();
                
                // Auto-play if enabled
                if (autoPlaySwitch.checked) {
                    setTimeout(() => {
                        playTts();
                    }, 500);
                }
            }
            
            // TTS Functions
            function loadVoices() {
                voices = synth.getVoices();
                voiceSelect.innerHTML = '<option value="">Select Voice</option>';
                
                voices.forEach(voice => {
                    const option = document.createElement('option');
                    option.textContent = `${voice.name} (${voice.lang})`;
                    option.setAttribute('data-lang', voice.lang);
                    option.setAttribute('data-name', voice.name);
                    voiceSelect.appendChild(option);
                });
            }
            
            function playTts() {
                if (utterance) {
                    synth.cancel();
                }
                
                if (!currentAnnouncement) return;
                
                const selectedVoice = voiceSelect.selectedOptions[0];
                const voiceName = selectedVoice ? selectedVoice.getAttribute('data-name') : null;
                const voice = voices.find(v => v.name === voiceName);
                
                utterance = new SpeechSynthesisUtterance();
                utterance.text = `${currentAnnouncement.title}. ${currentAnnouncement.content}`;
                utterance.rate = parseFloat(rateControl.value);
                
                if (voice) {
                    utterance.voice = voice;
                }
                
                utterance.onboundary = (event) => {
                    // You could add highlighting of spoken text here
                };
                
                utterance.onend = () => {
                    playTtsBtn.disabled = false;
                    pauseTtsBtn.disabled = true;
                    stopTtsBtn.disabled = true;
                };
                
                synth.speak(utterance);
                playTtsBtn.disabled = true;
                pauseTtsBtn.disabled = false;
                stopTtsBtn.disabled = false;
            }
            
            function pauseTts() {
                if (synth.speaking) {
                    synth.pause();
                    playTtsBtn.disabled = false;
                    pauseTtsBtn.disabled = true;
                }
            }
            
            function resumeTts() {
                if (synth.paused) {
                    synth.resume();
                    playTtsBtn.disabled = true;
                    pauseTtsBtn.disabled = false;
                }
            }
            
            function stopTts() {
                synth.cancel();
                isPlaying = false;
                retryCount = 0;
                playTtsBtn.disabled = false;
                pauseTtsBtn.disabled = true;
                stopTtsBtn.disabled = true;
            }
            
            // Auto-play the latest announcement
            function autoPlayLatestAnnouncement() {
                if (!window.latestAnnouncements || window.latestAnnouncements.length === 0) {
                    console.log('No announcements available for auto-play');
                    return;
                }
                
                // Prevent multiple simultaneous auto-plays
                if (isPlaying) {
                    console.log('Already playing an announcement, skipping auto-play');
                    return;
                }
                
                // Get the latest announcement (first in the array since they're sorted by date desc)
                const latestAnnouncement = window.latestAnnouncements[0];
                console.log('Auto-playing latest announcement:', latestAnnouncement.title);
                
                // Ensure speech synthesis is available
                if (!synth) {
                    console.error('Speech synthesis not available');
                    return;
                }
                
                // Check if user has interacted with the page (required for autoplay)
                if (!window.userHasInteracted) {
                    console.log('Waiting for user interaction before auto-play');
                    // Show a prompt to the user
                    showAutoplayPrompt(latestAnnouncement);
                    return;
                }
                
                // Reset retry count for new announcement
                retryCount = 0;
                
                // Load voices if not already loaded
                if (voices.length === 0) {
                    loadVoices();
                    // Wait for voices to load
                    const checkVoices = setInterval(() => {
                        if (voices.length > 0) {
                            clearInterval(checkVoices);
                            playLatestAnnouncement(latestAnnouncement);
                        }
                    }, 100);
                    
                    // Timeout after 3 seconds
                    setTimeout(() => {
                        clearInterval(checkVoices);
                        if (voices.length === 0) {
                            console.log('Using default voice');
                            playLatestAnnouncement(latestAnnouncement);
                        }
                    }, 3000);
                } else {
                    playLatestAnnouncement(latestAnnouncement);
                }
            }
            
            // Show autoplay prompt using SweetAlert
            function showAutoplayPrompt(announcement) {
                Swal.fire({
                    title: '🎧 Audio Ready!',
                    text: `Click "Listen Now" to hear the latest announcement: ${announcement.title}`,
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Listen Now',
                    cancelButtonText: 'Not Now',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    timer: 10000, // Auto-close after 10 seconds
                    timerProgressBar: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // User clicked "Listen Now"
                        window.userHasInteracted = true;
                        playLatestAnnouncement(announcement);
                    } else {
                        // User clicked "Not Now" or timer expired
                        window.pendingAnnouncement = null;
                    }
                });
                
                // Store the announcement to play after user interaction
                window.pendingAnnouncement = announcement;
            }
            
            // Play the latest announcement without opening modal
            function playLatestAnnouncement(announcement) {
                // Prevent multiple simultaneous plays
                if (isPlaying) {
                    console.log('Already playing, skipping duplicate play request');
                    return;
                }
                
                isPlaying = true;
                
                if (utterance) {
                    synth.cancel();
                }
                
                const defaultVoice = voices.find(v => v.lang.startsWith('en')) || voices[0];
                
                utterance = new SpeechSynthesisUtterance();
                utterance.text = `Latest announcement: ${announcement.title}. ${announcement.content}`;
                utterance.rate = 1.0;
                
                if (defaultVoice) {
                    utterance.voice = defaultVoice;
                }
                
                utterance.onstart = () => {
                    console.log('Announcement playback started');
                    // Show SweetAlert notification that announcement is playing
                    Swal.fire({
                        title: '🔊 Playing Announcement',
                        text: announcement.title,
                        icon: 'success',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    });
                };
                
                utterance.onend = () => {
                    console.log('Latest announcement auto-play completed');
                    isPlaying = false;
                    retryCount = 0;
                    
                    // Show completion notification
                    Swal.fire({
                        title: '✅ Announcement Complete',
                        text: 'The announcement has finished playing.',
                        icon: 'success',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                };
                
                utterance.onerror = (event) => {
                    console.error('TTS Error:', event.error);
                    isPlaying = false;
                    
                    // Show error notification
                    Swal.fire({
                        title: '❌ Audio Error',
                        text: 'There was an error playing the announcement. Please try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    
                    // Try to resume if it was interrupted, but limit retries
                    if (event.error === 'interrupted' && retryCount < MAX_RETRIES) {
                        retryCount++;
                        console.log(`Retrying announcement playback (attempt ${retryCount}/${MAX_RETRIES})`);
                        setTimeout(() => {
                            playLatestAnnouncement(announcement);
                        }, 2000); // Longer delay between retries
                    } else if (retryCount >= MAX_RETRIES) {
                        console.log('Max retries reached, stopping auto-play');
                        retryCount = 0;
                    }
                };
                
                // Try to speak
                synth.speak(utterance);
                console.log('Auto-playing latest announcement:', announcement.title);
            }
            
            // Event Listeners
            function setupEventListeners() {
                // Voices changed
                synth.onvoiceschanged = loadVoices;
                
                // TTS Controls
                playTtsBtn.addEventListener('click', playTts);
                pauseTtsBtn.addEventListener('click', () => {
                    if (synth.paused) {
                        resumeTts();
                        pauseTtsBtn.innerHTML = '<i class="fas fa-pause"></i> Pause';
                    } else {
                        pauseTts();
                        pauseTtsBtn.innerHTML = '<i class="fas fa-play"></i> Resume';
                    }
                });
                stopTtsBtn.addEventListener('click', stopTts);
                

                
                // Rate control
                rateControl.addEventListener('input', () => {
                    rateValue.textContent = rateControl.value;
                    if (utterance) {
                        utterance.rate = parseFloat(rateControl.value);
                    }
                });
                
                // Refresh button
                refreshBtn.addEventListener('click', () => {
                    Swal.fire({
                        title: '🔄 Refreshing...',
                        text: 'Loading latest announcements',
                        icon: 'info',
                        timer: 1000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        allowOutsideClick: false
                    }).then(() => {
                        loadAnnouncements();
                    });
                });
                
                // Handle browser autoplay restrictions - enable audio on first user interaction
                document.addEventListener('click', function enableAudio() {
                    // Mark that user has interacted
                    window.userHasInteracted = true;
                    
                    // Try to play a silent audio to enable audio context
                    const silentAudio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=');
                    silentAudio.play().catch(() => {});
                    
                    // Play pending announcement if there is one and not already playing
                    if (window.pendingAnnouncement && !isPlaying) {
                        setTimeout(() => {
                            playLatestAnnouncement(window.pendingAnnouncement);
                            window.pendingAnnouncement = null;
                        }, 100);
                    }
                    
                    // Remove this listener after first click
                    document.removeEventListener('click', enableAudio);
                }, { once: true });
                
                // Modal events
                document.getElementById('announcementModal').addEventListener('hidden.bs.modal', () => {
                    stopTts();
                    currentAnnouncement = null;
                });
            }
        });
    </script>
    
    <!-- Additional Scripts (loaded after jQuery) -->
    <!-- FastClick -->
    <script src="../../../vendors/fastclick/lib/fastclick.js"></script>
    <!-- NProgress -->
    <!-- <script src="../../../vendors/nprogress/nprogress.js"></script> -->
    <!-- Chart.js -->
    <script src="../../../vendors/Chart.js/dist/Chart.min.js"></script>
    <!-- gauge.js -->
    <script src="../../../vendors/gauge.js/dist/gauge.min.js"></script>
    <!-- bootstrap-progressbar -->
    <script src="../../../vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
    <!-- iCheck -->
    <script src="../../../vendors/iCheck/icheck.min.js"></script>
    <!-- Skycons -->
    <script src="../../../vendors/skycons/skycons.js"></script>
    <!-- Flot -->
    <script src="../../../vendors/Flot/jquery.flot.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.pie.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.time.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.stack.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.resize.js"></script>
    <!-- Flot plugins -->
    <script src="../../../vendors/flot.orderbars/js/jquery.flot.orderBars.js"></script>
    <script src="../../../vendors/flot-spline/js/jquery.flot.spline.min.js"></script>
    <script src="../../../vendors/flot.curvedlines/curvedLines.js"></script>
    <!-- DateJS -->
    <script src="../../../vendors/DateJS/build/date.js"></script>
    <!-- JQVMap -->
    <script src="../../../vendors/jqvmap/dist/jquery.vmap.js"></script>
    <script src="../../../vendors/jqvmap/dist/maps/jquery.vmap.world.js"></script>
    <script src="../../../vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"></script>
    <!-- bootstrap-daterangepicker -->
    <script src="../../../vendors/moment/min/moment.min.js"></script>
    <script src="../../../vendors/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- Custom Theme Scripts (load last, after all dependencies) -->
    <script src="../../../build/js/custom.min.js"></script>
    
</body>
</html>
	