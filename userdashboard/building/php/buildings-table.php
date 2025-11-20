<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

require_once('../../db/db.php');

if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return getDatabaseConnection();
    }
}

// Handle DELETE request for removing a building
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['building_id'])) {
            throw new Exception('Building ID is required');
        }

        $building_id = intval($input['building_id']);
        $user_id = $_SESSION['user_id'] ?? 0;

        if ($building_id <= 0) {
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
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX request for fetching building data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_building') {
    header('Content-Type: application/json');

    if (!isset($_GET['building_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Building ID is required']);
        exit;
    }

    try {
        $conn = getDBConnection();
        $building_id = intval($_GET['building_id']);
        $user_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("SELECT * FROM buildings WHERE id = ? AND user_id = ?");
        $stmt->execute([$building_id, $user_id]);
        $building = $stmt->fetch();

        if (!$building) {
            echo json_encode(['status' => 'error', 'message' => 'Building not found or not authorized']);
            exit;
        }

        $building['has_sprinkler_system'] = (bool)$building['has_sprinkler_system'];
        $building['has_fire_alarm'] = (bool)$building['has_fire_alarm'];
        $building['has_fire_extinguishers'] = (bool)$building['has_fire_extinguishers'];
        $building['has_emergency_exits'] = (bool)$building['has_emergency_exits'];
        $building['has_emergency_lighting'] = (bool)$building['has_emergency_lighting'];
        $building['has_fire_escape'] = (bool)$building['has_fire_escape'];

        echo json_encode(['status' => 'success', 'data' => $building]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch building data: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch all buildings for the table
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
        <div class="page-title">
            <div class="title_left">

           
            <div class="title_right">
                
            </div>
           
        </div>
         </div>
        <div class="clearfix"></div>

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
                            <h2>Default Example <small>Registered Buildings</small></h2>
                            <ul class="nav navbar-right panel_toolbox">
                                <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                                <li class="dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-wrench"></i></a>
                                    <div class="dropdown-menu" role="menu">
                                        <a class="dropdown-item" href="main.php">Register New Building</a>
                                        <a class="dropdown-item" href="#" id="panelRefresh">Refresh Table</a>
                                    </div>
                                </li>
                                <li><a class="close-link"><i class="fa fa-close"></i></a></li>
                            </ul>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <p class="text-muted font-13 m-b-30">
                                DataTables has most features enabled by default, so all you need to do is call the constructor: <code>$().DataTable();</code>
                            </p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <a href="main.php" class="btn btn-success">
                                    <i class="bi bi-plus-lg"></i> Register New Building
                                </a>
                                <button class="btn btn-outline-secondary" id="refreshBuildings">
                                    <i class="bi bi-arrow-repeat"></i> Refresh
                                </button>
                            </div>
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
                                                        <button class="btn btn-info btn-sm view-building" data-id="<?php echo $building['id']; ?>" title="View">
                                                            <i class="bi bi-eye-fill"></i>
                                                        </button>
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

    <!-- Building Details Modal -->
    <div class="modal fade" id="buildingDetailsModal" tabindex="-1" aria-labelledby="buildingDetailsModalLabel">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="buildingDetailsModalLabel">Building Details</h5>
                    <button type="button" class="btn-close" id="closeModalBtn" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Content injected dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="closeModalBtnFooter">Close</button>
                    <button type="button" class="btn btn-primary" id="editBuildingBtn">Edit Building</button>
                </div>
            </div>
        </div>
    </div>

    <?php include('../../components/footer.php'); ?>

    <script>
        $(document).ready(function() {
            const endpoint = 'buildings-table.php';
            let buildingsTable;

            function initializeBuildingsTable() {
                const $buildingsTable = $('#buildingsTable');
                if (!$buildingsTable.length) {
                    return;
                }

                buildingsTable = $buildingsTable.DataTable({
                    dom: "Blfrtip",
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

            initializeBuildingsTable();

            $('#refreshBuildings, #panelRefresh').on('click', function(event) {
                if (event) {
                    event.preventDefault();
                }
                $(this).addClass('disabled');
                location.reload();
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

            $(document).on('click', '.view-building', function() {
                const buildingId = $(this).data('id');

                Swal.fire({
                    title: 'Loading Building Details',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        $.ajax({
                            url: endpoint,
                            method: 'GET',
                            dataType: 'json',
                            data: {
                                action: 'get_building',
                                building_id: buildingId
                            },
                            success: function(response) {
                                Swal.close();
                                if (response.status === 'success') {
                                    const building = response.data;

                                    const formatValue = (value, fallback = 'Not provided') => {
                                        if (value === null || value === undefined) {
                                            return fallback;
                                        }
                                        const trimmed = value.toString().trim();
                                        return trimmed.length ? trimmed : fallback;
                                    };

                                    const formatNumberValue = (value, fallback = 'Not provided') => {
                                        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
                                            return fallback;
                                        }
                                        return value;
                                    };

                                    const formatDateValue = (value) => {
                                        if (!value) {
                                            return 'Not inspected';
                                        }
                                        const parsedDate = new Date(value);
                                        if (Number.isNaN(parsedDate.getTime())) {
                                            return value;
                                        }
                                        return parsedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                    };

                                    const capitalize = (value) => {
                                        const formatted = formatValue(value, 'Unspecified');
                                        return formatted.charAt(0).toUpperCase() + formatted.slice(1);
                                    };

                                    const detailItem = (label, value) => `
                                        <div class="detail-item">
                                            <span class="detail-label">${label}</span>
                                            <span class="detail-item-value">${value}</span>
                                        </div>
                                    `;

                                    const safetyFeatures = [
                                        { label: 'Sprinkler System', active: !!building.has_sprinkler_system },
                                        { label: 'Fire Alarm', active: !!building.has_fire_alarm },
                                        { label: 'Fire Extinguishers', active: !!building.has_fire_extinguishers },
                                        { label: 'Emergency Exits', active: !!building.has_emergency_exits },
                                        { label: 'Emergency Lighting', active: !!building.has_emergency_lighting },
                                        { label: 'Fire Escape', active: !!building.has_fire_escape }
                                    ];

                                    const safetyBadges = safetyFeatures.map(feature => `
                                        <span class="safety-badge ${feature.active ? 'active' : ''}">${feature.label}</span>
                                    `).join('');

                                    const locationSection = (building.latitude && building.longitude) ? `
                                        <div class="detail-section">
                                            <span class="detail-label">Location</span>
                                            <div class="location-grid">
                                                ${detailItem('Latitude', formatValue(building.latitude, 'Not set'))}
                                                ${detailItem('Longitude', formatValue(building.longitude, 'Not set'))}
                                            </div>
                                        </div>
                                    ` : '';

                                    const buildingDetails = `
                                        <div class="building-detail-card">
                                            <div class="building-detail-header">
                                                <div>
                                                    <span class="detail-label">Building</span>
                                                    <h4>${formatValue(building.building_name, 'Unnamed Building')}</h4>
                                                    <p class="detail-meta">${formatValue(building.address, 'Address not provided')}</p>
                                                </div>
                                                <span class="detail-type-badge">${capitalize(building.building_type)}</span>
                                            </div>

                                            <div class="detail-grid">
                                                ${detailItem('Type', capitalize(building.building_type))}
                                                ${detailItem('Floors', formatNumberValue(building.total_floors, 'Not set'))}
                                                ${detailItem('Floor Area', building.building_area ? `${building.building_area} sq m` : 'Not set')}
                                                ${detailItem('Construction Year', formatValue(building.construction_year, 'Not set'))}
                                                ${detailItem('Last Inspection', formatDateValue(building.last_inspected))}
                                            </div>

                                            <div class="detail-section">
                                                <span class="detail-label">Contact</span>
                                                <div class="detail-grid">
                                                    ${detailItem('Contact Person', formatValue(building.contact_person, 'Not provided'))}
                                                    ${detailItem('Contact Number', formatValue(building.contact_number, 'Not provided'))}
                                                </div>
                                            </div>

                                            <div class="detail-divider"></div>

                                            <div class="detail-section">
                                                <span class="detail-label">Safety Features</span>
                                                <div class="safety-badges">
                                                    ${safetyBadges}
                                                </div>
                                            </div>

                                            ${locationSection}
                                        </div>
                                    `;

                                    $('#buildingDetailsModal .modal-body').html(buildingDetails);
                                    $('#buildingDetailsModal').data('building-id', buildingId).modal('show');
                                } else {
                                    Swal.fire('Error', response.message || 'Failed to load building details', 'error');
                                }
                            },
                            error: function() {
                                Swal.close();
                                Swal.fire('Error', 'Failed to load building details. Please try again.', 'error');
                            }
                        });
                    }
                });
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
                            data: JSON.stringify({ building_id: buildingId }),
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

            $('#editBuildingBtn').click(function() {
                const buildingId = $('#buildingDetailsModal').data('building-id');
                $('#buildingDetailsModal').modal('hide');
                if (buildingId) {
                    window.location.href = `main.php?edit_building=${buildingId}`;
                }
            });

            $('#closeModalBtn, #closeModalBtnFooter').click(function() {
                $('#buildingDetailsModal').modal('hide');
            });

            $('#buildingDetailsModal').on('hidden.bs.modal', function () {
                $(this).find('.modal-body').empty();
                $(this).removeData('building-id');
            });
        });
    </script>

    <?php include('../../../../components/scripts.php'); ?>
</body>
</html>