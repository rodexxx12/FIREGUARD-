<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../index.php");
    exit();
}

require_once('../../db/db.php');

if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return getDatabaseConnection();
    }
}

// ============================================
// SECURITY: CSRF Token Functions
// ============================================
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        error_log("CSRF validation failed: No token in session");
        return false;
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        error_log("CSRF validation failed: Token mismatch from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }
    
    return true;
}

/**
 * Secure input sanitization
 */
function sanitizeInput($input, $type = 'string', $maxLength = null) {
    if ($input === null || $input === '') {
        return null;
    }
    
    $input = str_replace(["\0", "\x00"], '', $input);
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    switch ($type) {
        case 'string':
            $input = trim($input);
            $input = strip_tags($input);
            if ($maxLength !== null && mb_strlen($input) > $maxLength) {
                $input = mb_substr($input, 0, $maxLength);
            }
            break;
            
        case 'int':
            $input = filter_var($input, FILTER_VALIDATE_INT);
            if ($input === false) {
                return null;
            }
            break;
            
        case 'float':
            $input = filter_var($input, FILTER_VALIDATE_FLOAT);
            if ($input === false) {
                return null;
            }
            break;
    }
    
    return $input;
}

// Handle DELETE request for removing a building
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header('Content-Type: application/json');

    try {
        // ============================================
        // SECURITY: JSON Input Validation with CSRF
        // ============================================
        $raw_input = file_get_contents('php://input');
        if (empty($raw_input)) {
            throw new Exception('No input provided');
        }
        
        $input = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }

        // Validate CSRF token
        if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Security validation failed. Please refresh the page and try again.'
            ]);
            exit;
        }

        if (!isset($input['building_id'])) {
            throw new Exception('Building ID is required');
        }

        $building_id = sanitizeInput($input['building_id'], 'int');
        $user_id = $_SESSION['user_id'] ?? 0;

        if ($building_id === null || $building_id <= 0) {
            throw new Exception('Invalid building ID');
        }

        $conn = getDBConnection();

        // Verify ownership
        $stmt = $conn->prepare("SELECT id FROM buildings WHERE id = ? AND user_id = ?");
        $stmt->execute([$building_id, $user_id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
            exit;
        }

        // Delete building
        $stmt = $conn->prepare("DELETE FROM buildings WHERE id = ?");

        if (!$stmt->execute([$building_id])) {
            throw new Exception('Delete failed');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Building deleted successfully'
        ]);

    } catch (Exception $e) {
        error_log("Building deletion error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete building. Please try again.'
        ]);
    }
    exit;
}

// Generate CSRF token for JavaScript
$csrf_token = generateCSRFToken();

// Fetch all buildings for the table
$buildings = [];
if (isset($_SESSION['user_id'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, building_name, building_type, address, total_floors, last_inspected FROM buildings WHERE user_id = ? ORDER BY building_name");
        $stmt->execute([$_SESSION['user_id']]);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $buildings = [];
    }
}

include('../../components/header.php');
?>
<!-- DataTables Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<style>

    .page-title {
        margin-bottom: 20px;
        padding: 0 5px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border-radius: 12px;
        border: 1px dashed #c7c7c7;
    }

    .empty-state i {
        font-size: 64px;
        color: #0d6efd;
    }

    .badge-residential { background-color: #007bff; }
    .badge-commercial { background-color: #28a745; }
    .badge-industrial { background-color: #ffc107; color: #000; }
    .badge-institutional { background-color: #17a2b8; }

    .badge {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 30px;
    }

    .table-card {
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        background: #fff;
    }

    #buildingsTable {
        width: 100% !important;
        min-width: 900px;
    }

    #buildingsTable thead th {
        background: #f5f7fb;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: .08em;
        color: #6b7280;
        border-bottom: 2px solid #e5e7eb;
    }

    .btn-outline-success {
        border-color: #2ecc71;
        color: #2ecc71;
    }

    .btn-outline-success:hover {
        background-color: #2ecc71;
        color: #fff;
    }

    /* Align DataTables length menu and search controls in one row */
    .dataTables_wrapper {
        padding: 0;
    }

    .dataTables_wrapper .row:first-child,
    .dataTables_wrapper .dt-buttons + .row,
    .dataTables_wrapper > .row:first-of-type {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        margin: 0 0 15px 0 !important;
        padding: 0 !important;
        flex-wrap: nowrap !important;
        width: 100% !important;
    }
    

    .dataTables_wrapper .row:first-child > div,
    .dataTables_wrapper .row:first-child > div[class*="col"],
    .dataTables_wrapper .dt-buttons + .row > div,
    .dataTables_wrapper > .row:first-of-type > div {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
        float: none !important;
        display: inline-block !important;
        margin: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    /* Position search filter container at the right corner */
    .dataTables_wrapper .row:first-child > div:last-child,
    .dataTables_wrapper .dt-buttons + .row > div:last-child,
    .dataTables_wrapper > .row:first-of-type > div:last-child {
        margin-left: auto !important;
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
        order: 999 !important;
    }

    .dataTables_wrapper .dataTables_length {
        float: none !important;
        text-align: left !important;
        margin-bottom: 0 !important;
        padding: 0 !important;
        order: 1;
        display: inline-block !important;
        vertical-align: middle !important;
    }

    .dataTables_wrapper .dataTables_filter {
        float: none !important;
        text-align: right !important;
        margin-bottom: 0 !important;
        margin-left: auto !important;
        padding: 0 !important;
        order: 999 !important;
        display: inline-block !important;
        vertical-align: middle !important;
        width: auto !important;
        flex: 0 0 auto !important;
    }

    .dataTables_wrapper .dataTables_length label {
        margin-bottom: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px;
        font-weight: normal;
        white-space: nowrap;
        width: auto !important;
        vertical-align: middle !important;
    }

    .dataTables_wrapper .dataTables_filter label {
        margin-bottom: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 8px;
        font-weight: normal;
        white-space: nowrap;
        width: auto !important;
        vertical-align: middle !important;
    }

    .dataTables_wrapper .dataTables_length select {
        margin: 0 5px;
        padding: 5px 8px;
        border-radius: 4px;
        border: 1px solid #ced4da;
        display: inline-block;
    }

    .dataTables_wrapper .dataTables_filter input {
        margin-left: 10px;
        padding: 5px 10px;
        border-radius: 4px;
        border: 1px solid #ced4da;
        display: inline-block;
    }

    /* Align DataTables info and pagination in one row */
    .dataTables_wrapper .row:last-child {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        margin: 15px 0 0 0 !important;
        padding: 0 !important;
        flex-wrap: nowrap !important;
    }

    .dataTables_wrapper .row:last-child > div {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
    }

    .dataTables_wrapper .dataTables_info {
        float: none !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        order: 1;
    }

    .dataTables_wrapper .dataTables_paginate {
        float: none !important;
        text-align: right !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        order: 2;
    }

    @media (max-width: 768px) {
        .dataTables_wrapper .row:first-child {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 15px;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            text-align: left !important;
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 100%;
            margin-left: 0;
            margin-top: 5px;
        }

        .dataTables_wrapper .row:last-child {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 15px;
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            text-align: left !important;
            width: 100%;
        }
    }
</style>
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
        <?php if (empty($buildings)): ?>
            <div class="x_panel mt-4">
                <div class="x_content">
                    <div class="empty-state">
                        <i class="bi bi-building"></i>
                        <h4 class="mt-3">No Buildings Found</h4>
                        <p class="text-muted">Add your first building to see it listed here.</p>
                        <a href="main.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-lg"></i> Register Building
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-12 col-sm-12">
                    <div class="x_panel table-card">
                        <div class="x_title">
                            <h2><small>Registered Buildings</small></h2>
                            <ul class="nav navbar-right panel_toolbox">
                                <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                                <li class="dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-wrench"></i></a>
                                    <div class="dropdown-menu" role="menu">
                                        <a class="dropdown-item" href="main.php">Register New Building</a>
                                        <a class="dropdown-item" href="#" id="panelRefresh">Refresh Table</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" id="toolboxCopy"><i class="bi bi-clipboard"></i> Copy</a>
                                        <a class="dropdown-item" href="#" id="toolboxCSV"><i class="bi bi-filetype-csv"></i> CSV</a>
                                        <a class="dropdown-item" href="#" id="toolboxPrint"><i class="bi bi-printer"></i> Print</a>
                                    </div>
                                </li>
                                <!-- <li><a class="close-link"><i class="fa fa-close"></i></a></li> -->
                            </ul>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <div class="table-responsive">
                                <table id="buildingsTable" class="table table-striped table-bordered jambo_table bulk_action">
                                    <thead>
                                        <tr class="headings">
                                            <th>
                                                <input type="checkbox" id="check-all" class="flat">
                                            </th>
                                            <th class="column-title">Building Name</th>
                                            <th class="column-title">Type</th>
                                            <th class="column-title">Address</th>
                                            <th class="column-title">Floors</th>
                                            <th class="column-title">Last Inspection</th>
                                            <th class="column-title no-link last"><span class="nobr">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($buildings as $index => $building): ?>
                                            <tr class="<?php echo ($index % 2 == 0) ? 'even' : 'odd'; ?> pointer building-card" data-id="<?php echo $building['id']; ?>">
                                                <td class="a-center ">
                                                    <input type="checkbox" class="flat building-select" value="<?php echo $building['id']; ?>">
                                                </td>
                                                <td><?php echo htmlspecialchars($building['building_name']); ?></td>
                                                <td>
                                                    <?php
                                                    $badge_class = '';
                                                    switch ($building['building_type']) {
                                                        case 'residential': $badge_class = 'badge-residential'; break;
                                                        case 'commercial': $badge_class = 'badge-commercial'; break;
                                                        case 'industrial': $badge_class = 'badge-industrial'; break;
                                                        case 'institutional': $badge_class = 'badge-institutional'; break;
                                                        default: $badge_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($building['building_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($building['address']); ?></td>
                                                <td><?php echo htmlspecialchars($building['total_floors'] ?? '-'); ?></td>
                                                <td><?php echo !empty($building['last_inspected']) ? date('M d, Y', strtotime($building['last_inspected'])) : 'Never'; ?></td>
                                                <td class="last">
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-warning btn-sm edit-building" data-id="<?php echo $building['id']; ?>" title="Edit">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm delete-building" data-id="<?php echo $building['id']; ?>" title="Delete">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include('../../components/footer.php'); ?>

    <?php include('../../components/scripts.php'); ?>
    
    <!-- Reload DataTables core after scripts.php to ensure it attaches to the correct jQuery instance -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- DataTables Buttons Extension JS - Load after scripts.php to ensure jQuery is available -->
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <!-- Initialize buildings table after all scripts are loaded -->
    <script>
        (function() {
            const endpoint = 'buildings-table.php';
            let buildingsTable;

            function initializeBuildingsTable() {
                const $buildingsTable = $('#buildingsTable');
                if (!$buildingsTable.length) {
                    return;
                }

                // Check if DataTables is loaded
                if (typeof $.fn.DataTable === 'undefined') {
                    console.error('DataTables is not loaded');
                    setTimeout(initializeBuildingsTable, 100);
                    return;
                }

                // Check if DataTables Buttons extension is loaded
                if (typeof $.fn.DataTable.Buttons === 'undefined') {
                    console.warn('DataTables Buttons extension is not loaded, initializing without buttons');
                    // Try to initialize without buttons
                    buildingsTable = $buildingsTable.DataTable({
                        processing: true,
                        responsive: true,
                        autoWidth: false,
                        pageLength: 10,
                        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                        language: {
                            lengthMenu: 'Show _MENU_ entries',
                            search: 'Search:',
                            info: 'Showing _START_ to _END_ of _TOTAL_ buildings',
                            infoEmpty: 'No buildings to display',
                            zeroRecords: 'No matching buildings found',
                            paginate: {
                                previous: 'Prev',
                                next: 'Next'
                            }
                        }
                    });
                    return;
                }

                buildingsTable = $buildingsTable.DataTable({
                    dom: "lfrtip",
                    buttons: [
                        { extend: 'copy', className: 'btn-sm btn-outline-primary' },
                        { extend: 'csv', className: 'btn-sm btn-outline-primary' },
                        { extend: 'excel', className: 'btn-sm btn-outline-primary' },
                        { extend: 'pdfHtml5', className: 'btn-sm btn-outline-primary' },
                        { extend: 'print', className: 'btn-sm btn-outline-primary' }
                    ],
                    processing: true,
                    responsive: true,
                    autoWidth: false,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    language: {
                        lengthMenu: 'Show _MENU_ entries',
                        search: 'Search:',
                        info: 'Showing _START_ to _END_ of _TOTAL_ buildings',
                        infoEmpty: 'No buildings to display',
                        zeroRecords: 'No matching buildings found',
                        paginate: {
                            previous: 'Prev',
                            next: 'Next'
                        }
                    }
                });
            }

            // Wait for jQuery and then initialize
            function waitForJQuery(callback) {
                if (typeof jQuery !== 'undefined' && typeof $ !== 'undefined') {
                    $(document).ready(function() {
                        // Small delay to ensure all DataTables scripts are loaded
                        setTimeout(function() {
                            initializeBuildingsTable();
                            callback();
                        }, 100);
                    });
                } else {
                    setTimeout(function() {
                        waitForJQuery(callback);
                    }, 50);
                }
            }

            // Setup event handlers after initialization
            function setupEventHandlers() {
                $('#refreshBuildings, #panelRefresh').on('click', function(event) {
                    if (event) {
                        event.preventDefault();
                    }
                    $(this).addClass('disabled');
                    location.reload();
                });

                // Toolbox dropdown actions - trigger DataTables buttons
                $('#toolboxCopy').on('click', function(event) {
                    event.preventDefault();
                    if (buildingsTable && buildingsTable.buttons) {
                        try {
                            // Trigger copy button by index (0 = copy, based on buttons array order)
                            buildingsTable.button(0).trigger();
                        } catch(e) {
                            // Fallback: find button in DOM
                            $('.dt-button.buttons-copy, button.dt-button:contains("Copy")').first().trigger('click');
                        }
                    }
                });

                $('#toolboxCSV').on('click', function(event) {
                    event.preventDefault();
                    if (buildingsTable && buildingsTable.buttons) {
                        try {
                            // Trigger CSV button by index (1 = csv, based on buttons array order)
                            buildingsTable.button(1).trigger();
                        } catch(e) {
                            // Fallback: find button in DOM
                            $('.dt-button.buttons-csv, button.dt-button:contains("CSV")').first().trigger('click');
                        }
                    }
                });

                $('#toolboxPrint').on('click', function(event) {
                    event.preventDefault();
                    if (buildingsTable && buildingsTable.buttons) {
                        try {
                            // Trigger print button by index (4 = print, based on buttons array order)
                            buildingsTable.button(4).trigger();
                        } catch(e) {
                            // Fallback: find button in DOM
                            $('.dt-button.buttons-print, button.dt-button:contains("Print")').first().trigger('click');
                        }
                    }
                });

                $('#globalSearchBtn').on('click', function () {
                    const query = $('#globalSearchInput').val();
                    if (buildingsTable) {
                        buildingsTable.search(query).draw();
                    }
                });

                $('#globalSearchInput').on('keyup', function (e) {
                    if (!buildingsTable) return;
                    if (e.key === 'Enter') {
                        buildingsTable.search(this.value).draw();
                    } else if (!this.value.length) {
                        buildingsTable.search('').draw();
                    }
                });

                $(document).on('change', '#check-all', function() {
                    const isChecked = $(this).prop('checked');
                    $('.building-select').prop('checked', isChecked);
                });

                $(document).on('change', '.building-select', function() {
                    const total = $('.building-select').length;
                    const checked = $('.building-select:checked').length;
                    $('#check-all').prop('checked', total === checked);
                });

                $(document).on('click', '.edit-building', function() {
                    const buildingId = $(this).data('id');
                    window.location.href = `main.php?edit_building=${buildingId}`;
                });

                $(document).on('click', '.delete-building', function() {
                    const buildingId = $(this).data('id');
                    const buildingName = $(this).closest('tr').find('td:first').text();

                    Swal.fire({
                        title: 'Delete Building',
                        html: `Are you sure you want to delete <strong>${buildingName}</strong>?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Delete',
                        cancelButtonText: 'Cancel',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return $.ajax({
                                type: 'DELETE',
                                url: endpoint,
                                contentType: 'application/json',
                                data: JSON.stringify({ 
                                    building_id: buildingId,
                                    csrf_token: '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>'
                                }),
                                dataType: 'json'
                            }).catch(error => {
                                let errorMsg = 'Failed to delete building';
                                if (error.responseJSON && error.responseJSON.message) {
                                    errorMsg = error.responseJSON.message;
                                }
                                Swal.showValidationMessage(errorMsg);
                                return Promise.reject(error);
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed && result.value) {
                            if (result.value.status === 'success') {
                                Swal.fire('Deleted!', result.value.message, 'success');
                                if (buildingsTable && $.fn.DataTable.isDataTable('#buildingsTable')) {
                                    buildingsTable.row($(`tr[data-id="${buildingId}"]`)).remove().draw();
                                    if (!buildingsTable.data().count()) {
                                        location.reload();
                                    } else {
                                        $('#check-all').prop('checked', false);
                                    }
                                } else {
                                    $(`tr[data-id="${buildingId}"]`).remove();
                                }
                            } else {
                                Swal.fire('Error', result.value.message || 'Unknown error', 'error');
                            }
                        }
                    });
                });
            }

            // Start initialization process
            waitForJQuery(setupEventHandlers);
        })();
    </script>
</body>
</html>