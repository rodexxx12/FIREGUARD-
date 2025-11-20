// Configuration
const config = {
    fireStation: { 
        lat: 10.525467693871333, 
        lng: 122.84123838118607, 
        name: "Bago City Fire Station",
        contact: "(034) 461-1234"
    },
    buildings: window.buildingsData || [],
    apiEndpoint: "server.php",
    updateInterval: 30000, // 30 seconds
    heatmapRadius: 25,
    heatmapBlur: 15,
    statusThresholds: {
        Safe: { smoke: 20, temp: 30, heat: 30 },
        Monitoring: { smoke: 50, temp: 50, heat: 50 },
        Acknowledged: { smoke: 100, temp: 100, heat: 100 },
        Emergency: { smoke: 200, temp: 200, heat: 200 }
    },
    userId: window.userId || 0,
    mapboxAccessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw',
    // TTS Configuration
    tts: {
        enabled: true,
        voice: null,
        rate: 1.0,
        pitch: 1.0,
        volume: 0.8,
        autoSpeak: true,
        speakDirections: true,
        speakStatus: true,
        speakETA: true
    }
};
    
// Global variables
let map, fireMarkers, heatLayer, routingControl;
let allFireData = [];
let heatmapEnabled = false;
let clusteringEnabled = true;
let userLocation = null;
let userMarker = null;
let speechSynthesis = window.speechSynthesis;
let currentUtterance = null;
let ttsEnabled = true;
    
// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    // Initialize TTS first
    initTTS();
    
    initMap();
    initEventListeners();
    fetchFireData();
    initBuildingsLayer();
    initBuildingDetailsModal();
    setInterval(fetchFireData, config.updateInterval);
    
    // Get user location if permission was previously granted
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => updateUserLocation(position.coords.latitude, position.coords.longitude),
            error => console.log("Geolocation error:", error)
        );
    }
    
    // Ensure TTS button shows correct state on page load
    setTimeout(() => {
        const ttsToggleBtn = document.getElementById('ttsToggleBtn');
        if (ttsToggleBtn && ttsEnabled) {
            const icon = ttsToggleBtn.querySelector('i');
            icon.className = 'bi bi-volume-up-fill';
            ttsToggleBtn.classList.remove('btn-outline-secondary');
            ttsToggleBtn.classList.add('btn-success');
        }
    }, 1000);
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
    
// Get appropriate icon for building type
function getBuildingIcon(buildingType) {
    const icons = {
        'Residential': 'https://cdn-icons-png.flaticon.com/512/619/619153.png',
        'Commercial': './images/commercial.png',
        'Industrial': './images/industrial.png',
        'Institutional': './images/institutional.png',
    };

    return icons[buildingType] || 'https://cdn-icons-png.flaticon.com/512/619/619153.png';  // Default icon if type is not found
}
    
let buildingsLayer = null;
    
// Load and display user's buildings on the map
function initBuildingsLayer() {
    fetch('get_user_buildings.php')
        .then(response => response.json())
        .then(data => {
            if (buildingsLayer) {
                map.removeLayer(buildingsLayer);
            }

            buildingsLayer = L.layerGroup();

            data.forEach(building => {
                const marker = createBuildingMarker(building);
                buildingsLayer.addLayer(marker);
                
                // Save building area coordinates to database
                saveBuildingArea(building.id, building.latitude, building.longitude, 100);
            });

            buildingsLayer.addTo(map);
        })
        .catch(error => {
            console.error('Error loading user buildings:', error);
        });
}
    
// Create a marker for a building (with popup and button)
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

    // Create a 100-meter radius circle around the building
    const buildingCircle = L.circle([building.latitude, building.longitude], {
        radius: 100, // 100 meters
        color: '#3388ff',
        fillColor: '#3388ff',
        fillOpacity: 0.2,
        weight: 2,
        opacity: 0.6
    });

    // Check if address matches the specific address and apply styling
    const targetAddress = "Rafael M. Salas Drive, Sampinit, Bago, Negros Occidental, Philippines";
    const addressStyle = building.address && building.address.includes("Rafael M. Salas Drive, Sampinit, Bago, Negros Occidental, Philippines") 
        ? 'style="background-color: red; font-weight: bold; padding: 5px; display: inline-block;"' 
        : '';
    const addressDisplay = building.address ? `<span ${addressStyle}>${building.address}</span>` : 'N/A';
    
    // Bind popup to circle as well
    buildingCircle.bindPopup(`
        <div class="building-popup">
            <p class="mb-2"><i class="bi bi-geo-alt me-2"></i><strong>${addressDisplay}</strong></p>
            <h5 class="fw-bold">${building.building_name}</h5>
            <p class="mb-1"><i class="bi bi-building me-2"></i>Type: ${building.building_type}</p>
            <p class="mb-1"><i class="bi bi-rulers me-2"></i>Area: 100 meters radius</p>
        </div>
    `);

    const popupContent = `
        <div class="building-popup">
            <p class="mb-2"><i class="bi bi-geo-alt me-2"></i><strong>${addressDisplay}</strong></p>
            <h5 class="fw-bold">${building.building_name}</h5>
            <p class="mb-1"><i class="bi bi-building me-2"></i>Type: ${building.building_type}</p>
            <p class="mb-1"><i class="bi bi-person me-2"></i><strong>Contact:</strong> ${building.contact_person || 'N/A'}</p>
            <p class="mb-1"><i class="bi bi-telephone me-2"></i>${building.contact_number || 'N/A'}</p>
            <p class="mb-1"><i class="bi bi-layers me-2"></i><strong>Floors:</strong> ${building.total_floors || 'N/A'}</p>
            <p class="mb-1"><i class="bi bi-rulers me-2"></i>Area: 100 meters radius</p>
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
    `;

    marker.bindPopup(popupContent);

    marker.bindTooltip(building.building_name, {
        permanent: false,
        direction: 'top'
    });

    // Create a layer group containing both marker and circle
    const buildingGroup = L.layerGroup([buildingCircle, marker]);
    
    return buildingGroup;
}

// Save building area coordinates to database
function saveBuildingArea(buildingId, latitude, longitude, radius = 100) {
    if (!buildingId || !latitude || !longitude) {
        console.warn('Cannot save building area: missing required parameters');
        return;
    }
    
    // Calculate boundary coordinates (optional - circle boundary points)
    const boundaryPoints = calculateCircleBoundary(latitude, longitude, radius);
    
    const data = {
        building_id: buildingId,
        center_latitude: latitude,
        center_longitude: longitude,
        radius: radius,
        boundary_coordinates: boundaryPoints
    };
    
    fetch('save_building_area.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            console.log(`Building area saved for building ID: ${buildingId}`);
        } else {
            console.warn(`Failed to save building area: ${result.error || result.message}`);
        }
    })
    .catch(error => {
        console.error('Error saving building area:', error);
    });
}

// Calculate circle boundary points (optional helper function)
function calculateCircleBoundary(centerLat, centerLng, radiusMeters) {
    // Calculate approximate boundary points for the circle
    // This creates 32 points around the circle for smoother representation
    const points = [];
    const numPoints = 32;
    const earthRadius = 6371000; // Earth radius in meters
    
    for (let i = 0; i < numPoints; i++) {
        const angle = (i * 360) / numPoints;
        const angleRad = (angle * Math.PI) / 180;
        
        // Calculate point on circle
        const latOffset = (radiusMeters / earthRadius) * (180 / Math.PI);
        const lngOffset = (radiusMeters / (earthRadius * Math.cos(centerLat * Math.PI / 180))) * (180 / Math.PI);
        
        const pointLat = centerLat + latOffset * Math.cos(angleRad);
        const pointLng = centerLng + lngOffset * Math.sin(angleRad);
        
        points.push([pointLat, pointLng]);
    }
    
    return points;
}
    
// Toggle building visibility
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
    
// Event delegation for "View Details" button in popup
document.addEventListener('click', function (event) {
    if (event.target.closest('.view-building-details')) {
        const buildingId = event.target.closest('.view-building-details').dataset.id;
        fetchBuildingDetails(buildingId);
    }
});
    
// Fetch and show detailed info in modal
function fetchBuildingDetails(buildingId) {
    fetch(`get_building.php?id=${buildingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.building) {
                showBuildingModal(data.building);
            } else {
                showAlert('danger', 'Failed to fetch building details');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showAlert('danger', 'Error fetching building details');
        });
}
    
// Show data inside Bootstrap modal
function showBuildingModal(building) {
    const modalBody = document.querySelector('#buildingDetailsModal .modal-body');
    
    // Check if address matches the specific address and apply styling
    const targetAddress = "Rafael M. Salas Drive, Sampinit, Bago, Negros Occidental, Philippines";
    const addressStyle = building.address && building.address.includes("Rafael M. Salas Drive, Sampinit, Bago, Negros Occidental, Philippines") 
        ? 'style="background-color: red; font-weight: bold; padding: 5px; display: inline-block;"' 
        : '';

    modalBody.innerHTML = `
        <p class="mb-2"><strong><span ${addressStyle}>${building.address || 'N/A'}</span></strong></p>
        <h5 class="fw-bold">${building.building_name}</h5>
        <p><strong>Type:</strong> ${building.building_type}</p>
        <p><strong>Contact Person:</strong> ${building.contact_person || 'N/A'}</p>
        <p><strong>Contact Number:</strong> ${building.contact_number || 'N/A'}</p>
        <p><strong>Floors:</strong> ${building.total_floors || 'N/A'}</p>
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

    const modal = new bootstrap.Modal(document.getElementById('buildingDetailsModal'));
    modal.show();
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
        "ACKNOWLEDGED": 0,
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
    document.getElementById('predispatch-count').textContent = counts.ACKNOWLEDGED;
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
    
    // Add fireMarkers back to map
    map.addLayer(fireMarkers);
    
    // Add heatmap if data exists
    if (heatData.length > 0 && heatmapEnabled) {
        heatLayer = L.heatLayer(heatData, {
            radius: config.heatmapRadius,
            blur: config.heatmapBlur,
            maxZoom: 10
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
    
function showRouteToStation() {
    console.log('showRouteToStation: Fetching latest fire data for user...');
    
    // Fetch the latest fire_data for the user_id
    fetch('get_latest_fire_data_for_route.php?t=' + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            console.log('showRouteToStation: Received fire data:', data);
            
            // Check if request was successful
            if (!data.success) {
                console.log('showRouteToStation: Request failed:', data.error);
                
                // Check if device is not within building radius
                if (data.error === 'Device not within building radius') {
                    showAlert('warning', 
                        `<strong>Device Location Restriction</strong><br>
                        ${data.message || 'Device must be inside a building radius area to calculate route to fire station.'}<br>
                        <small>Please ensure your device is within 100 meters of a building location.</small>`);
                } else {
                    showAlert('info', data.message || data.error || 'No fire data found for your account.');
                }
                clearRoute();
                return;
            }

            const latestFireData = data.data;
            
            // Use GPS coordinates from fire_data
            const { latitude, longitude, status, timestamp, temp, heat, smoke, flame_detected, building_type, device_id } = latestFireData;
            
            // Validate GPS coordinates
            if (!latitude || !longitude || isNaN(latitude) || isNaN(longitude)) {
                showAlert('danger', 'Invalid GPS coordinates in fire data. Please check your device location settings.');
                clearRoute();
                return;
            }
            
            console.log('Latest fire data found at:', latitude, longitude, 'Status:', status);

            clearRoute();
            if (emergencyMarker) {
                map.removeLayer(emergencyMarker);
                emergencyMarker = null;
            }

            // Determine styling based on status
            const statusUpper = status.toUpperCase();
            let routeColor = '#3388ff'; // Default blue
            let iconUrl = 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png'; // Default icon
            
            if (statusUpper === 'EMERGENCY') {
                routeColor = '#d90429'; // Red
                iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599508.png';
            } else if (statusUpper === 'ACKNOWLEDGED') {
                routeColor = '#ffc107'; // Yellow/Orange
                iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599502.png';
            } else if (statusUpper === 'PRE-DISPATCH' || statusUpper === 'PRE DISPATCH') {
                routeColor = '#fd7e14'; // Orange
                iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599502.png';
            } else if (statusUpper === 'MONITORING') {
                routeColor = '#ffc107'; // Yellow
                iconUrl = 'https://cdn-icons-png.flaticon.com/512/3523/3523096.png';
            } else if (statusUpper === 'SAFE') {
                routeColor = '#28a745'; // Green
                iconUrl = 'https://cdn-icons-png.flaticon.com/512/1828/1828640.png';
            }

            // Add marker with appropriate styling
            emergencyMarker = L.marker([latitude, longitude], {
                icon: L.icon({
                    iconUrl: iconUrl,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32]
                })
            }).addTo(map);

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

            routingControl.on('routesfound', async function (e) {
                const route = e.routes[0];
                const distanceMeters = route.summary.totalDistance;
                const durationSeconds = route.summary.totalTime;

                const distanceKm = (distanceMeters / 1000).toFixed(2);
                const durationFormatted = formatDuration(durationSeconds);

                // Get address from coordinates
                const address = await getAddressFromCoordinates(latitude, longitude);
                const locationInfo = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;

                if (emergencyMarker) {
                    emergencyMarker.bindPopup(`
                        <b>Fire Alert Location</b><br>
                        <span style="color:${routeColor}"><strong>${statusUpper}</strong></span><br>
                        <strong>Address:</strong> ${address}<br>
                        <strong>GPS:</strong> ${locationInfo}<br>
                        <strong>Type:</strong> ${building_type || 'N/A'}<br>
                        <strong>Device ID:</strong> ${device_id || 'N/A'}<br><br>
                        <strong>Distance:</strong> ${distanceKm} km<br>
                        <strong>ETA:</strong> ${durationFormatted}
                    `).openPopup();
                }

                // Determine alert type based on status
                let alertType = 'info';
                if (statusUpper === 'EMERGENCY') {
                    alertType = 'danger';
                } else if (statusUpper === 'ACKNOWLEDGED' || statusUpper === 'PRE-DISPATCH' || statusUpper === 'PRE DISPATCH' || statusUpper === 'MONITORING') {
                    alertType = 'warning';
                } else if (statusUpper === 'SAFE') {
                    alertType = 'success';
                }
                
                showAlert(
                    alertType,
                    `<strong>Fire Data Status:</strong> ${statusUpper}<br>
                    <strong>Address:</strong> ${address}<br>
                    <strong>GPS:</strong> ${locationInfo}<br>
                    <strong>Distance:</strong> ${distanceKm} km<br>
                    <strong>Estimated Time:</strong> ${durationFormatted}`
                );

                // Speak route directions with TTS
                console.log('Route found, autoSpeak enabled:', config.tts.autoSpeak);
                if (config.tts.autoSpeak) {
                    console.log('Calling speakRouteDirections...');
                    speakRouteDirections('Fire Alert Location', address, statusUpper, distanceKm, durationFormatted);
                    
                    // Speak turn-by-turn directions after a short delay
                    setTimeout(() => {
                        console.log('Calling speakTurnByTurnDirections...');
                        speakTurnByTurnDirections(route);
                    }, 3000);
                }
            });

            map.fitBounds([
                [latitude, longitude],
                [config.fireStation.lat, config.fireStation.lng]
            ]);
        })
        .catch(error => {
            console.error("Error fetching latest fire data:", error);
            showAlert('danger', 'Error locating fire data. Please try again.');
            clearRoute();
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
    
    // Stop any ongoing TTS
    stopSpeech();
}

let emergencyMarker = null;

function locateEmergency() {
    // Get the button and show loading state
    const locateBtn = document.getElementById('locate-emergency');
    const originalText = locateBtn.innerHTML;
    
    // Show loading state
    locateBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Locating...';
    locateBtn.disabled = true;
    
    // Fetch latest fire_data with GPS coordinates (gps_latitude, gps_longitude) for the user
    fetch('get_latest_fire_data.php?t=' + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            // Check if request was successful
            if (!data.success) {
                // Check if device is not within building radius
                if (data.error === 'Device not within building radius') {
                    showAlert('warning', 
                        `<strong>Device Location Restriction</strong><br>
                        ${data.message || 'Device must be inside a building radius area to be located.'}<br>
                        <small>Please ensure your device is within 100 meters of a building location.</small>`);
                } else {
                    showAlert('info', data.message || data.error || 'No fire data found for your account.');
                }
                // Restore button state
                locateBtn.innerHTML = originalText;
                locateBtn.disabled = false;
                return;
            }

            const fireData = data.data;
            // Extract GPS coordinates (gps_latitude, gps_longitude) from fire_data table
            const { latitude, longitude, status, timestamp, temp, heat, smoke, flame_detected, building_type, device_id } = fireData;

            // Validate GPS coordinates from fire_data table
            if (!latitude || !longitude || isNaN(latitude) || isNaN(longitude)) {
                showAlert('danger', 'Invalid GPS coordinates found in fire data. Please check your device location settings.');
                locateBtn.innerHTML = originalText;
                locateBtn.disabled = false;
                return;
            }

            // Remove existing emergency marker if any
            if (emergencyMarker) {
                map.removeLayer(emergencyMarker);
            }

            // Fly to the fire data location using GPS coordinates (gps_latitude, gps_longitude) from fire_data table
            map.flyTo([latitude, longitude], 16);

            // Determine status color and icon
            const statusUpper = status.toUpperCase();
            const statusColor = statusUpper === 'EMERGENCY' ? 'red' : 
                              statusUpper === 'ACKNOWLEDGED' ? 'orange' : 
                              statusUpper === 'MONITORING' ? 'yellow' : 'green';
            
            const statusIcon = statusUpper === 'EMERGENCY' ? '🚨' : 
                             statusUpper === 'ACKNOWLEDGED' ? '⚠️' : 
                             statusUpper === 'MONITORING' ? '⚡' : '✅';

            // Use local fire icon for locateEmergency
            const fireIcon = L.icon({
                iconUrl: '../images/fireIcon.png',
                iconSize: [40, 40],
                iconAnchor: [20, 40],
                popupAnchor: [0, -40]
            });

            // Get address from coordinates (async)
            getAddressFromCoordinates(latitude, longitude).then(address => {
                // Create popup content with fire data details
                const popupContent = `
                    <div class="fire-popup">
                        <h5 class="fw-bold text-${statusUpper.toLowerCase()}" style="color: ${statusColor}">
                            ${statusIcon} ${statusUpper}
                        </h5>
                        <hr>
                        <p class="mb-1"><i class="bi bi-thermometer-sun me-2"></i><strong>Heat:</strong> ${heat}°C</p>
                        <p class="mb-1"><i class="bi bi-thermometer-high me-2"></i><strong>Temp:</strong> ${temp}°C</p>
                        <p class="mb-1"><i class="bi bi-cloud-fog me-2"></i><strong>Smoke:</strong> ${smoke}</p>
                        <p class="mb-1"><i class="bi bi-fire me-2"></i><strong>Flame:</strong> ${flame_detected ? 'Detected' : 'Not detected'}</p>
                        <p class="mb-1"><i class="bi bi-building me-2"></i><strong>Type:</strong> ${building_type || 'N/A'}</p>
                        ${device_id ? `<p class="mb-1"><i class="bi bi-device-hdd me-2"></i><strong>Device ID:</strong> ${device_id}</p>` : ''}
                        <p class="mb-2"><i class="bi bi-clock me-2"></i><strong>Time:</strong> ${new Date(timestamp).toLocaleString()}</p>
                        <p class="mb-1"><i class="bi bi-geo-alt me-2"></i><strong>Address:</strong> ${address}</p>
                        <p class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i><strong>GPS:</strong> ${latitude.toFixed(6)}, ${longitude.toFixed(6)}</p>
                    </div>
                `;

                // Update popup with address
                if (emergencyMarker) {
                    emergencyMarker.setPopupContent(popupContent);
                }
            });

            // Create initial popup with coordinates (will be updated with address)
            const initialPopupContent = `
                <div class="fire-popup">
                    <h5 class="fw-bold text-${statusUpper.toLowerCase()}" style="color: ${statusColor}">
                        ${statusIcon} ${statusUpper}
                    </h5>
                    <hr>
                    <p class="mb-1"><i class="bi bi-thermometer-sun me-2"></i><strong>Heat:</strong> ${heat}°C</p>
                    <p class="mb-1"><i class="bi bi-thermometer-high me-2"></i><strong>Temp:</strong> ${temp}°C</p>
                    <p class="mb-1"><i class="bi bi-cloud-fog me-2"></i><strong>Smoke:</strong> ${smoke}</p>
                    <p class="mb-1"><i class="bi bi-fire me-2"></i><strong>Flame:</strong> ${flame_detected ? 'Detected' : 'Not detected'}</p>
                    <p class="mb-1"><i class="bi bi-building me-2"></i><strong>Type:</strong> ${building_type || 'N/A'}</p>
                    ${device_id ? `<p class="mb-1"><i class="bi bi-device-hdd me-2"></i><strong>Device ID:</strong> ${device_id}</p>` : ''}
                    <p class="mb-2"><i class="bi bi-clock me-2"></i><strong>Time:</strong> ${new Date(timestamp).toLocaleString()}</p>
                    <p class="mb-0"><i class="bi bi-geo-alt me-2"></i><strong>Location:</strong> ${latitude.toFixed(6)}, ${longitude.toFixed(6)}<br><small>Loading address...</small></p>
                </div>
            `;

            // Create and add the emergency marker
            emergencyMarker = L.marker([latitude, longitude], { 
                icon: fireIcon,
                riseOnHover: true
            })
                .addTo(map)
                .bindPopup(initialPopupContent)
                .openPopup();

            // Get address and update alert
            getAddressFromCoordinates(latitude, longitude).then(address => {
                // Show appropriate alert based on status
                const alertColor = statusUpper === 'EMERGENCY' ? 'danger' : 
                                 statusUpper === 'ACKNOWLEDGED' ? 'warning' : 
                                 statusUpper === 'MONITORING' ? 'warning' : 'success';
                
                showAlert(alertColor, 
                         `${statusIcon} Located Latest Fire Data - Status: ${statusUpper}<br>
                         <strong>Address:</strong> ${address}<br>
                         <strong>GPS:</strong> ${latitude.toFixed(6)}, ${longitude.toFixed(6)}<br>
                         <strong>Temperature:</strong> ${temp}°C | <strong>Heat:</strong> ${heat}°C | <strong>Smoke:</strong> ${smoke}<br>
                         <strong>Timestamp:</strong> ${new Date(timestamp).toLocaleString()}`);
            });
            
            // Restore button state
            locateBtn.innerHTML = originalText;
            locateBtn.disabled = false;
        })
        .catch(error => {
            console.error("Error fetching latest fire data:", error);
            showAlert('danger', 'Error locating fire data. Please try again.');
            
            // Restore button state on error
            locateBtn.innerHTML = originalText;
            locateBtn.disabled = false;
        });
}

function fetchEmergencyFireData() {
    return new Promise((resolve, reject) => {
        // The script runs from php/ directory, so the endpoint is in the same directory
        // Add cache-busting parameter with version to force fresh load
        const url = 'get_emergency_fire_data.php?v=2&t=' + new Date().getTime();
        console.log('fetchEmergencyFireData: Fetching from:', url);
        console.log('fetchEmergencyFireData: This function uses fire_data GPS coordinates (gps_latitude, gps_longitude), NOT buildings');
        
        fetch(url)
            .then(response => {
                console.log('fetchEmergencyFireData: Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('fetchEmergencyFireData: Response data:', data);
                
                // Handle error response
                if (data && data.error) {
                    console.error('fetchEmergencyFireData: Error in response:', data.error);
                    reject(new Error(data.error));
                    return;
                }
                
                // Ensure we have an array
                if (Array.isArray(data)) {
                    console.log('fetchEmergencyFireData: Resolving with', data.length, 'fire_data records');
                    resolve(data);
                } else {
                    console.log('fetchEmergencyFireData: Invalid response format, resolving with empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('fetchEmergencyFireData: Fetch error:', error);
                console.error('fetchEmergencyFireData: Make sure get_emergency_fire_data.php exists and is accessible');
                reject(error);
            });
    });
}

// Reverse geocoding function to convert coordinates to address
// Using OpenStreetMap Nominatim API (free, no API key needed)
async function getAddressFromCoordinates(lat, lng) {
    try {
        // Use Nominatim reverse geocoding API
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
            {
                headers: {
                    'User-Agent': 'FireGuard Emergency System' // Required by Nominatim
                }
            }
        );
        
        if (!response.ok) {
            throw new Error('Geocoding API error');
        }
        
        const data = await response.json();
        
        if (data && data.address) {
            const addr = data.address;
            // Build readable address from components
            let addressParts = [];
            
            // Add road/street
            if (addr.road) addressParts.push(addr.road);
            if (addr.house_number) addressParts.push(addr.house_number);
            
            // Add village/neighborhood
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
            
            // Add country
            if (addr.country) addressParts.push(addr.country);
            
            // If we have address parts, return formatted address
            if (addressParts.length > 0) {
                return addressParts.join(', ');
            }
            
            // Fallback to display_name if available
            if (data.display_name) {
                return data.display_name;
            }
        }
        
        // Fallback to coordinates if geocoding fails
        return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    } catch (error) {
        console.error('Reverse geocoding error:', error);
        // Return coordinates as fallback
        return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
}

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
    .addTo(map)
    .bindPopup('Your Location')
    .bindTooltip('Your Location', { 
        permanent: false, 
        direction: 'top' 
    });
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
            sendUserLocationToServer(message.user_id, message.latitude, message.longitude);
        } else {
            console.error('Invalid or missing user_id in WebSocket message');
        }
    };
}

// ===== TEXT-TO-SPEECH FUNCTIONS =====

// Initialize TTS
function initTTS() {
    if (!speechSynthesis) {
        console.warn('Speech synthesis not supported in this browser');
        return false;
    }
    
    console.log('Initializing TTS...');
    
    // Wait for voices to load
    speechSynthesis.onvoiceschanged = function() {
        const voices = speechSynthesis.getVoices();
        console.log('Available voices:', voices.length);
        
        // Try to find a good English voice
        const preferredVoice = voices.find(voice => 
            voice.lang.startsWith('en') && voice.name.includes('Google')
        ) || voices.find(voice => 
            voice.lang.startsWith('en')
        ) || voices[0];
        
        if (preferredVoice) {
            config.tts.voice = preferredVoice;
            console.log('TTS initialized with voice:', preferredVoice.name);
            
            // Test TTS is working
            setTimeout(() => {
                if (ttsEnabled) {
                    speakText('TTS system ready', { rate: 0.9, volume: 0.5 });
                }
            }, 2000);
        } else {
            console.warn('No suitable voice found for TTS');
        }
    };
    
    // Force voices to load if they haven't already
    if (speechSynthesis.getVoices().length === 0) {
        speechSynthesis.getVoices();
    }
    
    return true;
}

// Stop current speech
function stopSpeech() {
    if (speechSynthesis && speechSynthesis.speaking) {
        speechSynthesis.cancel();
        currentUtterance = null;
    }
}

// Speak text with TTS
function speakText(text, options = {}) {
    console.log('speakText called with:', text, 'ttsEnabled:', ttsEnabled, 'config.tts.enabled:', config.tts.enabled);
    
    if (!config.tts.enabled || !ttsEnabled || !speechSynthesis) {
        console.log('TTS disabled or not available');
        return;
    }
    
    // Stop any current speech
    stopSpeech();
    
    const utterance = new SpeechSynthesisUtterance(text);
    
    // Apply configuration
    utterance.voice = config.tts.voice;
    utterance.rate = options.rate || config.tts.rate;
    utterance.pitch = options.pitch || config.tts.pitch;
    utterance.volume = options.volume || config.tts.volume;
    
    console.log('Speaking with voice:', config.tts.voice?.name, 'rate:', utterance.rate, 'volume:', utterance.volume);
    
    // Event handlers
    utterance.onstart = () => {
        console.log('TTS started:', text);
        currentUtterance = utterance;
        showTTSStatus('Speaking...', true);
        
        // Add speaking animation to TTS button
        const ttsBtn = document.getElementById('ttsToggleBtn');
        if (ttsBtn) {
            ttsBtn.classList.add('tts-speaking');
        }
    };
    
    utterance.onend = () => {
        console.log('TTS ended');
        currentUtterance = null;
        showTTSStatus('', false);
        
        // Remove speaking animation from TTS button
        const ttsBtn = document.getElementById('ttsToggleBtn');
        if (ttsBtn) {
            ttsBtn.classList.remove('tts-speaking');
        }
    };
    
    utterance.onerror = (event) => {
        console.error('TTS error:', event.error);
        currentUtterance = null;
        showTTSStatus('TTS Error', false);
        
        // Remove speaking animation from TTS button
        const ttsBtn = document.getElementById('ttsToggleBtn');
        if (ttsBtn) {
            ttsBtn.classList.remove('tts-speaking');
        }
    };
    
    speechSynthesis.speak(utterance);
}

// Show TTS status indicator
function showTTSStatus(message, show) {
    let statusElement = document.getElementById('tts-status');
    
    if (!statusElement) {
        statusElement = document.createElement('div');
        statusElement.id = 'tts-status';
        statusElement.className = 'tts-status';
        document.body.appendChild(statusElement);
    }
    
    if (show && message) {
        statusElement.textContent = message;
        statusElement.classList.add('show');
    } else {
        statusElement.classList.remove('show');
    }
}

// Speak route directions
function speakRouteDirections(locationName, locationDesc, status, distance, eta) {
    console.log('speakRouteDirections called with:', { locationName, locationDesc, status, distance, eta });
    
    if (!config.tts.speakDirections) {
        console.log('speakDirections disabled');
        return;
    }
    
    const statusText = status.toUpperCase();
    const distanceText = `${distance} kilometers`;
    const etaText = eta;
    
    let message = '';
    
    if (config.tts.speakStatus) {
        if (statusText === 'EMERGENCY') {
            message += `🚨 URGENT: Fire alert at ${locationName} is in EMERGENCY status. `;
        } else if (statusText === 'ACKNOWLEDGED') {
            message += `⚠️ Fire alert at ${locationName} is in ACKNOWLEDGED status. `;
        } else if (statusText === 'PRE-DISPATCH' || statusText === 'PRE DISPATCH') {
            message += `⚠️ Fire alert at ${locationName} is in PRE-DISPATCH status. `;
        } else if (statusText === 'MONITORING') {
            message += `⚡ Fire alert at ${locationName} is in MONITORING status. `;
        } else if (statusText === 'SAFE') {
            message += `✅ Fire alert at ${locationName} is in SAFE status. `;
        } else {
            message += `Fire alert at ${locationName} is in ${statusText} status. `;
        }
    }
    
    if (config.tts.speakETA) {
        message += `Distance to fire station is ${distanceText}. Estimated travel time is ${etaText}. `;
    }
    
    message += `Route has been calculated and displayed on the map. Turn-by-turn directions will follow.`;
    
    console.log('Speaking route message:', message);
    speakText(message, { rate: 0.85, pitch: 1.1 }); // Slower and slightly higher pitch for urgency
}

// Speak turn-by-turn directions
function speakTurnByTurnDirections(route) {
    if (!config.tts.speakDirections || !route || !route.instructions) return;
    
    const instructions = route.instructions;
    let directionText = 'Turn-by-turn directions: ';
    
    // Process first few instructions
    const maxInstructions = 6; // Show more instructions
    const instructionsToSpeak = instructions.slice(0, maxInstructions);
    
    instructionsToSpeak.forEach((instruction, index) => {
        const text = instruction.text.replace(/<[^>]*>/g, ''); // Remove HTML tags
        const distance = instruction.distance;
        
        // Format distance
        let distanceText = '';
        if (distance < 1000) {
            distanceText = `in ${Math.round(distance)} meters`;
        } else {
            distanceText = `in ${(distance / 1000).toFixed(1)} kilometers`;
        }
        
        directionText += `${index + 1}. ${text} ${distanceText}. `;
    });
    
    if (instructions.length > maxInstructions) {
        directionText += `And ${instructions.length - maxInstructions} more turns to reach the destination.`;
    } else {
        directionText += `This will take you to the fire station.`;
    }
    
    speakText(directionText, { rate: 0.8, pitch: 1.0 }); // Slower for directions
}

// Toggle TTS on/off
function toggleTTS() {
    ttsEnabled = !ttsEnabled;
    
    if (!ttsEnabled) {
        stopSpeech();
    }
    
    // Update UI
    const ttsToggleBtn = document.getElementById('ttsToggleBtn');
    if (ttsToggleBtn) {
        const icon = ttsToggleBtn.querySelector('i');
        if (ttsEnabled) {
            icon.className = 'bi bi-volume-up-fill';
            ttsToggleBtn.classList.remove('btn-outline-secondary');
            ttsToggleBtn.classList.add('btn-success');
            showAlert('success', 'Text-to-Speech enabled');
            
            // Test TTS with a brief message
            setTimeout(() => {
                speakText('Text-to-Speech is now active', { rate: 0.9 });
            }, 500);
        } else {
            icon.className = 'bi bi-volume-mute-fill';
            ttsToggleBtn.classList.remove('btn-success');
            ttsToggleBtn.classList.add('btn-outline-secondary');
            showAlert('info', 'Text-to-Speech disabled');
        }
    }
}

// Test TTS functionality
function testTTS() {
    if (!ttsEnabled) {
        showAlert('warning', 'Please enable Text-to-Speech first');
        return;
    }
    
    const testMessage = 'This is a test of the text-to-speech system. Route directions will be spoken when you use the Route to Fire Station feature.';
    speakText(testMessage, { rate: 0.9 });
}

// Force enable TTS (for debugging)
function forceEnableTTS() {
    ttsEnabled = true;
    config.tts.enabled = true;
    config.tts.autoSpeak = true;
    config.tts.speakDirections = true;
    config.tts.speakStatus = true;
    config.tts.speakETA = true;
    
    console.log('TTS forcefully enabled');
    showAlert('success', 'TTS forcefully enabled');
    
    // Update UI
    const ttsToggleBtn = document.getElementById('ttsToggleBtn');
    if (ttsToggleBtn) {
        const icon = ttsToggleBtn.querySelector('i');
        icon.className = 'bi bi-volume-up-fill';
        ttsToggleBtn.classList.remove('btn-outline-secondary');
        ttsToggleBtn.classList.add('btn-success');
    }
}

// Initialize TTS when page loads
document.addEventListener('DOMContentLoaded', function() {
    initTTS();
});

// Debug: Check if TTS functions are loaded
console.log('TTS Functions loaded:', {
    initTTS: typeof initTTS,
    speakText: typeof speakText,
    toggleTTS: typeof toggleTTS,
    testTTS: typeof testTTS,
    forceEnableTTS: typeof forceEnableTTS
});