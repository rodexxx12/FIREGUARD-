var alarmAudio = new Audio('alarm.mp3');
alarmAudio.loop = true;
var isAlarmPlaying = false;
var ws;
var reconnectAttempts = 0;
var maxReconnectAttempts = 5;
var reconnectDelay = 3000; // 3 seconds
var currentAlert = null;

// Connect WebSocket
function connectWebSocket() {
    ws = new WebSocket("ws://127.0.0.1:3000");

    ws.onopen = function() {
        console.log("Connected to WebSocket server!");
        reconnectAttempts = 0;
    };

    ws.onmessage = function(event) {
        try {
            var data = JSON.parse(event.data);
            console.log("Received:", data);
            handleStatusChange(data);
        } catch (e) {
            console.error("Error parsing WebSocket message:", e);
        }
    };

    ws.onerror = function(error) {
        console.error("WebSocket Error:", error);
    };

    ws.onclose = function() {
        console.warn("WebSocket connection closed");
        if (reconnectAttempts < maxReconnectAttempts) {
            reconnectAttempts++;
            console.log(`Attempting to reconnect (${reconnectAttempts}/${maxReconnectAttempts})...`);
            setTimeout(connectWebSocket, reconnectDelay);
        } else {
            console.error("Max reconnection attempts reached. Please refresh the page.");
        }
    };
}

// Handle status changes (from WebSocket or initial load)
function handleStatusChange(data) {
    if (!data || !data.status) return;
    
    if (data.status === "EMERGENCY") {
        // Only trigger if not already in emergency state
        if (!isAlarmPlaying || !currentAlert) {
            triggerEmergencyAlert(data);
        }
    } else {
        // If status changed to safe, stop any active alarms
        if (isAlarmPlaying || currentAlert) {
            stopAlarm();
        }
    }
}

// Trigger emergency alert and sound alarm
function triggerEmergencyAlert(data) {
    // Play alarm if not already playing
    if (!isAlarmPlaying) {
        alarmAudio.play().catch(e => {
            console.error("Failed to play alarm:", e);
            // Handle browsers that require user interaction first
            document.body.addEventListener('click', function firstClick() {
                alarmAudio.play();
                document.body.removeEventListener('click', firstClick);
            });
        });
        isAlarmPlaying = true;
    }
    
    // Show alert if not already showing
    if (!currentAlert) {
        showFireDetectionAlert(data);
    }
}

// Stop alarm and close any active alerts
function stopAlarm() {
    alarmAudio.pause();
    alarmAudio.currentTime = 0;
    isAlarmPlaying = false;
    if (currentAlert) {
        currentAlert.close();
        currentAlert = null;
    }
}

// Show SweetAlert modal with fire detection data
function showFireDetectionAlert(data) {
    var statusClass = data.status.toLowerCase().replace(" ", "-");
    var geoLat = data.geo_tag ? data.geo_tag.latitude : 'N/A';
    var geoLong = data.geo_tag ? data.geo_tag.longitude : 'N/A';

    currentAlert = Swal.fire({
        icon: 'error',
        title: '🔥 Fire Detection Alert',
        html: `  
            <strong>Status:</strong> <span class="status ${statusClass}">${data.status}</span><br>
            <strong>Building Type:</strong> ${data.building_type || 'N/A'}<br>
            <strong>Smoke Level:</strong> ${data.smoke || 'N/A'}<br>
            <strong>Temperature:</strong> ${data.temp || 'N/A'} °C<br>
            <strong>Heat Level:</strong> ${data.heat || 'N/A'}<br>
            <strong>Flame Detected:</strong> ${data.flame_detected ? 'Yes' : 'No'}<br>
        `,
        confirmButtonText: 'Silence Alarm',
        confirmButtonColor: '#dc3545',
        allowOutsideClick: false,
        backdrop: 'rgba(0,0,0,0.7)',
        didClose: () => {
            currentAlert = null;
            // Set a flag in localStorage to prevent alert from showing again on page reload
            localStorage.setItem('emergencyAlertShown', 'true');
        }
    }).then(() => {
        stopAlarm();
    });
}

// Initialize on page load
window.onload = function() {
    var initialStatus = "<?php echo $data['status'] ?? 'SAFE'; ?>";
    var initialData = <?php echo json_encode($data); ?>;
    
    // Check if emergency alert has already been shown in this session
    if (localStorage.getItem('emergencyAlertShown') !== 'true' && initialData && initialData.status === "EMERGENCY") {
        // Automatically trigger the emergency alert if the initial status is "EMERGENCY"
        triggerEmergencyAlert(initialData);
    }

    // Start WebSocket connection
    connectWebSocket();
};
// Configuration
const config = {
fireStation: { 
    lat: 10.525845456115054, 
    lng: 122.84103381660024, 
    name: "Bago City Fire Station",
    contact: "(034) 461-1234"
},
buildings: <?php echo json_encode($buildings); ?>,
apiEndpoint: "server.php",
updateInterval: 30000, // 30 seconds
heatmapRadius: 25,
heatmapBlur: 15,
statusThresholds: {
    Safe: { smoke: 20, temp: 30, heat: 30 },
    Monitoring: { smoke: 50, temp: 50, heat: 50 },
    'Pre-Dispatch': { smoke: 100, temp: 100, heat: 100 },
    Emergency: { smoke: 200, temp: 200, heat: 200 }
},
userId: <?= $_SESSION['user_id'] ?? 0 ?>,
mapboxAccessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw'
};

// Global variables
let map, fireMarkers, heatLayer, routingControl;
let allFireData = [];
let heatmapEnabled = false;
let clusteringEnabled = true;
let userLocation = null;
let userMarker = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initEventListeners();
    fetchFireData();
    initBuildingsLayer(); // Add this line
    initBuildingDetailsModal(); // Add this line
    setInterval(fetchFireData, config.updateInterval);
    
    // Get user location if permission was previously granted
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => updateUserLocation(position.coords.latitude, position.coords.longitude),
            error => console.log("Geolocation error:", error)
        );
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
function addFireStationMarker() {
    const fireStationIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/2972/2972035.png',
        iconSize: [40, 40],
        iconAnchor: [20, 40],
        popupAnchor: [0, -40]
    });
    
    L.marker([config.fireStation.lat, config.fireStation.lng], { 
        icon: fireStationIcon,
        zIndexOffset: 1000 
    })
    .addTo(map)
    .bindPopup(`
        <div class="text-center">
            <h5 class="fw-bold mb-2">${config.fireStation.name}</h5>
            <p class="mb-1"><i class="bi bi-telephone me-2"></i>${config.fireStation.contact}</p>
            <p class="mb-0"><i class="bi bi-geo-alt me-2"></i>${config.fireStation.lat.toFixed(6)}, ${config.fireStation.lng.toFixed(6)}</p>
        </div>
    `)
    .bindTooltip(config.fireStation.name, { 
        permanent: false, 
        direction: 'top',
        className: 'fw-bold' 
    })
    .openPopup();
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
} else {
btn.classList.add('active');
btn.innerHTML = '<i class="bi bi-eye me-1"></i> Hide Buildings';
initBuildingsLayer();
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

// Add popup with building details
marker.bindPopup(`
<div class="building-popup">
    <h5 class="fw-bold">${building.building_name}</h5>
    <p class="mb-1"><i class="bi bi-building me-2"></i>Type: ${building.building_type}</p>
    <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>${building.address}</p>
    <p class="mb-1"><i class="bi bi-person me-2"></i>Contact: ${building.contact_person || 'N/A'}</p>
    <p class="mb-1"><i class="bi bi-telephone me-2"></i>${building.contact_number || 'N/A'}</p>
    <p class="mb-1"><i class="bi bi-layers me-2"></i>Floors: ${building.total_floors}</p>
    <div class="mt-2">
        <h6 class="fw-bold">Safety Features:</h6>
        <div class="d-flex flex-wrap gap-1">
            ${building.has_sprinkler_system ? '<span class="badge bg-success">Sprinklers</span>' : ''}
            ${building.has_fire_alarm ? '<span class="badge bg-success">Alarm</span>' : ''}
            ${building.has_fire_extinguishers ? '<span class="badge bg-success">Extinguishers</span>' : ''}
            ${building.has_emergency_exits ? '<span class="badge bg-success">Exits</span>' : ''}
            ${building.has_emergency_lighting ? '<span class="badge bg-success">Lighting</span>' : ''}
            ${building.has_fire_escape ? '<span class="badge bg-success">Fire Escape</span>' : ''}
        </div>
    </div>
    <div class="mt-2">
        <button class="btn btn-sm btn-primary view-building-details" 
                data-id="${building.id}">
            <i class="bi bi-info-circle me-1"></i> View Details
        </button>
    </div>
</div>
`);

// Add tooltip
marker.bindTooltip(building.building_name, {
permanent: false,
direction: 'top'
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

// Fetch building details from the server or database
function fetchBuildingDetails(buildingId) {
// Here you can fetch the details from your backend or get the data from the config.
// For demonstration, we'll assume the data is in `config.buildings`.

const building = config.buildings.find(b => b.id == buildingId);

if (building) {
// Populate the modal with building data
populateBuildingModal(building);
} else {
console.error('Building not found with ID:', buildingId);
}
}

// Populate the modal with building details
function populateBuildingModal(building) {
const modalBody = document.querySelector('#buildingDetailsModal .modal-body');

// Clear existing content
modalBody.innerHTML = `
<h5 class="fw-bold">${building.building_name}</h5>
<p><strong>Type:</strong> ${building.building_type}</p>
<p><strong>Address:</strong> ${building.address}</p>
<p><strong>Contact Person:</strong> ${building.contact_person || 'N/A'}</p>
<p><strong>Contact Number:</strong> ${building.contact_number || 'N/A'}</p>
<p><strong>Total Floors:</strong> ${building.total_floors}</p>
<div class="mt-2">
    <h6 class="fw-bold">Safety Features:</h6>
    <div class="d-flex flex-wrap gap-1">
        ${building.has_sprinkler_system ? '<span class="badge bg-success">Sprinklers</span>' : ''}
        ${building.has_fire_alarm ? '<span class="badge bg-success">Alarm</span>' : ''}
        ${building.has_fire_extinguishers ? '<span class="badge bg-success">Extinguishers</span>' : ''}
        ${building.has_emergency_exits ? '<span class="badge bg-success">Exits</span>' : ''}
        ${building.has_emergency_lighting ? '<span class="badge bg-success">Lighting</span>' : ''}
        ${building.has_fire_escape ? '<span class="badge bg-success">Fire Escape</span>' : ''}
    </div>
</div>
`;

// Show the modal
const modal = new bootstrap.Modal(document.getElementById('buildingDetailsModal'));
modal.show();
}


// Get appropriate icon for building type
function getBuildingIcon(buildingType) {
const icons = {
'Residential': 'https://cdn-icons-png.flaticon.com/512/619/619153.png',
'Commercial': 'https://cdn-icons-png.flaticon.com/512/3069/3069172.png',
'Industrial': 'https://cdn-icons-png.flaticon.com/512/2458/2458441.png',
'Institutional': 'https://cdn-icons-png.flaticon.com/512/3079/3079151.png',
'Government': 'https://cdn-icons-png.flaticon.com/512/1570/1570887.png',
'Hospital': 'https://cdn-icons-png.flaticon.com/512/2968/2968828.png'
};

return icons[buildingType] || 'https://cdn-icons-png.flaticon.com/512/619/619153.png';
}

// Initialize building details modal
function initBuildingDetailsModal() {
document.addEventListener('click', function(e) {
if (e.target.classList.contains('view-building-details')) {
    const buildingId = e.target.getAttribute('data-id');
    fetchBuildingDetails(buildingId);
}
});
}

// Fetch building details via AJAX
function fetchBuildingDetails(buildingId) {
fetch(`get_building.php?id=${buildingId}`)
.then(response => response.json())
.then(data => {
    if (data.success) {
        showBuildingModal(data.building);
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

// Initialize event listeners
function initEventListeners() {
    // Route buttons
    document.getElementById('routeToStation').addEventListener('click', showRouteToStation);
    document.getElementById('clearRoute').addEventListener('click', clearRoute);
    document.getElementById('locate-emergency').addEventListener('click', locateEmergency);
    
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
    
    // Toggle buttons
    document.getElementById('heatmap-toggle').addEventListener('click', toggleHeatmap);
    document.getElementById('cluster-toggle').addEventListener('click', toggleClustering);
    
    // Refresh button
    document.getElementById('refresh-btn').addEventListener('click', fetchFireData);
    
    // View on map buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-on-map')) {
            const lat = parseFloat(e.target.getAttribute('data-lat'));
            const lng = parseFloat(e.target.getAttribute('data-lng'));
            map.flyTo([lat, lng], 18);
        }
    });
}

// Fetch fire data from API
function fetchFireData() {
    fetch(`${config.apiEndpoint}?user_id=${config.userId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                allFireData = data.data;
                updateDashboard(data.data);
                updateTime();
                showAlert('success', 'Data updated successfully', false);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            console.error("Error fetching fire data:", error);
            showAlert('danger', 'Failed to fetch fire data. Please try again.');
        });
}

// Update dashboard with new data
function updateDashboard(fireData) {
    // Update counts
    const counts = {
        "SAFE": 0,
        "MONITORING": 0,
        "PRE-DISPATCH": 0,
        "EMERGENCY": 0
    };
    
    fireData.forEach(fire => {
        const status = fire.status.toUpperCase();
        if (counts.hasOwnProperty(status)) {
            counts[status]++;
        }
    });
    
    document.getElementById('safe-count').textContent = counts.SAFE;
    document.getElementById('monitoring-count').textContent = counts.MONITORING;
    document.getElementById('predispatch-count').textContent = counts['PRE-DISPATCH'];
    document.getElementById('emergency-count').textContent = counts.EMERGENCY;
    document.getElementById('all-count').textContent = fireData.length;
    
    // Update map with new data
    filterFiresByStatus(document.querySelector('.filter-legend.active').getAttribute('data-status'));
    
    // Update alerts count
    const emergencyCount = counts.EMERGENCY;
    document.getElementById('alert-count').textContent = emergencyCount;
}

// Filter fires by status
function filterFiresByStatus(status) {
    // Clear existing layers
    map.removeLayer(fireMarkers);
    if (heatLayer) map.removeLayer(heatLayer);
    
    fireMarkers.clearLayers();
    const heatData = [];
    let emergencyCount = 0;
    
    // Process each fire incident
    allFireData.forEach(fire => {
        if (status === 'all' || fire.status === status) {
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
        }
    });
    
    // Add markers to map
    if (clusteringEnabled) {
        map.addLayer(fireMarkers);
    } else {
        fireMarkers.getLayers().forEach(layer => {
            map.addLayer(layer);
        });
    }
    
    // Add heatmap if enabled
    if (heatmapEnabled && heatData.length > 0) {
        heatLayer = L.heatLayer(heatData, {
            radius: config.heatmapRadius,
            blur: config.heatmapBlur,
            gradient: {0.4: 'blue', 0.6: 'lime', 0.7: 'yellow', 0.8: 'red'}
        }).addTo(map);
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
    
    // Add tooltip
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
        'Pre-Dispatch': 'https://cdn-icons-png.flaticon.com/512/599/599502.png',
        'Emergency': 'https://cdn-icons-png.flaticon.com/512/599/599508.png'
    };
    return icons[status] || 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png';
}

// Show route to fire station only if the newest status is "EMERGENCY"
function showRouteToStation() {
fetchEmergencyBuildings()
.then(buildings => {
    if (buildings.length === 0) {
        showAlert('warning', 'No buildings in emergency status.');
        return;
    }

    // Find the most recent building with "EMERGENCY" status
    const emergencyBuilding = buildings.find(b => b.status.toUpperCase() === 'EMERGENCY');

    if (!emergencyBuilding) {
        showAlert('info', 'No active emergency detected.');
        return;
    }

    const { latitude, longitude } = emergencyBuilding;

    // Clear existing route
    clearRoute();

    // Show route from emergency building to fire station
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
            styles: [{ color: '#d90429', weight: 5, opacity: 0.7 }]
        },
        createMarker: () => null,
        summaryTemplate: '<div class="text-center"><h5>Distance: {distance} km</h5><p>Estimated time: {time}</p></div>'
    }).addTo(map);

    map.fitBounds([
        [latitude, longitude],
        [config.fireStation.lat, config.fireStation.lng]
    ]);
})
.catch(error => {
    console.error("Error fetching emergency buildings:", error);
    showAlert('danger', 'Error locating emergency building.');
});
}

// Clear any existing route
function clearRoute() {
if (routingControl) {
map.removeControl(routingControl);
routingControl = null;
}
}

// Locate the building with "EMERGENCY" status on the map
function locateEmergency() {
fetchEmergencyBuildings()
.then(buildings => {
    const emergencyBuilding = buildings.find(b => b.status.toUpperCase() === 'EMERGENCY');
    const locateBtn = document.getElementById('locateEmergencyBtn');

    if (!emergencyBuilding) {
        showAlert('warning', 'No buildings in emergency status.');
        if (locateBtn) locateBtn.disabled = true;
        return;
    }

    if (locateBtn) locateBtn.disabled = false;

    const { latitude, longitude, building_name, address } = emergencyBuilding;

    map.flyTo([latitude, longitude], 16);

    L.marker([latitude, longitude])
        .addTo(map)
        .bindPopup(`<b>${building_name}</b><br>${address}`)
        .openPopup();

    showAlert('success', `Building "${building_name}" is in emergency.`);
})
.catch(error => {
    console.error("Error fetching emergency buildings:", error);
    showAlert('danger', 'Error locating emergency building.');
});
}

// Fetch emergency buildings from the database
function fetchEmergencyBuildings() {
return new Promise((resolve, reject) => {
// Mocking a server call (replace this with actual AJAX or API call to the backend)
const emergencyBuildings = []; // Array to store emergency buildings

// Simulate fetching data from the backend (replace this logic with actual AJAX/fetch)
// const query = `
//     SELECT b.id, b.building_name, b.address, b.latitude, b.longitude, f.status
//     FROM buildings b
//     JOIN fire_data f ON b.user_id = f.user_id
//     JOIN (
//         SELECT user_id, MAX(timestamp) AS latest_time
//         FROM fire_data
//         WHERE status IN ('EMERGENCY', 'SAFE', 'MONITORING', 'PRE-DISPATCH')  -- Filter statuses
//         GROUP BY user_id
//     ) latest ON latest.user_id = f.user_id AND f.timestamp = latest.latest_time
//     ORDER BY f.timestamp DESC
//     LIMIT 1
// `;

$.ajax({
    url: 'get_emergency_buildings.php',
    method: 'GET',
    success: function(data) {
        resolve(data);
    },
    error: function(error) {
        reject(error);
    }
});
});
}

// Button to locate emergency building(s)
document.getElementById('locate-emergency').addEventListener('click', function() {
locateEmergency();
});

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
    document.getElementById('update-time').textContent = now.toLocaleTimeString();
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


// WebSocket message handler (assuming you're using a WebSocket for real-time communication)
websocket.onmessage = function (event) {
let message = JSON.parse(event.data);

// Log the whole message to see if it's correct
console.log('Received WebSocket message:', message);

// Ensure the user_id is properly received
if (message.user_id && !isNaN(message.user_id) && message.user_id !== 0) {
console.log('Received valid user ID from WebSocket:', message.user_id);
// Assuming latitude and longitude are available, send the user location
sendUserLocationToServer(message.user_id, message.latitude, message.longitude);
} else {
console.error('Invalid or missing user_id in WebSocket message');
}
};
  var alarmAudio = new Audio('alarm.mp3');
        alarmAudio.loop = true;
        var isAlarmPlaying = false;
        var ws;
        var reconnectAttempts = 0;
        var maxReconnectAttempts = 5;
        var reconnectDelay = 3000; // 3 seconds
        var currentAlert = null;

        // Connect WebSocket
        function connectWebSocket() {
            ws = new WebSocket("ws://127.0.0.1:3000");

            ws.onopen = function() {
                console.log("Connected to WebSocket server!");
                reconnectAttempts = 0;
            };

            ws.onmessage = function(event) {
                try {
                    var data = JSON.parse(event.data);
                    console.log("Received:", data);
                    handleStatusChange(data);
                } catch (e) {
                    console.error("Error parsing WebSocket message:", e);
                }
            };

            ws.onerror = function(error) {
                console.error("WebSocket Error:", error);
            };

            ws.onclose = function() {
                console.warn("WebSocket connection closed");
                if (reconnectAttempts < maxReconnectAttempts) {
                    reconnectAttempts++;
                    console.log(`Attempting to reconnect (${reconnectAttempts}/${maxReconnectAttempts})...`);
                    setTimeout(connectWebSocket, reconnectDelay);
                } else {
                    console.error("Max reconnection attempts reached. Please refresh the page.");
                }
            };
        }

        // Handle status changes (from WebSocket or initial load)
        function handleStatusChange(data) {
            if (!data || !data.status) return;
            
            if (data.status === "EMERGENCY") {
                // Only trigger if not already in emergency state
                if (!isAlarmPlaying || !currentAlert) {
                    triggerEmergencyAlert(data);
                }
            } else {
                // If status changed to safe, stop any active alarms
                if (isAlarmPlaying || currentAlert) {
                    stopAlarm();
                }
            }
        }

        // Trigger emergency alert and sound alarm
        function triggerEmergencyAlert(data) {
            // Play alarm if not already playing
            if (!isAlarmPlaying) {
                alarmAudio.play().catch(e => {
                    console.error("Failed to play alarm:", e);
                    // Handle browsers that require user interaction first
                    document.body.addEventListener('click', function firstClick() {
                        alarmAudio.play();
                        document.body.removeEventListener('click', firstClick);
                    });
                });
                isAlarmPlaying = true;
            }
            
            // Show alert if not already showing
            if (!currentAlert) {
                showFireDetectionAlert(data);
            }
        }

        // Stop alarm and close any active alerts
        function stopAlarm() {
            alarmAudio.pause();
            alarmAudio.currentTime = 0;
            isAlarmPlaying = false;
            if (currentAlert) {
                currentAlert.close();
                currentAlert = null;
            }
        }

        // Show SweetAlert modal with fire detection data
        function showFireDetectionAlert(data) {
            var statusClass = data.status.toLowerCase().replace(" ", "-");
            var geoLat = data.geo_tag ? data.geo_tag.latitude : 'N/A';
            var geoLong = data.geo_tag ? data.geo_tag.longitude : 'N/A';

            currentAlert = Swal.fire({
                icon: 'error',
                title: '🔥 Fire Detection Alert',
                html: `  
                    <strong>Status:</strong> <span class="status ${statusClass}">${data.status}</span><br>
                    <strong>Building Type:</strong> ${data.building_type || 'N/A'}<br>
                    <strong>Smoke Level:</strong> ${data.smoke || 'N/A'}<br>
                    <strong>Temperature:</strong> ${data.temp || 'N/A'} °C<br>
                    <strong>Heat Level:</strong> ${data.heat || 'N/A'}<br>
                    <strong>Flame Detected:</strong> ${data.flame_detected ? 'Yes' : 'No'}<br>
                `,
                confirmButtonText: 'Silence Alarm',
                confirmButtonColor: '#dc3545',
                allowOutsideClick: false,
                backdrop: 'rgba(0,0,0,0.7)',
                didClose: () => {
                    currentAlert = null;
                    // Set a flag in localStorage to prevent alert from showing again on page reload
                    localStorage.setItem('emergencyAlertShown', 'true');
                }
            }).then(() => {
                stopAlarm();
            });
        }

        // Initialize on page load
        window.onload = function() {
            var initialStatus = "<?php echo $data['status'] ?? 'SAFE'; ?>";
            var initialData = <?php echo json_encode($data); ?>;
            
            // Check if emergency alert has already been shown in this session
            if (localStorage.getItem('emergencyAlertShown') !== 'true' && initialData && initialData.status === "EMERGENCY") {
                // Automatically trigger the emergency alert if the initial status is "EMERGENCY"
                triggerEmergencyAlert(initialData);
            }

            // Start WebSocket connection
            connectWebSocket();
        };
        // Configuration
    const config = {
        fireStation: { 
            lat: 10.525845456115054, 
            lng: 122.84103381660024, 
            name: "Bago City Fire Station",
            contact: "(034) 461-1234"
        },
        buildings: <?php echo json_encode($buildings); ?>,
        apiEndpoint: "server.php",
        updateInterval: 30000, // 30 seconds
        heatmapRadius: 25,
        heatmapBlur: 15,
        statusThresholds: {
            Safe: { smoke: 20, temp: 30, heat: 30 },
            Monitoring: { smoke: 50, temp: 50, heat: 50 },
            'Pre-Dispatch': { smoke: 100, temp: 100, heat: 100 },
            Emergency: { smoke: 200, temp: 200, heat: 200 }
        },
        userId: <?= $_SESSION['user_id'] ?? 0 ?>,
        mapboxAccessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw'
    };

        // Global variables
        let map, fireMarkers, heatLayer, routingControl;
        let allFireData = [];
        let heatmapEnabled = false;
        let clusteringEnabled = true;
        let userLocation = null;
        let userMarker = null;

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            initEventListeners();
            fetchFireData();
            initBuildingsLayer(); // Add this line
            initBuildingDetailsModal(); // Add this line
            setInterval(fetchFireData, config.updateInterval);
            
            // Get user location if permission was previously granted
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => updateUserLocation(position.coords.latitude, position.coords.longitude),
                    error => console.log("Geolocation error:", error)
                );
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
        function addFireStationMarker() {
            const fireStationIcon = L.icon({
                iconUrl: 'https://cdn-icons-png.flaticon.com/512/2972/2972035.png',
                iconSize: [40, 40],
                iconAnchor: [20, 40],
                popupAnchor: [0, -40]
            });
            
            L.marker([config.fireStation.lat, config.fireStation.lng], { 
                icon: fireStationIcon,
                zIndexOffset: 1000 
            })
            .addTo(map)
            .bindPopup(`
                <div class="text-center">
                    <h5 class="fw-bold mb-2">${config.fireStation.name}</h5>
                    <p class="mb-1"><i class="bi bi-telephone me-2"></i>${config.fireStation.contact}</p>
                    <p class="mb-0"><i class="bi bi-geo-alt me-2"></i>${config.fireStation.lat.toFixed(6)}, ${config.fireStation.lng.toFixed(6)}</p>
                </div>
            `)
            .bindTooltip(config.fireStation.name, { 
                permanent: false, 
                direction: 'top',
                className: 'fw-bold' 
            })
            .openPopup();
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
    } else {
        btn.classList.add('active');
        btn.innerHTML = '<i class="bi bi-eye me-1"></i> Hide Buildings';
        initBuildingsLayer();
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
    
    // Add popup with building details
    marker.bindPopup(`
        <div class="building-popup">
            <h5 class="fw-bold">${building.building_name}</h5>
            <p class="mb-1"><i class="bi bi-building me-2"></i>Type: ${building.building_type}</p>
            <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>${building.address}</p>
            <p class="mb-1"><i class="bi bi-person me-2"></i>Contact: ${building.contact_person || 'N/A'}</p>
            <p class="mb-1"><i class="bi bi-telephone me-2"></i>${building.contact_number || 'N/A'}</p>
            <p class="mb-1"><i class="bi bi-layers me-2"></i>Floors: ${building.total_floors}</p>
            <div class="mt-2">
                <h6 class="fw-bold">Safety Features:</h6>
                <div class="d-flex flex-wrap gap-1">
                    ${building.has_sprinkler_system ? '<span class="badge bg-success">Sprinklers</span>' : ''}
                    ${building.has_fire_alarm ? '<span class="badge bg-success">Alarm</span>' : ''}
                    ${building.has_fire_extinguishers ? '<span class="badge bg-success">Extinguishers</span>' : ''}
                    ${building.has_emergency_exits ? '<span class="badge bg-success">Exits</span>' : ''}
                    ${building.has_emergency_lighting ? '<span class="badge bg-success">Lighting</span>' : ''}
                    ${building.has_fire_escape ? '<span class="badge bg-success">Fire Escape</span>' : ''}
                </div>
            </div>
            <div class="mt-2">
                <button class="btn btn-sm btn-primary view-building-details" 
                        data-id="${building.id}">
                    <i class="bi bi-info-circle me-1"></i> View Details
                </button>
            </div>
        </div>
    `);
    
    // Add tooltip
    marker.bindTooltip(building.building_name, {
        permanent: false,
        direction: 'top'
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

// Fetch building details from the server or database
function fetchBuildingDetails(buildingId) {
    // Here you can fetch the details from your backend or get the data from the config.
    // For demonstration, we'll assume the data is in `config.buildings`.

    const building = config.buildings.find(b => b.id == buildingId);
    
    if (building) {
        // Populate the modal with building data
        populateBuildingModal(building);
    } else {
        console.error('Building not found with ID:', buildingId);
    }
}

// Populate the modal with building details
function populateBuildingModal(building) {
    const modalBody = document.querySelector('#buildingDetailsModal .modal-body');
    
    // Clear existing content
    modalBody.innerHTML = `
        <h5 class="fw-bold">${building.building_name}</h5>
        <p><strong>Type:</strong> ${building.building_type}</p>
        <p><strong>Address:</strong> ${building.address}</p>
        <p><strong>Contact Person:</strong> ${building.contact_person || 'N/A'}</p>
        <p><strong>Contact Number:</strong> ${building.contact_number || 'N/A'}</p>
        <p><strong>Total Floors:</strong> ${building.total_floors}</p>
        <div class="mt-2">
            <h6 class="fw-bold">Safety Features:</h6>
            <div class="d-flex flex-wrap gap-1">
                ${building.has_sprinkler_system ? '<span class="badge bg-success">Sprinklers</span>' : ''}
                ${building.has_fire_alarm ? '<span class="badge bg-success">Alarm</span>' : ''}
                ${building.has_fire_extinguishers ? '<span class="badge bg-success">Extinguishers</span>' : ''}
                ${building.has_emergency_exits ? '<span class="badge bg-success">Exits</span>' : ''}
                ${building.has_emergency_lighting ? '<span class="badge bg-success">Lighting</span>' : ''}
                ${building.has_fire_escape ? '<span class="badge bg-success">Fire Escape</span>' : ''}
            </div>
        </div>
    `;
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('buildingDetailsModal'));
    modal.show();
}


// Get appropriate icon for building type
function getBuildingIcon(buildingType) {
    const icons = {
        'Residential': 'https://cdn-icons-png.flaticon.com/512/619/619153.png',
        'Commercial': 'https://cdn-icons-png.flaticon.com/512/3069/3069172.png',
        'Industrial': 'https://cdn-icons-png.flaticon.com/512/2458/2458441.png',
        'Institutional': 'https://cdn-icons-png.flaticon.com/512/3079/3079151.png',
        'Government': 'https://cdn-icons-png.flaticon.com/512/1570/1570887.png',
        'Hospital': 'https://cdn-icons-png.flaticon.com/512/2968/2968828.png'
    };
    
    return icons[buildingType] || 'https://cdn-icons-png.flaticon.com/512/619/619153.png';
}

// Initialize building details modal
function initBuildingDetailsModal() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-building-details')) {
            const buildingId = e.target.getAttribute('data-id');
            fetchBuildingDetails(buildingId);
        }
    });
}

// Fetch building details via AJAX
function fetchBuildingDetails(buildingId) {
    fetch(`get_building.php?id=${buildingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showBuildingModal(data.building);
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

        // Initialize event listeners
        function initEventListeners() {
            // Route buttons
            document.getElementById('routeToStation').addEventListener('click', showRouteToStation);
            document.getElementById('clearRoute').addEventListener('click', clearRoute);
            document.getElementById('locate-emergency').addEventListener('click', locateEmergency);
            
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
            
            // Toggle buttons
            document.getElementById('heatmap-toggle').addEventListener('click', toggleHeatmap);
            document.getElementById('cluster-toggle').addEventListener('click', toggleClustering);
            
            // Refresh button
            document.getElementById('refresh-btn').addEventListener('click', fetchFireData);
            
            // View on map buttons
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('view-on-map')) {
                    const lat = parseFloat(e.target.getAttribute('data-lat'));
                    const lng = parseFloat(e.target.getAttribute('data-lng'));
                    map.flyTo([lat, lng], 18);
                }
            });
        }

        // Fetch fire data from API
        function fetchFireData() {
            fetch(`${config.apiEndpoint}?user_id=${config.userId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        allFireData = data.data;
                        updateDashboard(data.data);
                        updateTime();
                        showAlert('success', 'Data updated successfully', false);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    console.error("Error fetching fire data:", error);
                    showAlert('danger', 'Failed to fetch fire data. Please try again.');
                });
        }

        // Update dashboard with new data
        function updateDashboard(fireData) {
            // Update counts
            const counts = {
                "SAFE": 0,
                "MONITORING": 0,
                "PRE-DISPATCH": 0,
                "EMERGENCY": 0
            };
            
            fireData.forEach(fire => {
                const status = fire.status.toUpperCase();
                if (counts.hasOwnProperty(status)) {
                    counts[status]++;
                }
            });
            
            document.getElementById('safe-count').textContent = counts.SAFE;
            document.getElementById('monitoring-count').textContent = counts.MONITORING;
            document.getElementById('predispatch-count').textContent = counts['PRE-DISPATCH'];
            document.getElementById('emergency-count').textContent = counts.EMERGENCY;
            document.getElementById('all-count').textContent = fireData.length;
            
            // Update map with new data
            filterFiresByStatus(document.querySelector('.filter-legend.active').getAttribute('data-status'));
            
            // Update alerts count
            const emergencyCount = counts.EMERGENCY;
            document.getElementById('alert-count').textContent = emergencyCount;
        }

        // Filter fires by status
        function filterFiresByStatus(status) {
            // Clear existing layers
            map.removeLayer(fireMarkers);
            if (heatLayer) map.removeLayer(heatLayer);
            
            fireMarkers.clearLayers();
            const heatData = [];
            let emergencyCount = 0;
            
            // Process each fire incident
            allFireData.forEach(fire => {
                if (status === 'all' || fire.status === status) {
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
                }
            });
            
            // Add markers to map
            if (clusteringEnabled) {
                map.addLayer(fireMarkers);
            } else {
                fireMarkers.getLayers().forEach(layer => {
                    map.addLayer(layer);
                });
            }
            
            // Add heatmap if enabled
            if (heatmapEnabled && heatData.length > 0) {
                heatLayer = L.heatLayer(heatData, {
                    radius: config.heatmapRadius,
                    blur: config.heatmapBlur,
                    gradient: {0.4: 'blue', 0.6: 'lime', 0.7: 'yellow', 0.8: 'red'}
                }).addTo(map);
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
            
            // Add tooltip
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
                'Pre-Dispatch': 'https://cdn-icons-png.flaticon.com/512/599/599502.png',
                'Emergency': 'https://cdn-icons-png.flaticon.com/512/599/599508.png'
            };
            return icons[status] || 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png';
        }

// Show route to fire station only if the newest status is "EMERGENCY"
function showRouteToStation() {
    fetchEmergencyBuildings()
        .then(buildings => {
            if (buildings.length === 0) {
                showAlert('warning', 'No buildings in emergency status.');
                return;
            }

            // Find the most recent building with "EMERGENCY" status
            const emergencyBuilding = buildings.find(b => b.status.toUpperCase() === 'EMERGENCY');

            if (!emergencyBuilding) {
                showAlert('info', 'No active emergency detected.');
                return;
            }

            const { latitude, longitude } = emergencyBuilding;

            // Clear existing route
            clearRoute();

            // Show route from emergency building to fire station
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
                    styles: [{ color: '#d90429', weight: 5, opacity: 0.7 }]
                },
                createMarker: () => null,
                summaryTemplate: '<div class="text-center"><h5>Distance: {distance} km</h5><p>Estimated time: {time}</p></div>'
            }).addTo(map);

            map.fitBounds([
                [latitude, longitude],
                [config.fireStation.lat, config.fireStation.lng]
            ]);
        })
        .catch(error => {
            console.error("Error fetching emergency buildings:", error);
            showAlert('danger', 'Error locating emergency building.');
        });
}

// Clear any existing route
function clearRoute() {
    if (routingControl) {
        map.removeControl(routingControl);
        routingControl = null;
    }
}

// Locate the building with "EMERGENCY" status on the map
function locateEmergency() {
    fetchEmergencyBuildings()
        .then(buildings => {
            const emergencyBuilding = buildings.find(b => b.status.toUpperCase() === 'EMERGENCY');
            const locateBtn = document.getElementById('locateEmergencyBtn');

            if (!emergencyBuilding) {
                showAlert('warning', 'No buildings in emergency status.');
                if (locateBtn) locateBtn.disabled = true;
                return;
            }

            if (locateBtn) locateBtn.disabled = false;

            const { latitude, longitude, building_name, address } = emergencyBuilding;

            map.flyTo([latitude, longitude], 16);

            L.marker([latitude, longitude])
                .addTo(map)
                .bindPopup(`<b>${building_name}</b><br>${address}`)
                .openPopup();

            showAlert('success', `Building "${building_name}" is in emergency.`);
        })
        .catch(error => {
            console.error("Error fetching emergency buildings:", error);
            showAlert('danger', 'Error locating emergency building.');
        });
}

// Fetch emergency buildings from the database
function fetchEmergencyBuildings() {
    return new Promise((resolve, reject) => {
        // Mocking a server call (replace this with actual AJAX or API call to the backend)
        const emergencyBuildings = []; // Array to store emergency buildings
        
        // Simulate fetching data from the backend (replace this logic with actual AJAX/fetch)
        // const query = `
        //     SELECT b.id, b.building_name, b.address, b.latitude, b.longitude, f.status
        //     FROM buildings b
        //     JOIN fire_data f ON b.user_id = f.user_id
        //     JOIN (
        //         SELECT user_id, MAX(timestamp) AS latest_time
        //         FROM fire_data
        //         WHERE status IN ('EMERGENCY', 'SAFE', 'MONITORING', 'PRE-DISPATCH')  -- Filter statuses
        //         GROUP BY user_id
        //     ) latest ON latest.user_id = f.user_id AND f.timestamp = latest.latest_time
        //     ORDER BY f.timestamp DESC
        //     LIMIT 1
        // `;

        $.ajax({
            url: 'get_emergency_buildings.php',
            method: 'GET',
            success: function(data) {
                resolve(data);
            },
            error: function(error) {
                reject(error);
            }
        });
    });
}

// Button to locate emergency building(s)
document.getElementById('locate-emergency').addEventListener('click', function() {
    locateEmergency();
});

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
            document.getElementById('update-time').textContent = now.toLocaleTimeString();
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


        // WebSocket message handler (assuming you're using a WebSocket for real-time communication)
websocket.onmessage = function (event) {
    let message = JSON.parse(event.data);
    
    // Log the whole message to see if it's correct
    console.log('Received WebSocket message:', message);

    // Ensure the user_id is properly received
    if (message.user_id && !isNaN(message.user_id) && message.user_id !== 0) {
        console.log('Received valid user ID from WebSocket:', message.user_id);
        // Assuming latitude and longitude are available, send the user location
        sendUserLocationToServer(message.user_id, message.latitude, message.longitude);
    } else {
        console.error('Invalid or missing user_id in WebSocket message');
    }
};
