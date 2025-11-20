<?php 
require_once __DIR__ . '/../functions/functions.php';
require_once __DIR__ . '/../db/db.php';

// Include alert.php FIRST to handle POST requests before any HTML output
// This ensures JSON responses are sent without HTML contamination
include('../../alarm/alert.php');

// Only continue with HTML output if this is not a POST request
// (POST requests are handled and exit in alert.php)
?>
<?php include('../../components/header.php'); ?>
   <link rel="stylesheet" href="../css/style.css">
  </head>
  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
                    <!-- Main Map Column -->
                    <div class="col-lg-8">
                        <div class="control-panel">
                            <div id="map"></div>
                            <div class="d-flex justify-content-between mt-3">
                                <div class="btn-group mb-2" role="group">
                                    <button id="routeToStation" class="btn btn-primary">
                                        Route To Fire Station
                                    </button>
                                    <button id="clearRoute" class="btn btn-outline-secondary">
                                        Clear
                                    </button>
                                </div>
                                <div>
                                <button id="locate-emergency" onclick="locateEmergency()" class="btn btn-primary">
                                    Locate Device
                                </button>
                                <button id="toggle-buildings" class="btn btn-outline-primary">
                            Show Buildings
                        </button>
                        </div>
                            </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="control-panel">
                    <!-- Devices Row -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card status-card status-monitoring h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Total Devices</h5>
                                    <h2 id="total-devices-count" class="text-primary"><?= $total_devices ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Devices Row (replacing Buildings) -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card status-card status-monitoring h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Total Devices</h5>
                                    <h2 id="total-buildings-count" class="text-info"><?= $total_buildings ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="list-group legend-list-group mt-10">
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend active legend-3d" data-status="all" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(44,62,80,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #6c757d 60%, #adb5bd 100%); box-shadow: 0 2px 8px 0 rgba(44,62,80,0.10);"></span>
                <span class="fw-semibold">All Fire Incidents</span>
            </div>
            <span id="all-count" class="badge bg-dark rounded-pill shadow-sm px-3 py-2 fs-6"><?= is_array($counts) ? array_sum($counts) : 0 ?></span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="Safe" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(40,167,69,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #28a745 60%, #a8e063 100%); box-shadow: 0 2px 8px 0 rgba(40,167,69,0.10);"></span>
                <span class="fw-semibold">Safe Zones</span>
            </div>
            <span class="badge bg-success rounded-pill shadow-sm px-3 py-2 fs-6"><?= isset($counts['SAFE']) ? $counts['SAFE'] : 0 ?></span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="Monitoring" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(255,193,7,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #ffc107 60%, #fffbe0 100%); box-shadow: 0 2px 8px 0 rgba(255,193,7,0.10);"></span>
                <span class="fw-semibold">Monitoring</span>
            </div>
            <span class="badge bg-warning text-dark rounded-pill shadow-sm px-3 py-2 fs-6"><?= isset($counts['MONITORING']) ? $counts['MONITORING'] : 0 ?></span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="Acknowledged" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(253,126,20,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #fd7e14 60%, #ffe0b2 100%); box-shadow: 0 2px 8px 0 rgba(253,126,20,0.10);"></span>
                <span class="fw-semibold">Acknowledged</span>
            </div>
            <span class="badge bg-orange rounded-pill shadow-sm px-3 py-2 fs-6"><?= isset($counts['ACKNOWLEDGED']) ? $counts['ACKNOWLEDGED'] : 0 ?></span>
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center filter-legend legend-3d" data-status="Emergency" style="border-radius: 1rem; margin-bottom: 0.5rem; box-shadow: 0 2px 8px 0 rgba(220,53,69,0.10); transition: box-shadow 0.2s, transform 0.2s;">
            <div class="d-flex align-items-center">
                <span class="legend-indicator legend-3d-indicator me-2" style="background: linear-gradient(135deg, #dc3545 60%, #ffb3b3 100%); box-shadow: 0 2px 8px 0 rgba(220,53,69,0.10);"></span>
                <span class="fw-semibold">Emergency</span>
            </div>
            <span class="badge bg-danger rounded-pill shadow-sm px-3 py-2 fs-6"><?= isset($counts['EMERGENCY']) ? $counts['EMERGENCY'] : 0 ?></span>
        </a>
    </div>
</div> 
                <!-- Modal for displaying building details -->
                <div class="modal fade" id="buildingDetailsModal" tabindex="-1" aria-labelledby="buildingDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="buildingDetailsModalLabel">Building Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Content will be injected here -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <div>
        <?php include('../../components/footer.php'); ?>
        </div>
       
<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Pass PHP data to JavaScript -->
<script>
    window.buildingsData = <?php echo json_encode($buildings ?? []); ?>;
    window.userId = <?php echo $_SESSION['user_id'] ?? 0; ?>;
</script>

<script src="../js/script.js"></script>
<?php include('../../../../components/scripts.php'); ?>
</body>
</html>