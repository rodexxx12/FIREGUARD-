<?php require_once __DIR__ . '/../functions/functions.php'; ?>

<?php
// Check if user is logged in, if not redirect to index
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../index.php');
    exit();
}
?>

<?php include('../../components/header.php'); ?>
<link rel="stylesheet" href="../css/style.css">
<body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <div class="col-md-3 left_col">
                <div class="left_col scroll-view">
                    <!-- /menu footer buttons -->
                    <?php include('../../components/sidebar.php'); ?>
                </div>
                <!-- /menu footer buttons -->
            </div>
        </div>

        <!-- top navigation -->
        <?php include('../../components/navigation.php')?>
        <!-- /top navigation -->

        <div class="right_col" role="main">
            <main class="main-content">
                <div class="row">
                    <div class="container-fluid">
                       
                        
            
                        
                        <!-- Device List -->
                        <div class="card mt-0">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-microchip me-2"></i>Devices</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <button class="btn btn-modern btn-refresh" id="refreshDeviceList">
                                        <i class="fas fa-sync-alt me-1"></i> Refresh
                                    </button>
                                    <button class="btn btn-modern btn-primary-modern" id="addDeviceBtn">
                                        <i class="fas fa-plus-circle me-2"></i>Add Device
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Advanced Filters -->
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="statusFilter" class="form-label"><i class="fas fa-filter me-1"></i>Status Filter</label>
                                        <select id="statusFilter" class="form-select">
                                            <option value="">All Status</option>
                                            <option value="online">Online</option>
                                            <option value="offline">Offline</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="activeFilter" class="form-label"><i class="fas fa-power-off me-1"></i>Active Filter</label>
                                        <select id="activeFilter" class="form-select">
                                            <option value="">All Devices</option>
                                            <option value="Active">Active Only</option>
                                            <option value="Inactive">Inactive Only</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="deviceTypeFilter" class="form-label"><i class="fas fa-microchip me-1"></i>Device Type</label>
                                        <select id="deviceTypeFilter" class="form-select">
                                            <option value="">All Types</option>
                                            <option value="Fire Sensor">Fire Sensor</option>
                                            <option value="Smoke Detector">Smoke Detector</option>
                                            <option value="Heat Sensor">Heat Sensor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="dateRangeFilter" class="form-label"><i class="fas fa-calendar me-1"></i>Last Updated</label>
                                        <select id="dateRangeFilter" class="form-select">
                                            <option value="">All Dates</option>
                                            <option value="today">Today</option>
                                            <option value="week">This Week</option>
                                            <option value="month">This Month</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Filter Actions -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <button id="clearFilters" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-times-circle me-1"></i>Clear All Filters
                                        </button>
                                        <span class="ms-3 text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Use the filters above to narrow down your device list. All filters work together for precise results.
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table id="devicesTable" class="table table-hover table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Device Name</th>
                                                <th>Device Number</th>
                                                <th>Serial Number</th>
                                                <th>Status</th>
                                                <th>Active</th>
                                                <th>Last Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="devicesTableBody">
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Add Device Modal (Bootstrap) -->
                        <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addDeviceModalLabel">Add New Device</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addDeviceForm">
                                            <div class="mb-3">
                                                <label for="device_name" class="form-label">Device Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="device_name" name="device_name" placeholder="Enter device name (e.g., Fire Sensor 001)" required>
                                                <div id="device_name_validation" class="form-text"></div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="device_number" class="form-label">Device Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="device_number" name="device_number" placeholder="Format: DV1-PHI-000345" required>
                                                <div id="device_number_validation" class="form-text"></div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="serial_number" class="form-label">Serial Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="serial_number" name="serial_number" placeholder="Format: SEN-2527-005871" required>
                                                <div id="serial_number_validation" class="form-text"></div>
                                            </div>
                                            <div class="alert alert-info" role="alert">
                                                Device number and serial number must exist in the approved devices list.
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-modern btn-secondary-modern" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-modern btn-primary-modern" id="addDeviceSubmit">Add Device</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Device Modal (Bootstrap) -->
                        <div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editDeviceModalLabel"><i class="fas fa-edit me-2"></i>Edit Device</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="editDeviceForm">
                                            <input type="hidden" id="edit_device_id" name="device_id">
                                            <div class="mb-3">
                                                <label for="edit_device_name" class="form-label">Device Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="edit_device_name" name="device_name" placeholder="Enter device name" required>
                                                <div id="edit_device_name_validation" class="form-text"></div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_device_number" class="form-label">Device Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="edit_device_number" name="device_number" placeholder="Format: DV1-PHI-000345" required>
                                                <div id="edit_device_number_validation" class="form-text"></div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_serial_number" class="form-label">Serial Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="edit_serial_number" name="serial_number" placeholder="Format: SEN-2527-005871" required>
                                                <div id="edit_serial_number_validation" class="form-text"></div>
                                            </div>
                                            <div class="alert alert-info" role="alert">
                                                Device number and serial number must exist in the approved devices list.
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-modern btn-secondary-modern" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-modern btn-primary-modern" id="saveEditDevice">Save Changes</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- DataTables JS (Core DataTables only) -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Custom Scripts -->
    <script src="../js/script.js"></script>
    
    <!-- Additional vendor scripts -->
    <script src="../../../vendors/jquery/dist/jquery.min.js"></script>
    <script src="../../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../vendors/fastclick/lib/fastclick.js"></script>
    <script src="../../../vendors/nprogress/nprogress.js"></script>
    <script src="../../../vendors/gauge.js/dist/gauge.min.js"></script>
    <script src="../../../vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
    <script src="../../../vendors/iCheck/icheck.min.js"></script>
    <script src="../../../vendors/skycons/skycons.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.pie.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.time.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.stack.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.resize.js"></script>
    <script src="../../../vendors/flot.orderbars/js/jquery.flot.orderBars.js"></script>
    <script src="../../../vendors/flot-spline/js/jquery.flot.spline.min.js"></script>
    <script src="../../../vendors/flot.curvedlines/curvedLines.js"></script>
    <script src="../../../vendors/DateJS/build/date.js"></script>
    <script src="../../../vendors/jqvmap/dist/jquery.vmap.js"></script>
    <script src="../../../vendors/jqvmap/dist/maps/jquery.vmap.world.js"></script>
    <script src="../../../vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"></script>
    <script src="../../../vendors/moment/min/moment.min.js"></script>
    <script src="../../../vendors/bootstrap-daterangepicker/daterangepicker.js"></script>
    
    <!-- Prevent Chart.js initialization errors -->
    <script>
        // Override Chart.js initialization to prevent errors
        window.init_charts = function() {
            console.log('Chart initialization skipped for device page');
        };
        
        // Load custom.min.js safely
        $(document).ready(function() {
            // Load custom.min.js after ensuring Chart.js is available or skip chart init
            if (typeof Chart !== 'undefined') {
                $.getScript('../../../build/js/custom.min.js').fail(function() {
                    console.log('Custom.min.js not loaded - chart functionality disabled');
                });
            } else {
                console.log('Chart.js not available - skipping custom.min.js');
            }
        });
    </script>
</body>
</html>
