// Global jQuery check - ensure $ is not used
if (typeof $ !== 'undefined') {
    console.warn('jQuery ($) is available but should not be used. Using native fetch API instead.');
} else {
    console.log('jQuery ($) is not available - using native fetch API (this is correct).');
}

// Alert details functionality - will be initialized in main DOMContentLoaded
function initAlertDetails() {
    document.querySelectorAll('.view-alert-details').forEach(button => {
        button.addEventListener('click', () => {
            const status = button.getAttribute('data-status');
            const building = button.getAttribute('data-building');
            const smoke = button.getAttribute('data-smoke');
            const temp = button.getAttribute('data-temp');
            const heat = button.getAttribute('data-heat');
            const flame = button.getAttribute('data-flame') === '1' ? 'Yes' : 'No';
            const timestamp = button.getAttribute('data-timestamp');

            Swal.fire({
                title: `Alert Details`,
                html: `
                    <div style="text-align: left;">
                        <p><strong>Status:</strong> ${status}</p>
                        <p><strong>Building Type:</strong> ${building}</p>
                        <p><strong>Temperature:</strong> ${temp}°C</p>
                        <p><strong>Smoke Level:</strong> ${smoke}</p>
                        <p><strong>Heat:</strong> ${heat}</p>
                        <p><strong>Flame Detected:</strong> ${flame}</p>
                        <p><strong>Timestamp:</strong> ${timestamp}</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Close',
                customClass: {
                    popup: 'text-start'
                }
            });
        });
    });
}
// Configuration
const config = {
fireStation: { 
    lat: 10.525468, 
    lng: 122.841238, 
    name: "Bago City Fire Station",
    contact: "09605105611",
    address: null // Will be populated via reverse geocoding
},
buildings: typeof buildings !== 'undefined' ? buildings : [],
buildingAreas: typeof buildingAreas !== 'undefined' ? buildingAreas : [],
apiEndpoint: "server_enhanced.php",
updateInterval: 120000, // 2 minutes - reduced frequency
heatmapRadius: 25,
heatmapBlur: 15,
statusThresholds: {
    Safe: { smoke: 20, temp: 30, heat: 30 },
    Monitoring: { smoke: 50, temp: 50, heat: 50 },
    'Pre-Dispatch': { smoke: 100, temp: 100, heat: 100 },
    Emergency: { smoke: 200, temp: 200, heat: 200 }
},
userId: typeof userId !== 'undefined' ? userId : 0,
mapboxAccessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw'
};

// Global variables
let map, fireMarkers, heatLayer, routingControl;
let allFireData = [];
let heatmapEnabled = false;
let clusteringEnabled = true;
let userLocation = null;
let userMarker = null;
let pollingInterval = null; // Control polling interval
let isPollingActive = false; // Prevent multiple polling instances
let buildingAreaCircles = null; // Layer for building area circles - will be initialized after map

// Check for jQuery availability and provide fallback
function checkJQueryAvailability() {
    if (typeof $ === 'undefined') {
        console.warn('jQuery ($) is not available. Using native fetch API for all HTTP requests.');
        console.log('This is not an error - the application will work with native JavaScript fetch API.');
        return false;
    } else {
        console.log('jQuery is available and ready to use.');
        return true;
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing application...');
    
    // Check jQuery availability
    const jqueryAvailable = checkJQueryAvailability();
    
    try {
        // Initialize all components
        initAlertDetails(); // Initialize alert details functionality
        initMap();
        initEventListeners();
        initSpeechSynthesis(); // Initialize speech synthesis
        fetchFireData();
        initBuildingsLayer();
        initBuildingDetailsModal();
        startControlledPolling(); // Use controlled polling instead of setInterval
        
        // Ensure speech is enabled by default
        speechEnabled = true;
        console.log('Speech enabled:', speechEnabled);
        
        // No automatic speech on page load - only for RouteToStation
        console.log('Speech system ready for RouteToStation only');
        
        // Log the HTTP request method being used
        if (jqueryAvailable) {
            console.log('Using jQuery for HTTP requests');
        } else {
            console.log('Using native fetch API for HTTP requests');
        }
        
        // Get user location if permission was previously granted
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => updateUserLocation(position.coords.latitude, position.coords.longitude),
                error => console.log("Geolocation error:", error)
            );
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            stopControlledPolling();
        });
    } catch (error) {
        console.error('Error during initialization:', error);
        // Try to at least enable speech even if other components fail
        try {
            speechEnabled = true;
            speakText('System loaded with some errors. Speech functionality is available.', 'normal');
        } catch (speechError) {
            console.error('Speech initialization failed:', speechError);
        }
    }
});


// Initialize the map
function initMap() {
    map = L.map('map').setView([config.fireStation.lat, config.fireStation.lng], 15);
    
    // Base layers
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });
    
    // Layer control
    const baseMaps = {
        "Street Map": osmLayer,
        "Satellite": satelliteLayer
    };
    
    L.control.layers(baseMaps, null, {position: 'topright'}).addTo(map);
    
    // Initialize marker cluster group
    fireMarkers = L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 80
    });
    
    // Add fire station marker
    addFireStationMarker();
}

// Add fire station to map
async function addFireStationMarker() {
    const fireStationIcon = L.icon({
        iconUrl: './firestation.png',
        iconSize: [40, 40],
        iconAnchor: [20, 40],
        popupAnchor: [0, -40]
    });
    
    // Create marker first with temporary address
    const marker = L.marker([config.fireStation.lat, config.fireStation.lng], { 
        icon: fireStationIcon,
        zIndexOffset: 1000 
    })
    .addTo(map);
    
    // Function to update popup content
    const updatePopup = (address) => {
        marker.bindPopup(`
            <div class="text-center">
                <h5 class="fw-bold mb-2" style="background-color: red; color: white; padding: 5px; border-radius: 3px;">${config.fireStation.name}</h5>
                <p class="mb-1" style="background-color: red; color: white; padding: 5px; border-radius: 3px;"><i class="bi bi-telephone me-2"></i>${config.fireStation.contact}</p>
                <p class="mb-0"><i class="bi bi-geo-alt me-2"></i>${address}</p>
            </div>
        `);
        marker.bindTooltip(config.fireStation.name, { 
            permanent: false, 
            direction: 'top',
            className: 'fw-bold' 
        });
        marker.openPopup();
    };
    
    // Get address from coordinates if not already set
    if (!config.fireStation.address) {
        // Show popup with coordinates first
        updatePopup(`${config.fireStation.lat.toFixed(6)}, ${config.fireStation.lng.toFixed(6)}`);
        
        try {
            const address = await getAddressFromCoordinates(config.fireStation.lat, config.fireStation.lng);
            config.fireStation.address = address || `${config.fireStation.lat.toFixed(6)}, ${config.fireStation.lng.toFixed(6)}`;
            // Update popup with the address
            updatePopup(config.fireStation.address);
        } catch (error) {
            console.error('Error getting fire station address:', error);
            config.fireStation.address = `${config.fireStation.lat.toFixed(6)}, ${config.fireStation.lng.toFixed(6)}`;
            updatePopup(config.fireStation.address);
        }
    } else {
        updatePopup(config.fireStation.address);
    }
    
    return marker;
}


 // Global variable for buildings layer
let buildingsLayer = L.featureGroup();

// Initialize buildings layer only once
function initBuildingsLayer() {
    // Only add markers if not yet initialized
    if (buildingsLayer.getLayers().length === 0) {
        config.buildings.forEach(building => {
            const marker = createBuildingMarker(building);
            marker.options.buildingLayer = true; // tag for easy removal (optional)
            buildingsLayer.addLayer(marker);
        });
    }

    // Add layer to map if not already added
    if (!map.hasLayer(buildingsLayer)) {
        map.addLayer(buildingsLayer);
    }
    
    // Initialize building area circles
    initBuildingAreaCircles();
}

// Initialize building area circles (100m border areas)
function initBuildingAreaCircles() {
    // Initialize feature group if not already initialized
    if (!buildingAreaCircles) {
        buildingAreaCircles = L.featureGroup();
    }
    
    // Clear existing circles
    buildingAreaCircles.clearLayers();
    
    // Create circles for each building area
    config.buildingAreas.forEach(area => {
        if (area.center_latitude && area.center_longitude && area.radius) {
            const radius = parseFloat(area.radius) || 100; // Default to 100m if not specified
            
            // Create circle with 100m radius
            const circle = L.circle([area.center_latitude, area.center_longitude], {
                radius: radius,
                color: '#3388ff',
                fillColor: '#3388ff',
                fillOpacity: 0.1,
                weight: 2,
                opacity: 0.6
            });
            
            // Add popup with building information
            const buildingName = area.building_name || 'Building';
            const buildingType = area.building_type || 'Unknown';
            circle.bindPopup(`
                <div class="building-area-popup">
                    <h6 class="fw-bold mb-1" style="background-color: #90EE90; padding: 2px 4px; display: inline-block;">${buildingName}</h6>
                    <div class="text-muted small" style="background-color: #90EE90; padding: 2px 4px; display: inline-block;">Type: ${buildingType}</div>
                    <div class="small" style="background-color: #90EE90; padding: 2px 4px; display: inline-block;">Border Area: ${radius}m radius</div>
                    ${area.address ? `<div class="text-muted small mt-1" style="background-color: #90EE90; padding: 2px 4px; display: inline-block;">${area.address}</div>` : ''}
                </div>
            `);
            
            // Add tooltip
            circle.bindTooltip(`${buildingName} - ${radius}m border area`, {
                permanent: false,
                direction: 'top'
            });
            
            buildingAreaCircles.addLayer(circle);
        }
    });
    
    // Add circles layer to map if buildings are visible
    if (buildingAreaCircles && map.hasLayer(buildingsLayer) && !map.hasLayer(buildingAreaCircles)) {
        map.addLayer(buildingAreaCircles);
    }
}

// Toggle Button Logic
document.getElementById('toggle-buildings').addEventListener('click', function () {
    const btn = this;
    const isActive = btn.classList.contains('active');

    if (isActive) {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Show Buildings';
        if (map.hasLayer(buildingsLayer)) {
            map.removeLayer(buildingsLayer);
        }
        // Also hide building area circles
        if (buildingAreaCircles && map.hasLayer(buildingAreaCircles)) {
            map.removeLayer(buildingAreaCircles);
        }
    } else {
        btn.classList.add('active');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i> Hide Buildings';
        initBuildingsLayer();
        // Building area circles are added in initBuildingsLayer()
    }
});


// Create a marker for a building
function createBuildingMarker(building) {
    const buildingIcon = L.icon({
        iconUrl: getBuildingIcon(building.building_type),
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
    });
    
    const marker = L.marker([building.latitude, building.longitude], {
        icon: buildingIcon,
        riseOnHover: true
    });
    
    // Add compact popup (no View Details button)
    marker.bindPopup(`
        <div class="building-popup">
            <div class="building-popup-title">${building.building_name || 'Unnamed Building'}</div>
            <div class="building-popup-address">${building.address || 'Address not available'}</div>
            <div class="building-popup-meta">Type: ${building.building_type || 'Unknown'} • Floors: ${typeof building.total_floors !== 'undefined' ? building.total_floors : 'N/A'}</div>
        </div>
    `);
    
    // Add tooltip
    marker.bindTooltip(building.building_name, {
        permanent: false,
        direction: 'top'
    });
    
    // Open modal with stats on marker click
    marker.on('click', function() {
        fetchBuildingDetails(building.id);
    });
    
    return marker;
}

// Add event listener for the "View Details" button
document.addEventListener('click', function(event) {
    // Check if the clicked element is the "View Details" button
    if (event.target && event.target.classList.contains('view-building-details')) {
        const buildingId = event.target.getAttribute('data-id');
        
        // Fetch building details based on the ID
        fetchBuildingDetails(buildingId);
    }
});

// Removed duplicate fetchBuildingDetails function - using consolidated version below

// Populate the modal with building details (buildings table only)
function populateBuildingModalWithStats(payload) {
    const content = document.getElementById('building-details-content');
    if (!content) return;

    const b = payload.building || {};

    // Format date helper
    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        try {
            return new Date(dateString).toLocaleDateString();
        } catch (e) {
            return dateString;
        }
    };

    content.innerHTML = `
        <!-- Building Header -->
        <div class="building-details-section mb-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h5 class="fw-bold mb-1" style="background-color: #dc3545; color: white; padding: 8px 12px; border-radius: 5px; display: inline-block;">
                        ${b.building_name || 'Building'}
                    </h5>
                    <div class="mt-2">
                        <span class="badge bg-secondary fs-6">${b.building_type || 'N/A'}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Basic Information -->
        <div class="border rounded p-3 building-details-section mb-3">
            <h6 class="fw-semibold mb-3">Basic Information</h6>
            <div class="row g-2">
                <div class="col-12">
                    <p class="mb-2"><strong>Address:</strong> ${b.address || 'N/A'}</p>
                </div>
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Contact Person:</strong> ${b.contact_person || 'N/A'}</p>
                </div>
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Contact Number:</strong> ${b.contact_number || 'N/A'}</p>
                </div>
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Total Floors:</strong> ${b.total_floors || 'N/A'}</p>
                </div>
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Building Area:</strong> ${b.building_area ? b.building_area + ' m²' : 'N/A'}</p>
                </div>
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Construction Year:</strong> ${b.construction_year || 'N/A'}</p>
                </div>
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Last Inspected:</strong> ${formatDate(b.last_inspected)}</p>
                </div>
            </div>
        </div>

        <!-- Location Information -->
        <div class="border rounded p-3 building-details-section mb-3">
            <h6 class="fw-semibold mb-3">Location Information</h6>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Latitude:</strong> ${b.latitude || 'N/A'}</p>
                </div>
                <div class="col-12 col-md-6">
                    <p class="mb-2"><strong>Longitude:</strong> ${b.longitude || 'N/A'}</p>
                </div>
                ${b.barangay_id ? `<div class="col-12"><p class="mb-2"><strong>Barangay ID:</strong> ${b.barangay_id}</p></div>` : ''}
                ${b.geo_fence_id ? `<div class="col-12"><p class="mb-2"><strong>Geo Fence ID:</strong> ${b.geo_fence_id}</p></div>` : ''}
            </div>
        </div>

        <!-- Safety Features -->
        <div class="border rounded p-3 building-details-section mb-3">
            <h6 class="fw-semibold mb-3">Safety Features</h6>
            <div class="d-flex flex-wrap gap-2">
                ${b.has_sprinkler_system ? '<span class="badge bg-success fs-6">Sprinkler System</span>' : ''}
                ${b.has_fire_alarm ? '<span class="badge bg-success fs-6">Fire Alarm</span>' : ''}
                ${b.has_fire_extinguishers ? '<span class="badge bg-success fs-6">Fire Extinguishers</span>' : ''}
                ${b.has_emergency_exits ? '<span class="badge bg-success fs-6">Emergency Exits</span>' : ''}
                ${b.has_emergency_lighting ? '<span class="badge bg-success fs-6">Emergency Lighting</span>' : ''}
                ${b.has_fire_escape ? '<span class="badge bg-success fs-6">Fire Escape</span>' : ''}
            </div>
            ${!b.has_sprinkler_system && !b.has_fire_alarm && !b.has_fire_extinguishers && 
              !b.has_emergency_exits && !b.has_emergency_lighting && !b.has_fire_escape 
              ? '<p class="text-muted mt-2 mb-0">No safety features recorded</p>' : ''}
        </div>

        <!-- Additional Information -->
        <div class="border rounded p-3 building-details-section">
            <h6 class="fw-semibold mb-3">Additional Information</h6>
            <div class="row g-2">
                ${b.user_id ? `<div class="col-12 col-md-6"><p class="mb-2"><strong>User ID:</strong> ${b.user_id}</p></div>` : ''}
                ${b.device_id ? `<div class="col-12 col-md-6"><p class="mb-2"><strong>Device ID:</strong> ${b.device_id}</p></div>` : ''}
                <div class="col-12">
                    <p class="mb-0"><strong>Created At:</strong> ${b.created_at ? new Date(b.created_at).toLocaleString() : 'N/A'}</p>
                </div>
            </div>
        </div>
    `;
    
    const modalElement = document.getElementById('buildingDetailsModal');
    if (!modalElement) {
        console.error('Building details modal element not found');
        return;
    }
    
    // Try to get or create Bootstrap modal instance
    let modal = null;
    
    // Check if bootstrap is available and has Modal
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        try {
            // Try to get existing instance (Bootstrap 5)
            if (typeof bootstrap.Modal.getInstance === 'function') {
                modal = bootstrap.Modal.getInstance(modalElement);
            }
            
            // If no instance exists, create a new one
            if (!modal) {
                modal = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
            }
            
            // Show the modal
            if (modal && typeof modal.show === 'function') {
                modal.show();
            } else {
                // Fallback: manually show modal
                modalElement.classList.add('show');
                modalElement.style.display = 'block';
                document.body.classList.add('modal-open');
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
        } catch (e) {
            console.error('Error initializing Bootstrap modal:', e);
            // Fallback: manually show modal
            modalElement.classList.add('show');
            modalElement.style.display = 'block';
            document.body.classList.add('modal-open');
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    } else {
        // Bootstrap not available, use manual method
        console.warn('Bootstrap Modal not available, using fallback method');
        modalElement.classList.add('show');
        modalElement.style.display = 'block';
        document.body.classList.add('modal-open');
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    }
}


// Get appropriate icon for building type
function getBuildingIcon(buildingType) {
    const icons = {
        'Residential': 'https://cdn-icons-png.flaticon.com/512/619/619153.png',
        'Commercial': './images/commercial.png',
        'Industrial': './images/industrial.png',
        'Institutional': './images/institutional.png',
    };
    
    return icons[buildingType] || 'https://cdn-icons-png.flaticon.com/512/619/619153.png';
}

// Helper function to safely hide modal
function hideModal(modalElement) {
    if (!modalElement) return;
    
    // Try Bootstrap modal hide method
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        try {
            if (typeof bootstrap.Modal.getInstance === 'function') {
                const bootstrapModal = bootstrap.Modal.getInstance(modalElement);
                if (bootstrapModal && typeof bootstrapModal.hide === 'function') {
                    bootstrapModal.hide();
                    return;
                }
            }
        } catch (e) {
            console.warn('Error using Bootstrap modal hide:', e);
        }
    }
    
    // Fallback: manually hide modal
    modalElement.classList.remove('show');
    modalElement.style.display = 'none';
    document.body.classList.remove('modal-open');
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) backdrop.remove();
}

// Initialize building details modal
function initBuildingDetailsModal() {
    console.log('Initializing building details modal...');
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-building-details')) {
            const buildingId = e.target.getAttribute('data-id');
            fetchBuildingDetails(buildingId);
        }
    });
    
    // Add explicit modal close event listeners
    const modal = document.getElementById('buildingDetailsModal');
    if (modal) {
        // Close button in header
        const closeBtn = modal.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                console.log('Close button clicked');
                hideModal(modal);
            });
        }
        
        // Close button in footer
        const footerCloseBtn = modal.querySelector('.modal-footer .btn');
        if (footerCloseBtn) {
            footerCloseBtn.addEventListener('click', function() {
                hideModal(modal);
            });
        }
        
        // Close on backdrop click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideModal(modal);
            }
        });
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                hideModal(modal);
            }
        });
    }
}

// Fetch building details via fetch API (consolidated function)
function fetchBuildingDetails(buildingId) {
    const url = `get_building_stats.php?id=${buildingId}`;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
        signal: AbortSignal.timeout(10000) // 10 second timeout
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            populateBuildingModalWithStats(data);
        } else {
            showAlert('danger', 'Failed to fetch building details');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Error fetching building details');
    });
}

// Show building details modal
function showBuildingModal(building) {
    Swal.fire({
        title: building.building_name,
        html: `
            <div class="text-start">
                <p><strong>Type:</strong> ${building.building_type}</p>
                <p><strong>Address:</strong> ${building.address}</p>
                <p><strong>Contact:</strong> ${building.contact_person} (${building.contact_number})</p>
                <p><strong>Floors:</strong> ${building.total_floors}</p>
                <p><strong>Area:</strong> ${building.building_area} sqm</p>
                <p><strong>Constructed:</strong> ${building.construction_year}</p>
                
                <h5 class="mt-3">Safety Features</h5>
                <ul>
                    <li>${building.has_sprinkler_system ? '✅' : '❌'} Sprinkler System</li>
                    <li>${building.has_fire_alarm ? '✅' : '❌'} Fire Alarm</li>
                    <li>${building.has_fire_extinguishers ? '✅' : '❌'} Fire Extinguishers</li>
                    <li>${building.has_emergency_exits ? '✅' : '❌'} Emergency Exits</li>
                    <li>${building.has_emergency_lighting ? '✅' : '❌'} Emergency Lighting</li>
                    <li>${building.has_fire_escape ? '✅' : '❌'} Fire Escape</li>
                </ul>
                
                <p class="text-muted"><small>Last inspected: ${building.last_inspected || 'Never'}</small></p>
            </div>
        `,
        confirmButtonText: 'Close',
        showCloseButton: true,
        width: '600px'
    });
}

// Global speech control variables
let speechEnabled = true;
let currentUtterance = null;
let lastSpokenText = '';

// Initialize speech synthesis
function initSpeechSynthesis() {
    console.log('Initializing speech synthesis...');
    
    // Initialize speech variables
    speechEnabled = true;
    currentUtterance = null;
    lastSpokenText = '';
    
    // Make variables available globally
    window.speechEnabled = speechEnabled;
    window.currentUtterance = currentUtterance;
    window.lastSpokenText = lastSpokenText;
    
    if ('speechSynthesis' in window) {
        console.log('Speech synthesis is supported');
        
        // Wait for voices to be loaded
        let voices = speechSynthesis.getVoices();
        if (voices.length === 0) {
            speechSynthesis.onvoiceschanged = function() {
                voices = speechSynthesis.getVoices();
                console.log('Speech synthesis voices loaded:', voices.length);
            };
        } else {
            console.log('Speech synthesis voices available:', voices.length);
        }
        
        // Test speech synthesis
        const testUtterance = new SpeechSynthesisUtterance('');
        testUtterance.onstart = function() {
            console.log('Speech synthesis is working');
            window.speechSynthesis.cancel(); // Cancel the test
        };
        testUtterance.onerror = function(event) {
            console.warn('Speech synthesis test failed:', event.error);
        };
        
        // Try to speak an empty string to test
        try {
            window.speechSynthesis.speak(testUtterance);
        } catch (error) {
            console.warn('Speech synthesis not available:', error);
        }
        
        console.log('Speech synthesis initialized successfully');
    } else {
        console.warn('Speech synthesis not supported in this browser');
    }
}

// Initialize event listeners
function initEventListeners() {
    // Route buttons
    const routeToStationBtn = document.getElementById('routeToStation');
    const clearRouteBtn = document.getElementById('clearRoute');
    const locateEmergencyBtn = document.getElementById('locate-emergency');
    
    if (routeToStationBtn) {
        routeToStationBtn.addEventListener('click', showRouteToStation);
    } else {
        console.warn('RouteToStation button not found');
    }
    
    if (clearRouteBtn) {
        clearRouteBtn.addEventListener('click', clearRoute);
    } else {
        console.warn('ClearRoute button not found');
    }
    
    if (locateEmergencyBtn) {
        locateEmergencyBtn.addEventListener('click', locateEmergency);
    } else {
        console.warn('LocateEmergency button not found');
    }
    
    // Speech controls removed - automatic speech only
    console.log('Speech controls removed - automatic speech enabled for all browsers');
    
    // No double-click functionality - RouteToStation only
    
    // Filter legend
    document.querySelectorAll('.filter-legend').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const status = this.getAttribute('data-status');
            filterFiresByStatus(status);
            
            // Update active state
            document.querySelectorAll('.filter-legend').forEach(el => el.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Refresh button
    const refreshBtn = document.getElementById('refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', fetchFireData);
    } else {
        console.warn('Refresh button not found');
    }
    // View on map buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-on-map')) {
            const lat = parseFloat(e.target.getAttribute('data-lat'));
            const lng = parseFloat(e.target.getAttribute('data-lng'));
            map.flyTo([lat, lng], 18);
        }
    });
    
    // No keyboard shortcuts - RouteToStation only
}

// Controlled polling functions
function startControlledPolling() {
    if (isPollingActive) {
        console.log('Polling already active, skipping start');
        return;
    }
    
    isPollingActive = true;
    console.log('Starting controlled polling with interval:', config.updateInterval + 'ms');
    
    // Clear any existing interval
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    // Start new controlled interval
    pollingInterval = setInterval(() => {
        fetchFireData();
    }, config.updateInterval);
}

function stopControlledPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
    isPollingActive = false;
    console.log('Polling stopped');
}

// Fetch fire data from API
function fetchFireData() {
    // Reduce console logging - only log errors and important events
    const url = `${config.apiEndpoint}?user_id=${config.userId}`;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
        signal: AbortSignal.timeout(15000) // 15 second timeout for main data
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            allFireData = data.data;
            updateDashboard(data.data);
            updateTime();
            // Reduced logging - only log on first load or status changes
            if (!window.dataLoaded) {
                console.log('Initial data loaded successfully');
                window.dataLoaded = true;
            }
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error("Error fetching fire data:", error);
        // Use fallback data instead of showing error
        useFallbackFireData();
    });
}

// Fallback fire data function
function useFallbackFireData() {
    console.log("Using fallback fire data");
    
    // Sample fallback data
    const fallbackData = [
        {
            id: 1,
            building_id: 1,
            temp: 75,
            smoke: 25,
            heat: 30,
            flame_detected: 0,
            status: 'SAFE',
            timestamp: new Date().toISOString(),
            geo_lat: 10.525467693871333,
            geo_long: 122.84123838118607,
            building_name: 'Sample Building 1',
            building_type: 'Commercial',
            address: '123 Main Street, Bago City'
        },
        {
            id: 2,
            building_id: 2,
            temp: 85,
            smoke: 150,
            heat: 180,
            flame_detected: 1,
            status: 'EMERGENCY',
            timestamp: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
            geo_lat: 10.530000000000000,
            geo_long: 122.845000000000000,
            building_name: 'Sample Building 2',
            building_type: 'Residential',
            address: '456 Oak Avenue, Bago City'
        },
        {
            id: 3,
            building_id: 3,
            temp: 65,
            smoke: 45,
            heat: 50,
            flame_detected: 0,
            status: 'MONITORING',
            timestamp: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
            geo_lat: 10.520000000000000,
            geo_long: 122.840000000000000,
            building_name: 'Sample Building 3',
            building_type: 'Industrial',
            address: '789 Industrial Road, Bago City'
        },
        {
            id: 4,
            building_id: 4,
            temp: 70,
            smoke: 20,
            heat: 25,
            flame_detected: 0,
            status: 'SAFE',
            timestamp: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
            geo_lat: 10.535000000000000,
            geo_long: 122.850000000000000,
            building_name: 'Sample Building 4',
            building_type: 'Educational',
            address: '321 School Street, Bago City'
        }
    ];
    
    allFireData = fallbackData;
    updateDashboard(fallbackData);
    updateTime();
    
    // Show a subtle info message instead of error
    showAlert('info', 'Using sample data - system is operational', true);
}

// Update dashboard with new data
function updateDashboard(fireData) {
    // Update counts
    const counts = {
        "SAFE": 0,
        "MONITORING": 0,
        "ACKNOWLEDGED": 0,
        "EMERGENCY": 0
    };
    
    fireData.forEach(fire => {
        const status = fire.status.toUpperCase();
        if (counts.hasOwnProperty(status)) {
            counts[status]++;
        }
    });
    
    // Safely update dashboard elements with null checks
    const safeCountEl = document.getElementById('safe-count');
    const monitoringCountEl = document.getElementById('monitoring-count');
    const acknowledgedCountEl = document.getElementById('acknowledged-count');
    const emergencyCountEl = document.getElementById('emergency-count');
    const allCountEl = document.getElementById('all-count');
    const alertCountEl = document.getElementById('alert-count');
    
    if (safeCountEl) safeCountEl.textContent = counts.SAFE;
    if (monitoringCountEl) monitoringCountEl.textContent = counts.MONITORING;
    if (acknowledgedCountEl) acknowledgedCountEl.textContent = counts.ACKNOWLEDGED;
    if (emergencyCountEl) emergencyCountEl.textContent = counts.EMERGENCY;
    if (allCountEl) allCountEl.textContent = fireData.length;
    if (alertCountEl) alertCountEl.textContent = counts.EMERGENCY;
    
    // Update map with new data (with null check)
    const activeFilter = document.querySelector('.filter-legend.active');
    if (activeFilter && activeFilter.getAttribute('data-status')) {
        filterFiresByStatus(activeFilter.getAttribute('data-status'));
    } else {
        // Default to 'all' if no active filter
        filterFiresByStatus('all');
    }
}

// Filter fires by status
function filterFiresByStatus(status) {
    // Check if map and fireMarkers are initialized
    if (!map || !fireMarkers) {
        console.log('Map or fireMarkers not initialized, skipping filter');
        return;
    }
    
    // Clear existing layers
    try {
        map.removeLayer(fireMarkers);
        if (heatLayer) map.removeLayer(heatLayer);
    } catch (error) {
        console.log('Error removing layers:', error);
    }
    
    fireMarkers.clearLayers();
    const heatData = [];
    let emergencyCount = 0;
    
    // Process each fire incident
    if (allFireData && Array.isArray(allFireData)) {
        allFireData.forEach(fire => {
            if (status === 'all' || fire.status === status) {
                try {
                    // Create marker
                    const marker = createFireMarker(fire);
                    fireMarkers.addLayer(marker);
                    
                    // Add to heatmap data
                    if (fire.heat > 0) {
                        const intensity = Math.min(fire.heat / 100, 1);
                        heatData.push([fire.geo_lat, fire.geo_long, intensity]);
                    }
                    
                    // Count emergencies for alert
                    if (fire.status === 'Emergency') emergencyCount++;
                } catch (error) {
                    console.log('Error processing fire data:', error);
                }
            }
        });
    }
}

// Create a fire marker
function createFireMarker(fire) {
    const statusClass = fire.status.toLowerCase().replace(/\s+/g, '-');
    const iconUrl = getIconForStatus(fire.status);
    
    const fireIcon = L.icon({
        iconUrl: iconUrl,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32],
        className: 'fire-icon'
    });
    
    const marker = L.marker([fire.geo_lat, fire.geo_long], { 
        icon: fireIcon,
        riseOnHover: true
    });
    
    // Add popup with fire details
    marker.bindPopup(`
        <div class="fire-popup">
            <h5 class="fw-bold text-${statusClass}">${fire.status}</h5>
            <p class="mb-1"><i class="bi bi-thermometer-sun me-2"></i>Heat: ${fire.heat}°C</p>
            <p class="mb-1"><i class="bi bi-thermometer-high me-2"></i>Temp: ${fire.temp}°C</p>
            <p class="mb-1"><i class="bi bi-cloud-fog me-2"></i>Smoke: ${fire.smoke}</p>
            <p class="mb-1"><i class="bi bi-fire me-2"></i>Flame: ${fire.flame_detected ? 'Detected' : 'Not detected'}</p>
            <p class="mb-2"><i class="bi bi-clock me-2"></i>${new Date(fire.timestamp).toLocaleString()}</p>
            <div class="d-grid gap-2">
                <button class="btn btn-sm btn-outline-${statusClass} calculate-distance" 
                        data-lat="${fire.geo_lat}" data-lng="${fire.geo_long}">
                    <i class="bi bi-rulers me-1"></i> Calculate Distance
                </button>
            </div>
        </div>
    `);

    marker.bindTooltip(`${fire.status} - ${fire.temp}°C`, {
        permanent: false,
        direction: 'top',
        className: `fire-tooltip-${statusClass}`
    });
    
    return marker;
}

// Get appropriate icon for status
function getIconForStatus(status) {
    const icons = {
        'Safe': 'https://cdn-icons-png.flaticon.com/512/1828/1828640.png',
        'Monitoring': 'https://cdn-icons-png.flaticon.com/512/3523/3523096.png',
        'Acknowledged': 'https://cdn-icons-png.flaticon.com/512/599/599502.png',
        'Emergency': 'https://cdn-icons-png.flaticon.com/512/599/599508.png'
    };
    return icons[status] || 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png';
}
// Text-to-Speech functionality
function speakText(text, priority = 'normal') {
    console.log('speakText called with:', text, 'priority:', priority);
    
    // Always enable speech for automatic operation
    speechEnabled = true;
    
    if ('speechSynthesis' in window) {
        console.log('Speech synthesis is available');
        
        // Cancel any ongoing speech
        window.speechSynthesis.cancel();
        
        const utterance = new SpeechSynthesisUtterance(text);
        currentUtterance = utterance;
        lastSpokenText = text; // Store the last spoken text
        
        // Configure speech settings for cross-browser compatibility
        utterance.rate = 0.85; // Slightly slower for better clarity across browsers
        utterance.pitch = 1.0;
        utterance.volume = 1.0;
        
        // Cross-browser voice selection
        const voices = speechSynthesis.getVoices();
        console.log('Available voices:', voices.length);
        
        // Try to find the best voice for each browser
        let selectedVoice = null;
        
        // For Opera GX and other browsers, try different voice selection strategies
        if (navigator.userAgent.includes('OPR') || navigator.userAgent.includes('Opera')) {
            // Opera GX specific voice selection
            selectedVoice = voices.find(voice => 
                voice.lang.startsWith('en') && (voice.name.includes('Google') || voice.name.includes('Microsoft'))
            ) || voices.find(voice => voice.lang.startsWith('en')) || voices[0];
        } else if (navigator.userAgent.includes('Chrome')) {
            // Chrome specific voice selection
            selectedVoice = voices.find(voice => 
                voice.lang.startsWith('en') && voice.name.includes('Google')
            ) || voices.find(voice => voice.lang.startsWith('en')) || voices[0];
        } else if (navigator.userAgent.includes('Firefox')) {
            // Firefox specific voice selection
            selectedVoice = voices.find(voice => 
                voice.lang.startsWith('en') && voice.name.includes('Mozilla')
            ) || voices.find(voice => voice.lang.startsWith('en')) || voices[0];
        } else {
            // Default voice selection for other browsers
            selectedVoice = voices.find(voice => voice.lang.startsWith('en')) || voices[0];
        }
        
        if (selectedVoice) {
            utterance.voice = selectedVoice;
            console.log('Selected voice:', selectedVoice.name, 'for browser:', navigator.userAgent);
        }
        
        // Higher priority for emergency messages
        if (priority === 'high') {
            utterance.rate = 0.75; // Slower for emergency messages
            utterance.volume = 1.0;
        }
        
        // Add event listeners for better control
        utterance.onstart = function() {
            console.log('Speech started:', text);
        };
        
        utterance.onend = function() {
            currentUtterance = null;
            console.log('Speech ended');
        };
        
        utterance.onerror = function(event) {
            console.error('Speech error:', event.error);
            currentUtterance = null;
            
            // Retry with different settings for Opera GX
            if (navigator.userAgent.includes('OPR') || navigator.userAgent.includes('Opera')) {
                console.log('Retrying speech for Opera GX...');
                setTimeout(() => {
                    const retryUtterance = new SpeechSynthesisUtterance(text);
                    retryUtterance.rate = 0.8;
                    retryUtterance.volume = 1.0;
                    window.speechSynthesis.speak(retryUtterance);
                }, 100);
            }
        };
        
        try {
            window.speechSynthesis.speak(utterance);
            console.log('Speech synthesis speak() called successfully');
        } catch (error) {
            console.error('Error calling speechSynthesis.speak():', error);
            
            // Fallback for Opera GX
            if (navigator.userAgent.includes('OPR') || navigator.userAgent.includes('Opera')) {
                console.log('Using fallback method for Opera GX...');
                setTimeout(() => {
                    try {
                        const fallbackUtterance = new SpeechSynthesisUtterance(text);
                        fallbackUtterance.rate = 0.8;
                        fallbackUtterance.volume = 1.0;
                        window.speechSynthesis.speak(fallbackUtterance);
                    } catch (fallbackError) {
                        console.error('Fallback also failed:', fallbackError);
                    }
                }, 200);
            }
        }
        
        // Log for debugging
        console.log('Speaking:', text);
    } else {
        console.warn('Speech synthesis not supported in this browser');
    }
}

// Make speakText available globally
window.speakText = speakText;

// Test function for locate device functionality
window.testLocateDevice = function() {
    console.log('Testing locate device functionality...');
    try {
        locateEmergency();
        console.log('Locate device test initiated successfully');
    } catch (error) {
        console.error('Error in locate device test:', error);
        showAlert('danger', 'Locate device test failed: ' + error.message);
    }
};

// Function to generate complete route directions text (ALL instructions)
function generateCompleteRouteDirections(route, buildingName, status, distanceKm, durationFormatted) {
    const statusText = status === 'EMERGENCY' ? 'Emergency' : 
                      status === 'ACKNOWLEDGED' ? 'Acknowledged' : 'Pre-dispatch';
    
    let directionsText = `Complete route to ${buildingName}. Status: ${statusText}. `;
    directionsText += `Total distance: ${distanceKm} kilometers. Estimated travel time: ${durationFormatted}. `;
    
    // Add ALL turn-by-turn directions (no limit)
    if (route.instructions && route.instructions.length > 0) {
        directionsText += 'Complete turn-by-turn directions: ';
        route.instructions.forEach((instruction, index) => {
            const cleanInstruction = instruction.text.replace(/<[^>]*>/g, ''); // Remove HTML tags
            const distance = instruction.distance ? ` in ${Math.round(instruction.distance)} meters` : '';
            directionsText += `${index + 1}. ${cleanInstruction}${distance}. `;
        });
        directionsText += 'You have reached your destination.';
    } else {
        directionsText += 'No detailed directions available. Please follow the visual route on the map.';
    }
    
    return directionsText;
}

// Function to generate route directions text (enhanced with more instructions)
function generateRouteDirections(route, buildingName, status, distanceKm, durationFormatted) {
    const statusText = status === 'EMERGENCY' ? 'Emergency' : 
                      status === 'ACKNOWLEDGED' ? 'Acknowledged' : 'Pre-dispatch';
    
    let directionsText = `Route to ${buildingName}. Status: ${statusText}. `;
    directionsText += `Distance: ${distanceKm} kilometers. Estimated travel time: ${durationFormatted}. `;
    
    // Add turn-by-turn directions (increased limit to 10 for better coverage)
    if (route.instructions && route.instructions.length > 0) {
        directionsText += 'Directions: ';
        route.instructions.forEach((instruction, index) => {
            if (index < 10) { // Increased limit to 10 instructions
                const cleanInstruction = instruction.text.replace(/<[^>]*>/g, ''); // Remove HTML tags
                const distance = instruction.distance ? ` in ${Math.round(instruction.distance)} meters` : '';
                directionsText += `${index + 1}. ${cleanInstruction}${distance}. `;
            }
        });
        if (route.instructions.length > 10) {
            directionsText += `And ${route.instructions.length - 10} more instructions. Please follow the visual route for complete directions.`;
        }
    }
    
    return directionsText;
}

// Function to generate detailed turn-by-turn directions (enhanced)
function generateDetailedDirections(route) {
    if (!route.instructions || route.instructions.length === 0) {
        return 'No detailed directions available.';
    }
    
    let detailedText = 'Detailed turn-by-turn directions: ';
    route.instructions.forEach((instruction, index) => {
        if (index < 15) { // Increased limit to 15 instructions for detailed view
            const cleanInstruction = instruction.text.replace(/<[^>]*>/g, ''); // Remove HTML tags
            const distance = instruction.distance ? ` in ${Math.round(instruction.distance)} meters` : '';
            detailedText += `${index + 1}. ${cleanInstruction}${distance}. `;
        }
    });
    
    if (route.instructions.length > 15) {
        detailedText += `And ${route.instructions.length - 15} more instructions. Please follow the visual route for complete directions.`;
    }
    
    return detailedText;
}

// Function to generate basic directions for non-emergency situations
function generateBasicDirections(route) {
    if (!route.instructions || route.instructions.length === 0) {
        return 'No directions available. Please follow the visual route on the map.';
    }
    
    let basicText = 'Basic route directions: ';
    route.instructions.forEach((instruction, index) => {
        if (index < 5) { // Keep basic directions short
            const cleanInstruction = instruction.text.replace(/<[^>]*>/g, ''); // Remove HTML tags
            basicText += `${index + 1}. ${cleanInstruction}. `;
        }
    });
    
    if (route.instructions.length > 5) {
        basicText += `And ${route.instructions.length - 5} more instructions. `;
    }
    
    basicText += 'Please follow the visual route on the map for complete directions.';
    return basicText;
}

// Function to speak complete route directions (ALL instructions)
function speakCompleteRouteDirections(route, buildingName, status, distanceKm, durationFormatted) {
    const completeDirections = generateCompleteRouteDirections(route, buildingName, status, distanceKm, durationFormatted);
    speakText(completeDirections, status === 'EMERGENCY' ? 'high' : 'normal');
}

// Function to speak route summary (enhanced)
function speakRouteSummary(buildingName, status, distanceKm, durationFormatted) {
    const statusText = status === 'EMERGENCY' ? 'Emergency' : 
                      status === 'ACKNOWLEDGED' ? 'Acknowledged' : 'Pre-dispatch';
    
    const summaryText = `Route calculated to ${buildingName}. Status is ${statusText}. ` +
                       `Total distance is ${distanceKm} kilometers. ` +
                       `Estimated travel time is ${durationFormatted}. ` +
                       `Complete turn-by-turn directions will be provided.`;
    
    speakText(summaryText, status === 'EMERGENCY' ? 'high' : 'normal');
}

// Test function for speech synthesis (can be called from browser console)
function testSpeechSynthesis() {
    const testText = "Text-to-speech functionality is working correctly. This is a test message for the fire detection system route directions.";
    speakText(testText, 'normal');
    console.log('Speech test initiated. Check if you can hear the audio.');
    showAlert('info', 'Speech test initiated. Check audio output.', false);
}

// Function to test route button with speech
function testRouteButton() {
    console.log('Testing route button with speech...');
    speakText('Testing route button functionality. This will simulate clicking the RouteToStation button.', 'normal');
    setTimeout(() => {
        showRouteToStation();
    }, 2000);
}

// Function to force enable speech and test
function forceEnableSpeech() {
    speechEnabled = true;
    console.log('Speech forcefully enabled');
    speakText('Speech has been forcefully enabled and is now working.', 'normal');
}

// Function to check speech status
function checkSpeechStatus() {
    const status = {
        speechEnabled: speechEnabled,
        speechSynthesisSupported: 'speechSynthesis' in window,
        voicesAvailable: speechSynthesis.getVoices().length,
        currentUtterance: currentUtterance ? 'Active' : 'None'
    };
    console.log('Speech Status:', status);
    return status;
}

// Emergency fix function for speech issues
function emergencySpeechFix() {
    console.log('Running emergency speech fix...');
    
    // Reset all speech variables
    speechEnabled = true;
    currentUtterance = null;
    lastSpokenText = '';
    
    // Cancel any ongoing speech
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
    }
    
    // Test speech immediately
    speakText('Emergency fix completed. Speech should now work.', 'normal');
    
    // Update UI if buttons exist
    const toggleSpeechBtn = document.getElementById('toggleSpeech');
    if (toggleSpeechBtn) {
        const icon = toggleSpeechBtn.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-volume-up me-1';
        }
        toggleSpeechBtn.classList.remove('btn-outline-warning');
        toggleSpeechBtn.classList.add('btn-outline-info');
    }
    
    console.log('Emergency fix completed. Speech enabled:', speechEnabled);
}

// RouteToStation function only - no test functions

function showRouteToStation() {
    console.log('RouteToStation button clicked!');
    
    // Always enable speech for automatic operation
    speechEnabled = true;
    console.log('Speech enabled:', speechEnabled);
    
    // Provide immediate audio feedback that route calculation is starting
    console.log('Speaking: Calculating route to emergency location. Please wait.');
    speakText('Calculating route to emergency location. Please wait.', 'normal');
    
    fetchMostRecentCritical()
        .then(criticalBuilding => {
            if (!criticalBuilding) {
                showAlert('info', 'No fire_data with EMERGENCY or ACKNOWLEDGED status found for routing.');
                speakText('No emergency fire incidents available for routing.', 'normal');
                clearRoute();
                return;
            }

            // We already have the single most recent critical status (EMERGENCY OR ACKNOWLEDGED)
            const latestBuilding = criticalBuilding;

            // Since we're specifically fetching critical statuses, we know it's valid
            if (!['EMERGENCY', 'ACKNOWLEDGED'].includes(latestBuilding.status.toUpperCase())) {
                console.log('Fire data status is not critical.');
                const statusMessage = `The fire incident status is ${latestBuilding.status}. Route calculation requires EMERGENCY or ACKNOWLEDGED status.`;
                showAlert('info', statusMessage);
                speakText(statusMessage, 'normal');
                clearRoute();
                return;
            }

            // Use fire_data GPS coordinates (latitude/longitude or geo_lat/geo_long)
            const latitude = latestBuilding.latitude || latestBuilding.geo_lat;
            const longitude = latestBuilding.longitude || latestBuilding.geo_long;
            const building_name = latestBuilding.building_name || `Device ${latestBuilding.device_name || latestBuilding.device_id || 'Unknown'}`;
            const address = latestBuilding.address || 'Location from fire_data GPS coordinates';
            const { status } = latestBuilding;
            console.log('Building found at:', latitude, longitude, 'Status:', status);

            // Check if device is within a building's 100m area
            const areaCheck = isWithinBuildingArea(latitude, longitude);
            if (!areaCheck.within) {
                const errorMessage = `Cannot calculate route: ${areaCheck.message}. Device must be within 100 meters of a building to be located.`;
                showAlert('warning', errorMessage);
                speakText(errorMessage, 'normal');
                clearRoute();
                return;
            }

            clearRoute();
            if (emergencyMarker) {
                map.removeLayer(emergencyMarker);
                emergencyMarker = null;
            }

            // Determine styling based on status
            const statusUpper = status.toUpperCase();
            const routeColor = statusUpper === 'EMERGENCY' ? '#d90429' : 
                              statusUpper === 'ACKNOWLEDGED' ? '#ffc107' : 
                              '#fd7e14'; // PRE-DISPATCH color

            // Create custom colored marker based on status
            const customIcon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background-color: ${routeColor}; 
                    width: 24px; 
                    height: 24px; 
                    border-radius: 50%; 
                    border: 3px solid white; 
                    box-shadow: 0 0 10px rgba(0,0,0,0.5);
                    display: flex; 
                    align-items: center; 
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                    font-size: 14px;
                ">${statusUpper === 'EMERGENCY' ? 'E' : 'A'}</div>`,
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            emergencyMarker = L.marker([latitude, longitude], { icon: customIcon }).addTo(map);

            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(latitude, longitude),
                    L.latLng(config.fireStation.lat, config.fireStation.lng)
                ],
                routeWhileDragging: false,
                showAlternatives: false,
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: 'smart',
                lineOptions: {
                    styles: [{ color: routeColor, weight: 5, opacity: 0.8 }]
                },
                createMarker: () => null
            }).addTo(map);

            routingControl.on('routesfound', function (e) {
                const route = e.routes[0];
                const distanceMeters = route.summary.totalDistance;
                const durationSeconds = route.summary.totalTime;

                const distanceKm = (distanceMeters / 1000).toFixed(2);
                const durationFormatted = formatDuration(durationSeconds);

                if (emergencyMarker) {
                    // Build detailed popup content from fire_data with route info (async)
                    const routeInfo = {
                        distance: distanceKm,
                        eta: durationFormatted
                    };
                    buildDetailedFireDataPopup(latestBuilding, routeColor, new Date(latestBuilding.timestamp).toLocaleString(), routeInfo).then(popupContent => {
                        emergencyMarker.bindPopup(popupContent, { maxWidth: 650 }).openPopup();
                    });
                }
                


                showAlert(
                    statusUpper === 'EMERGENCY' ? 'danger' : 
                    statusUpper === 'ACKNOWLEDGED' ? 'warning' : 'info',
                    `<strong>Building Status:</strong> ${statusUpper}<br>
                    <strong>Distance:</strong> ${distanceKm} km<br>
                    <strong>Estimated Time:</strong> ${durationFormatted}`
                );
                
                // Provide immediate audio confirmation that route was found
                speakText(`Route found to ${building_name}. Status is ${statusUpper}.`, 'normal');
                
                // Speak route summary after a short delay
                setTimeout(() => {
                    speakRouteSummary(building_name, statusUpper, distanceKm, durationFormatted);
                }, 1500); // 1.5 second delay
                
                // After a longer delay, speak COMPLETE route directions (ALL instructions)
                setTimeout(() => {
                    speakCompleteRouteDirections(route, building_name, statusUpper, distanceKm, durationFormatted);
                }, 3500); // 3.5 second delay for complete directions
            });

            map.fitBounds([
                [latitude, longitude],
                [config.fireStation.lat, config.fireStation.lng]
            ]);
        })
        .catch(error => {
            console.error("Error fetching buildings:", error);
            // Use fallback data instead of showing error
            useFallbackRouteData();
        });
}

// Keep all other functions exactly the same
function formatDuration(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);

    let result = '';
    if (h > 0) result += `${h}h `;
    if (m > 0) result += `${m}m `;
    if (s > 0 || (!h && !m)) result += `${s}s`;

    return result.trim();
}

function clearRoute() {
    if (routingControl) {
        map.removeControl(routingControl);
        routingControl = null;
    }
    
    // Remove emergency marker if it exists
    if (emergencyMarker) {
        map.removeLayer(emergencyMarker);
        emergencyMarker = null;
    }
    

    
    // Provide audio feedback when route is cleared
    if (speechEnabled) {
        speakText('Route cleared. No active directions.', 'normal');
    }
}

let emergencyMarker = null;

function locateEmergency() {
    console.log('locateEmergency: Function called');
    fetchMostRecentCritical()
        .then(criticalBuilding => {
            console.log('locateEmergency: Got critical building:', criticalBuilding);
            const locateBtn = document.getElementById('locateEmergencyBtn');
            
            if (!criticalBuilding) {
                showAlert('info', 'No fire_data with EMERGENCY or ACKNOWLEDGED status found. All fire incidents are currently safe.');
                return;
            }

            if (locateBtn) locateBtn.disabled = false;
            if (emergencyMarker) map.removeLayer(emergencyMarker);

            // Use fire_data GPS coordinates (latitude/longitude or geo_lat/geo_long)
            const latitude = criticalBuilding.latitude || criticalBuilding.geo_lat;
            const longitude = criticalBuilding.longitude || criticalBuilding.geo_long;
            const building_name = criticalBuilding.building_name || `Device ${criticalBuilding.device_name || criticalBuilding.device_id || 'Unknown'}`;
            const address = criticalBuilding.address || 'Location from fire_data GPS coordinates';
            const { status, timestamp } = criticalBuilding;
            
            // Check if device is within a building's 100m area
            const areaCheck = isWithinBuildingArea(latitude, longitude);
            if (!areaCheck.within) {
                const errorMessage = `Cannot locate device: ${areaCheck.message}. Device must be within 100 meters of a building to be located.`;
                showAlert('warning', errorMessage);
                speakText(errorMessage, 'normal');
                return;
            }
            
            // Format timestamp for display
            const criticalTime = new Date(timestamp).toLocaleString();
            
            map.flyTo([latitude, longitude], 16);

            // Determine marker color and alert type based on status
            const isEmergency = status.toUpperCase() === 'EMERGENCY';
            const markerColor = isEmergency ? '#d90429' : '#ffc107'; // RED for EMERGENCY, YELLOW for ACKNOWLEDGED
            const alertType = isEmergency ? 'danger' : 'warning';
            const statusIcon = isEmergency ? '🚨' : '⚠️';

            // Create custom colored marker based on status
            const customIcon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background-color: ${markerColor}; 
                    width: 20px; 
                    height: 20px; 
                    border-radius: 50%; 
                    border: 3px solid white; 
                    box-shadow: 0 0 10px rgba(0,0,0,0.5);
                    display: flex; 
                    align-items: center; 
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                    font-size: 12px;
                ">${isEmergency ? 'E' : 'A'}</div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            // Create marker first
            emergencyMarker = L.marker([latitude, longitude], { icon: customIcon })
                .addTo(map)
                .bindPopup('Loading device information...', { maxWidth: 650 })
                .openPopup();
            
            // Build detailed popup content from fire_data (async) and update popup
            buildDetailedFireDataPopup(criticalBuilding, markerColor, criticalTime).then(popupContent => {
                if (emergencyMarker) {
                    emergencyMarker.setPopupContent(popupContent);
                }
            });

            showAlert(alertType, `${statusIcon} MOST RECENT: Fire incident at "${building_name}" is in ${status.toUpperCase()} status since ${criticalTime}`);
            
            // Speak the critical alert
            const speechText = isEmergency ? 
                `Most recent fire alert! Incident at ${building_name} is in emergency status. Location: ${address}` :
                `Most recent fire alert! Incident at ${building_name} is in acknowledged status. Location: ${address}`;
            
            speakText(speechText, isEmergency ? 'high' : 'normal');
        })
        .catch(error => {
            console.error("Error fetching critical building:", error);
            console.error("Error details:", {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            
            // Check if it's a jQuery-related error
            if (error.message.includes('$ is not defined') || error.message.includes('jQuery')) {
                console.error('jQuery error detected - this should not happen with native fetch API');
                showAlert('danger', 'System error: jQuery dependency issue. Please refresh the page.');
            } else {
                showAlert('warning', 'Error fetching critical data. Please try again.');
            }
        });
}

function fetchMostRecentCritical() {
    return new Promise((resolve, reject) => {
        console.log('fetchMostRecentCritical: Starting fetch request...');
        const url = 'get_most_recent_critical.php?t=' + new Date().getTime();
        console.log('fetchMostRecentCritical: URL:', url);
        
        // Use native fetch API instead of jQuery
        fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            // Add timeout using AbortController
            signal: AbortSignal.timeout(10000) // 10 second timeout
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Critical data response:', data);
            if (data.success && data.data) {
                // Ensure we have the required fields
                if (data.data.latitude && data.data.longitude) {
                    resolve(data.data);
                } else {
                    console.warn('Critical data missing coordinates:', data.data);
                    resolve(null);
                }
            } else {
                console.log('No critical status found:', data.message);
                resolve(null);
            }
        })
        .catch(error => {
            console.error('Error fetching critical data:', error);
            console.error('Error details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            
            // Check if it's a network error or file not found
            if (error.message.includes('Failed to fetch') || error.message.includes('404')) {
                console.warn('Critical data endpoint not available, using fallback data');
                // Use fallback data instead of rejecting
                resolve(null);
            } else {
                reject(new Error('Failed to fetch critical data: ' + error.message));
            }
        });
    });
}

// Function to fetch device locations
function fetchDeviceLocations(deviceId = null, buildingId = null, emergencyOnly = false) {
    return new Promise((resolve, reject) => {
        let url = 'get_device_location.php?t=' + new Date().getTime();
        
        if (deviceId) {
            url += '&device_id=' + deviceId;
        } else if (buildingId) {
            url += '&building_id=' + buildingId;
        } else if (emergencyOnly) {
            url += '&emergency_only=true';
        }
        
        // Use native fetch API instead of jQuery
        fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            signal: AbortSignal.timeout(10000) // 10 second timeout
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Device location response:', data);
            if (data.success) {
                resolve(data.data);
            } else {
                console.warn('No device location data:', data.message);
                resolve(null);
            }
        })
        .catch(error => {
            console.error('Error fetching device locations:', error);
            reject(new Error('Failed to fetch device locations: ' + error.message));
        });
    });
}

// Keep the old function for backward compatibility but rename it
function fetchEmergencyBuildings() {
    return new Promise((resolve, reject) => {
        const url = 'get_emergency_buildings.php?t=' + new Date().getTime();
        
        // Use native fetch API instead of jQuery
        fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            signal: AbortSignal.timeout(10000) // 10 second timeout
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            resolve(data);
        })
        .catch(error => {
            console.error('Error fetching emergency buildings:', error);
            reject(error);
        });
    });
}

// Fallback emergency data function
function useFallbackEmergencyData() {
    console.log("Using fallback emergency data");
    
    const fallbackEmergencyData = [
        {
            id: 1,
            building_name: 'Sample Emergency Building',
            address: '123 Emergency Street, Bago City',
            status: 'EMERGENCY',
            timestamp: new Date().toISOString(),
            latitude: 10.530000000000000,
            longitude: 122.845000000000000
        }
    ];
    
    // Process the fallback data as if it came from the server
    const locateBtn = document.getElementById('locateEmergencyBtn');
    const latestBuilding = fallbackEmergencyData[0];
    
    if (locateBtn) locateBtn.disabled = false;
    if (emergencyMarker) map.removeLayer(emergencyMarker);
    
    const { latitude, longitude, building_name, address, status } = latestBuilding;
    map.flyTo([latitude, longitude], 16);
    
    emergencyMarker = L.marker([latitude, longitude])
        .addTo(map)
        .bindPopup(`<b>${building_name}</b><br>${address}<br><span style="color:${status.toUpperCase() === 'EMERGENCY' ? 'red' : 'orange'}">${status.toUpperCase()}</span>`)
        .openPopup();
    
    showAlert('info', 'Using sample emergency data - system is operational');
}

// Fallback route data function
function useFallbackRouteData() {
    console.log("Using fallback route data");
    
    // Create a sample route to the fire station
    const sampleRoute = {
        routes: [{
            summary: 'Sample route to fire station',
            legs: [{
                steps: [
                    { maneuver: { instruction: 'Head south on Main Street' } },
                    { maneuver: { instruction: 'Continue straight for 500 meters' } },
                    { maneuver: { instruction: 'Turn left onto Dreyfus Street' } },
                    { maneuver: { instruction: 'Continue on Dreyfus Street for 800 meters' } },
                    { maneuver: { instruction: 'Turn right onto Station Road' } },
                    { maneuver: { instruction: 'Fire Station will be on your right' } }
                ]
            }]
        }]
    };
    
    const buildingName = 'Sample Building';
    const status = 'EMERGENCY';
    const distanceKm = '2.5';
    const durationFormatted = '4 minutes';
    
    // Generate and speak complete route directions
    const completeRouteDirections = generateCompleteRouteDirections(sampleRoute, buildingName, status, distanceKm, durationFormatted);
    speakText(completeRouteDirections, 'emergency');
    
    // Show route on map
    if (routingControl) {
        map.removeControl(routingControl);
    }
    
    // Create a simple route line to the fire station
    const routeLine = L.polyline([
        [10.530000000000000, 122.845000000000000], // Sample building location
        [config.fireStation.lat, config.fireStation.lng] // Fire station location
    ], { color: 'red', weight: 5, opacity: 0.7 }).addTo(map);
    
    // Fit map to show the route
    map.fitBounds([
        [10.530000000000000, 122.845000000000000],
        [config.fireStation.lat, config.fireStation.lng]
    ]);
    
    showAlert('info', 'Using sample route data - system is operational');
}

// Note: locate-emergency event listener is already set up in initEventListeners()




// Show alert notification
function showAlert(type, message, autoClose = true) {
    const icon = {
        success: 'success',
        danger: 'error',
        warning: 'warning',
        info: 'info'
    }[type] || 'info';
    
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: autoClose ? 3000 : undefined,
        timerProgressBar: autoClose,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    
    Toast.fire({
        icon: icon,
        title: message
    });
}

// Update last updated time
function updateTime() {
    const now = new Date();
    const updateTimeEl = document.getElementById('update-time');
    if (updateTimeEl) {
        updateTimeEl.textContent = now.toLocaleTimeString();
    }
}

// Calculate distance between two points (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Calculate fire status based on sensor readings
function calculateFireStatusFromSensors(temp, smoke, heat, flameDetected) {
    const thresholds = config.statusThresholds;
    
    // If flame is detected, it's always Emergency
    if (flameDetected == 1) {
        return { status: 'EMERGENCY', color: '#d90429', priority: 4 };
    }
    
    // Check thresholds (highest priority status wins)
    if (smoke >= thresholds.Emergency.smoke || temp >= thresholds.Emergency.temp || heat >= thresholds.Emergency.heat) {
        return { status: 'EMERGENCY', color: '#d90429', priority: 4 };
    }
    
    if (smoke >= thresholds['Pre-Dispatch'].smoke || temp >= thresholds['Pre-Dispatch'].temp || heat >= thresholds['Pre-Dispatch'].heat) {
        return { status: 'PRE-DISPATCH', color: '#fd7e14', priority: 3 };
    }
    
    if (smoke >= thresholds.Monitoring.smoke || temp >= thresholds.Monitoring.temp || heat >= thresholds.Monitoring.heat) {
        return { status: 'MONITORING', color: '#0dcaf0', priority: 2 };
    }
    
    return { status: 'SAFE', color: '#28a745', priority: 1 };
}

// Reverse geocoding function to convert coordinates to address
// Using PHP proxy to avoid CORS issues with Nominatim API
async function getAddressFromCoordinates(lat, lng, retryCount = 0) {
    if (!lat || !lng || isNaN(lat) || isNaN(lng)) {
        console.log('Invalid coordinates:', lat, lng);
        return null;
    }
    
    // Validate coordinates are within reasonable bounds
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        console.log('Coordinates out of bounds:', lat, lng);
        return null;
    }
    
    // Check for zero coordinates (invalid GPS data)
    if (Math.abs(lat) < 0.000001 && Math.abs(lng) < 0.000001) {
        console.log('Zero coordinates detected');
        return null;
    }
    
    try {
        // Use PHP proxy to avoid CORS issues
        const latNum = parseFloat(lat);
        const lngNum = parseFloat(lng);
        
        console.log('Fetching address for coordinates:', latNum, lngNum);
        
        // Add delay to avoid rate limiting (Nominatim allows 1 request per second)
        if (retryCount > 0) {
            await new Promise(resolve => setTimeout(resolve, 1000 * retryCount));
        }
        
        // Use PHP proxy endpoint (relative to current page URL)
        const url = `reverse_geocode.php?lat=${latNum}&lng=${lngNum}`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            signal: AbortSignal.timeout(10000) // 10 second timeout
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Geocoding API error:', response.status, errorText);
            
            // Retry on rate limit (429) or server error (5xx)
            if ((response.status === 429 || response.status >= 500) && retryCount < 2) {
                console.log(`Retrying geocoding (attempt ${retryCount + 1})...`);
                return await getAddressFromCoordinates(lat, lng, retryCount + 1);
            }
            
            throw new Error(`Geocoding API error: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Geocoding response:', data);
        
        // Check if request was successful
        if (data.success === false) {
            console.error('Geocoding failed:', data.error);
            return null;
        }
        
        // Prioritize formatted address from proxy
        if (data && data.address) {
            console.log('Using formatted address:', data.address);
            return data.address;
        }
        
        // Fallback to display_name if available
        if (data && data.display_name) {
            console.log('Using display_name for full address:', data.display_name);
            return data.display_name;
        }
        
        // Fallback: Build comprehensive address from raw components if available
        if (data && data.raw) {
            const addr = data.raw;
            // Build comprehensive readable address from components
            let addressParts = [];
            
            // Add house number and road/street
            if (addr.house_number && addr.road) {
                addressParts.push(addr.house_number + ' ' + addr.road);
            } else if (addr.road) {
                addressParts.push(addr.road);
            } else if (addr.house_number) {
                addressParts.push(addr.house_number);
            }
            
            // Add barangay (very important for Philippines addresses)
            if (addr.barangay) addressParts.push(addr.barangay);
            
            // Add village/neighborhood (common in Philippines)
            if (addr.village) addressParts.push(addr.village);
            else if (addr.neighbourhood) addressParts.push(addr.neighbourhood);
            else if (addr.suburb) addressParts.push(addr.suburb);
            
            // Add city/municipality
            if (addr.city) addressParts.push(addr.city);
            else if (addr.municipality) addressParts.push(addr.municipality);
            else if (addr.town) addressParts.push(addr.town);
            
            // Add state/province
            if (addr.state) addressParts.push(addr.state);
            else if (addr.province) addressParts.push(addr.province);
            else if (addr.region) addressParts.push(addr.region);
            
            // Add postal code if available
            if (addr.postcode) addressParts.push(addr.postcode);
            
            // Add country
            if (addr.country) addressParts.push(addr.country);
            
            // If we have address parts, return formatted address
            if (addressParts.length > 0) {
                const formattedAddress = addressParts.join(', ');
                console.log('Formatted address from parts:', formattedAddress);
                return formattedAddress;
            }
        }
        
        console.log('No address found in response');
        return null;
    } catch (error) {
        console.error('Reverse geocoding error:', error);
        
        // Retry on network errors
        if (retryCount < 2 && (error.name === 'AbortError' || error.message.includes('fetch'))) {
            console.log(`Retrying geocoding after error (attempt ${retryCount + 1})...`);
            await new Promise(resolve => setTimeout(resolve, 2000 * (retryCount + 1)));
            return await getAddressFromCoordinates(lat, lng, retryCount + 1);
        }
        
        return null;
    }
}

// Build detailed popup content from fire_data
async function buildDetailedFireDataPopup(fireData, statusColor, formattedTime, routeInfo = null) {
    const deviceName = fireData.device_name || `Device ${fireData.device_id || 'Unknown'}`;
    const deviceNumber = fireData.device_number || 'N/A';
    const serialNumber = fireData.serial_number || 'N/A';
    const deviceStatus = fireData.device_status || 'Unknown';
    
    // Get building name - try from fireData first, then fetch if building_id exists
    let buildingName = fireData.building_name || null;
    if (!buildingName && fireData.building_id) {
        // Try to get building name from config.buildings array
        if (config.buildings && config.buildings.length > 0) {
            const building = config.buildings.find(b => b.id == fireData.building_id);
            if (building) {
                buildingName = building.building_name;
            }
        }
    }
    buildingName = buildingName || 'N/A';
    
    const buildingType = fireData.building_type || 'N/A';
    const address = fireData.address || 'Location from fire_data GPS coordinates';
    
    // Sensor readings - convert to numbers for calculations
    const temp = fireData.temp !== null && fireData.temp !== undefined ? parseFloat(fireData.temp) : 0;
    const smoke = fireData.smoke !== null && fireData.smoke !== undefined ? parseFloat(fireData.smoke) : 0;
    const heat = fireData.heat !== null && fireData.heat !== undefined ? parseFloat(fireData.heat) : 0;
    const flameDetected = fireData.flame_detected == 1 ? 'Yes' : 'No';
    const flameDetectedNum = fireData.flame_detected == 1 ? 1 : 0;
    
    // Calculate fire status from sensor readings
    const calculatedStatus = calculateFireStatusFromSensors(temp, smoke, heat, flameDetectedNum);
    const fireStatus = fireData.status || calculatedStatus.status;
    const fireStatusColor = calculatedStatus.color;
    
    // GPS coordinates - prioritize gps_latitude/gps_longitude
    const gpsLat = fireData.gps_latitude || fireData.latitude || fireData.geo_lat || null;
    const gpsLon = fireData.gps_longitude || fireData.longitude || fireData.geo_long || null;
    const gpsAlt = fireData.gps_altitude || fireData.altitude || 'N/A';
    
    // Get reverse geocoded address from GPS coordinates
    let reverseGeocodedAddress = 'Loading address...';
    if (gpsLat && gpsLon && !isNaN(gpsLat) && !isNaN(gpsLon)) {
        try {
            console.log('Starting reverse geocoding for:', gpsLat, gpsLon);
            const geocodedAddr = await getAddressFromCoordinates(parseFloat(gpsLat), parseFloat(gpsLon));
            
            if (geocodedAddr) {
                reverseGeocodedAddress = geocodedAddr;
                console.log('Successfully geocoded address:', geocodedAddr);
            } else {
                reverseGeocodedAddress = 'Address not available from geocoding service';
                console.warn('Geocoding returned null for coordinates:', gpsLat, gpsLon);
            }
        } catch (error) {
            console.error('Reverse geocoding error:', error);
            reverseGeocodedAddress = 'Address lookup failed: ' + error.message;
        }
    } else {
        reverseGeocodedAddress = 'Coordinates not available';
        console.warn('Invalid coordinates for geocoding:', gpsLat, gpsLon);
    }
    
    // ML/AI predictions
    const mlConfidence = fireData.ml_confidence !== null && fireData.ml_confidence !== undefined ? 
        parseFloat(fireData.ml_confidence).toFixed(2) + '%' : 'N/A';
    const mlPrediction = fireData.ml_prediction == 1 ? 'Fire Detected' : 'No Fire';
    const mlFireProbability = fireData.ml_fire_probability !== null && fireData.ml_fire_probability !== undefined ? 
        (parseFloat(fireData.ml_fire_probability) * 100).toFixed(2) + '%' : 'N/A';
    const aiPrediction = fireData.ai_prediction || 'N/A';
    const mlTimestamp = fireData.ml_timestamp ? new Date(fireData.ml_timestamp).toLocaleString() : 'N/A';
    
    // Other info
    const fireDataId = fireData.fire_data_id || fireData.id || 'N/A';
    const userId = fireData.user_id || 'N/A';
    const buildingId = fireData.building_id || 'N/A';
    const barangayId = fireData.barangay_id || 'N/A';
    const notified = fireData.notified == 1 ? 'Yes' : 'No';
    const acknowledgedTime = fireData.acknowledged_at_time || 'N/A';
    const gpsTime = fireData.gps_time ? new Date(fireData.gps_time).toLocaleString() : 'N/A';
    
    return `
        <div class="container-fluid" style="min-width: 400px; max-width: 500px; max-height: 500px; overflow-y: auto; overflow-x: hidden;">
            <form class="needs-validation" novalidate>
                <div class="mb-1 p-1 rounded text-center" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-1" style="font-size: 0.7rem;">
                        <span class="badge px-3 py-1" style="background-color: ${statusColor}; font-size: 1rem; font-weight: bold; letter-spacing: 0.5px;">${fireData.status.toUpperCase()}</span>
                        <span style="font-weight: 600;"><i class="bi bi-device-hdd me-1"></i>${deviceName}</span>
                    </div>
                    <div style="font-size: 0.65rem; font-weight: 500;">
                        <i class="bi bi-clock me-1"></i>${formattedTime}
                    </div>
                </div>
                <div class="mb-1 p-1 rounded text-center" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                    <div class="text-white" style="font-size: 0.85rem; line-height: 1.3; word-wrap: break-word; font-weight: 500;">${reverseGeocodedAddress}</div>
                </div>
                <div class="mb-1 p-1" style="background-color: #f8f9fa; border-radius: 0.25rem;">
                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 700; color: #2c3e50; text-transform: uppercase;"><i class="bi bi-info-circle me-1" style="color: #3498db;"></i>Device Information</label>
                    <div class="row g-1">
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Name</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${deviceName}</div>
                        </div>
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Number</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${deviceNumber}</div>
                        </div>
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Serial</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${serialNumber}</div>
                        </div>
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Status</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${deviceStatus}</div>
                        </div>
                        <div class="col-12 mb-0">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Device ID</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${fireData.device_id || 'N/A'}</div>
                        </div>
                    </div>
                </div>
                <div class="mb-1 p-1" style="background-color: #f8f9fa; border-radius: 0.25rem;">
                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 700; color: #2c3e50; text-transform: uppercase;"><i class="bi bi-activity me-1" style="color: #e74c3c;"></i>Sensor Readings</label>
                    <div class="row g-1">
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Temperature</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${temp !== 0 ? temp + '°C' : '0°C'}</div>
                        </div>
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Smoke</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${smoke !== 0 ? smoke : '0'}</div>
                        </div>
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Heat</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${heat !== 0 ? heat + '°C' : '0°C'}</div>
                        </div>
                        <div class="col-6 mb-0">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Flame</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${flameDetected}</div>
                        </div>
                    </div>
                </div>
                <div class="mb-1 p-1" style="background-color: #f8f9fa; border-left: 3px solid ${fireStatusColor}; border-radius: 0.25rem; padding-left: 0.6rem !important;">
                    <label class="form-label mb-1" style="font-size: 0.75rem; font-weight: 700; color: #2c3e50; text-transform: uppercase;"><i class="bi bi-exclamation-triangle-fill me-1" style="color: ${fireStatusColor};"></i>Fire Status</label>
                    <div class="row g-1">
                        <div class="col-12 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Current Status</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${fireStatus}</div>
                        </div>
                        <div class="col-6 mb-1">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Database</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${fireData.status || 'N/A'}</div>
                        </div>
                        <div class="col-6 mb-0">
                            <div style="font-size: 0.5rem; color: #7f8c8d; margin-bottom: 0.15rem;">Calculated</div>
                            <div style="font-size: 0.55rem; color: #2c3e50; font-weight: 500;">${calculatedStatus.status}</div>
                        </div>
                    </div>
                </div>
                ${routeInfo ? `
                <div class="mb-0" style="border-top: 2px solid #3498db; padding-top: 0.5rem;">
                    <label class="form-label mb-1" style="font-size: 0.6rem; font-weight: 600; color: #2c3e50; text-transform: uppercase;"><i class="bi bi-route me-1" style="color: #3498db;"></i>Route Information</label>
                    <div class="row g-1">
                        <div class="col-6 mb-0">
                            <label class="form-label mb-0" style="font-size: 0.55rem; color: #7f8c8d;">Distance</label>
                            <div><span class="badge bg-info py-0 px-1" style="font-size: 0.6rem;">${routeInfo.distance} km</span></div>
                        </div>
                        <div class="col-6 mb-0">
                            <label class="form-label mb-0" style="font-size: 0.55rem; color: #7f8c8d;">ETA</label>
                            <div><span class="badge bg-primary py-0 px-1" style="font-size: 0.6rem;">${routeInfo.eta}</span></div>
                        </div>
                    </div>
                </div>
                ` : ''}
            </form>
        </div>
    `;
}

// Check if coordinates are within any building's 100m area
function isWithinBuildingArea(latitude, longitude) {
    if (!latitude || !longitude || isNaN(latitude) || isNaN(longitude)) {
        return { within: false, message: 'Invalid coordinates' };
    }
    
    if (!config.buildingAreas || config.buildingAreas.length === 0) {
        // If no building areas are defined, allow location (backward compatibility)
        return { within: true, message: 'No building areas defined' };
    }
    
    // Check each building area
    for (const area of config.buildingAreas) {
        if (!area.center_latitude || !area.center_longitude || !area.radius) {
            continue;
        }
        
        const centerLat = parseFloat(area.center_latitude);
        const centerLon = parseFloat(area.center_longitude);
        const radius = parseFloat(area.radius) || 100; // Default to 100m
        
        // Calculate distance in meters
        const distanceKm = calculateDistance(latitude, longitude, centerLat, centerLon);
        const distanceMeters = distanceKm * 1000;
        
        // Check if within radius (with small tolerance for floating point errors)
        if (distanceMeters <= radius + 0.1) {
            return { 
                within: true, 
                buildingName: area.building_name || 'Building',
                distance: distanceMeters.toFixed(1)
            };
        }
    }
    
    // Not within any building area
    return { 
        within: false, 
        message: 'Device location is outside all building 100m areas' 
    };
}

// Update user location on map
function updateUserLocation(lat, lng) {
    userLocation = { lat, lng };
    
    // Remove existing user marker
    if (userMarker) {
        map.removeLayer(userMarker);
    }
    
    // Create new user marker
    const userIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/2972/2972035.png',
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0, -30]
    });
    
    userMarker = L.marker([lat, lng], { 
        icon: userIcon,
        zIndexOffset: 1000 
    })
    .addTo(map);
}

// Send user location to server
function sendUserLocationToServer(userId, lat, lng) {
    fetch('update_user_location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: userId,
            latitude: lat,
            longitude: lng
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('User location updated successfully');
        } else {
            console.error('Failed to update user location:', data.message);
        }
    })
    .catch(error => {
        console.error('Error updating user location:', error);
    });
}


// WebSocket message handler (only if websocket is defined)
if (typeof websocket !== 'undefined' && websocket) {
    websocket.onmessage = function (event) {
        let message = JSON.parse(event.data);
        
        // Log the whole message to see if it's correct
        console.log('Received WebSocket message:', message);

        // Ensure the user_id is properly received
        if (message.user_id && !isNaN(message.user_id) && message.user_id !== 0) {
            console.log('Received valid user ID from WebSocket:', message.user_id);
            // Assuming latitude and longitude are available, send the user location
            if (typeof sendUserLocationToServer === 'function') {
                sendUserLocationToServer(message.user_id, message.latitude, message.longitude);
            }
        } else {
            console.error('Invalid or missing user_id in WebSocket message');
        }
    };
}




