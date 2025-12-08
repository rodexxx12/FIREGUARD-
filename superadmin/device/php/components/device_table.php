<!-- Filter Overlay -->
<div class="filter-overlay" id="filterOverlay" onclick="toggleFilterPanel()"></div>

<!-- Filter Panel - Side Panel -->
<div class="filter-panel" id="filterPanel">
    <div class="filter-panel-header">
        <h3><i class="fa fa-filter" style="margin-right: 8px;"></i> Filters & Actions</h3>
        <button class="btn btn-sm btn-outline-secondary" onclick="toggleFilterPanel()" title="Close Filters">
            <i class="fa fa-times"></i>
        </button>
    </div>
    <div class="filter-panel-body">
        <!-- Search Fields -->
        <div class="filter-group">
            <h6 class="mb-2"><i class="fas fa-search"></i> Search Fields</h6>
            <label for="device-search" class="form-label">Device Number: <span class="shortcut-hint">(Ctrl+F)</span></label>
            <input type="text" id="device-search" name="search_term" class="form-control form-control-sm" placeholder="Search by device number..." data-toggle="tooltip" title="Press Ctrl+F to focus quickly" value="<?= htmlspecialchars(isset($search_term) ? $search_term : (isset($_GET['search_term']) ? $_GET['search_term'] : '')) ?>">
        </div>
        <div class="filter-group">
            <label for="serial-search" class="form-label">Serial Number:</label>
            <input type="text" id="serial-search" class="form-control form-control-sm" placeholder="Search by serial number...">
        </div>

        <!-- Filter Options -->
        <div class="filter-group">
            <h6 class="mb-2"><i class="fas fa-filter"></i> Filter Options</h6>
            <label for="device-status" class="form-label">Device Status:</label>
            <select name="status" id="device-status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="approved" <?= (isset($status_filter) && $status_filter==='approved') || (isset($_GET['status']) && $_GET['status']==='approved') ? 'selected' : '' ?>>Approved</option>
                <option value="pending" <?= (isset($status_filter) && $status_filter==='pending') || (isset($_GET['status']) && $_GET['status']==='pending') ? 'selected' : '' ?>>Pending</option>
                <option value="deactivated" <?= (isset($status_filter) && $status_filter==='deactivated') || (isset($_GET['status']) && $_GET['status']==='deactivated') ? 'selected' : '' ?>>Deactivated</option>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="filter-group">
            <h6 class="mb-2"><i class="fas fa-cog"></i> Actions</h6>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDeviceModal" style="width: 100%; margin-bottom: 5px;">
                <i class="fa fa-plus"></i> Add New Device
            </button>
            <button type="button" class="btn btn-success btn-sm" id="refreshTableBtn" style="width: 100%; margin-bottom: 5px;">
                <i class="fa fa-refresh"></i> Refresh Table
            </button>
            <button type="button" class="btn btn-info btn-sm" id="exportCSVBtn" style="width: 100%; margin-bottom: 5px;">
                <i class="fa fa-file-excel-o"></i> Export CSV
            </button>
            <button type="button" class="btn btn-warning btn-sm" id="exportPDFBtn" style="width: 100%; margin-bottom: 5px;">
                <i class="fa fa-file-pdf-o"></i> Export PDF
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="printTableBtn" style="width: 100%; margin-bottom: 5px;">
                <i class="fa fa-print"></i> Print Table
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFiltersBtn" style="width: 100%; margin-top: 10px;" data-toggle="tooltip" title="Press Ctrl+R to clear all filters">
                <i class="fas fa-times"></i> Clear All Filters <span class="shortcut-hint">(Ctrl+R)</span>
            </button>
        </div>
        <div class="filter-group">
            <small class="text-muted" style="display: block; margin-top: 10px;">
                <span id="filterCount">0</span> filters applied |
                <span id="resultCount">0</span> results shown
            </small>
        </div>
    </div>
</div>

<div class="x_panel">
    <div class="x_title">
        <h2><strong>Device Management <small>Fire Detection Devices</small></strong></h2>
        <ul class="nav navbar-right panel_toolbox">
            <li>
                <a class="filter-toggle-btn" id="filterToggleBtn" title="Toggle Filters">
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                </a>
            </li>
            <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
            <li><a class="close-link"><i class="fa fa-close"></i></a></li>
        </ul>
        <div class="clearfix"></div>
    </div>
    <div class="x_content">
        <div class="row">
            <div class="col-sm-12">
                <div class="card-box table-responsive">
                    <table id="datatable" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Device #</th>
                                <th>Serial #</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="device-table-body">
                            <?php foreach ($devices as $device): ?>
                                <tr data-device-number="<?php echo htmlspecialchars(strtolower($device['device_number'])); ?>"
                                    data-serial-number="<?php echo htmlspecialchars(strtolower($device['serial_number'])); ?>"
                                    data-device-status="<?php echo htmlspecialchars(strtolower($device['status'])); ?>"
                                    data-device-type="fire detection device">
                                    <td><?= htmlspecialchars($device['device_number']) ?></td>
                                    <td><?= htmlspecialchars($device['serial_number']) ?></td>
                                    <td>Fire Detection Device</td>
                                    <td>
                                        <span class="status-badge status-<?= $device['status'] ?>">
                                            <i class="fa fa-circle"></i>
                                            <?= ucfirst($device['status']) ?>
                                        </span>
                                    </td>
                                    <td class="action-btns">
                                        <?php if ($device['status'] === 'approved'): ?>
                                            <!-- Approved device actions -->
                                            <button type="button" class="btn btn-warning btn-xs set-pending-btn" 
                                                    data-device-id="<?= $device['admin_device_id'] ?>" 
                                                    data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                    title="Set to Pending">
                                                <i class="fa fa-clock"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-xs deactivate-btn" 
                                                    data-device-id="<?= $device['admin_device_id'] ?>" 
                                                    data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                    title="Deactivate">
                                                <i class="fa fa-ban"></i>
                                            </button>
                                        <?php elseif ($device['status'] === 'pending'): ?>
                                            <!-- Pending device actions -->
                                            <button type="button" class="btn btn-success btn-xs approve-btn" 
                                                    data-device-id="<?= $device['admin_device_id'] ?>" 
                                                    data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                    title="Approve">
                                                <i class="fa fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-xs deactivate-btn" 
                                                    data-device-id="<?= $device['admin_device_id'] ?>" 
                                                    data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                    title="Deactivate">
                                                <i class="fa fa-ban"></i>
                                            </button>
                                        <?php elseif ($device['status'] === 'deactivated'): ?>
                                            <!-- Deactivated device actions -->
                                            <button type="button" class="btn btn-success btn-xs approve-btn" 
                                                    data-device-id="<?= $device['admin_device_id'] ?>" 
                                                    data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                    title="Approve">
                                                <i class="fa fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-xs set-pending-btn" 
                                                    data-device-id="<?= $device['admin_device_id'] ?>" 
                                                    data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                    title="Set to Pending">
                                                <i class="fa fa-clock"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- Delete button for all statuses -->
                                        <form method="POST" style="display:inline;" class="delete-form">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $device['admin_device_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-xs" title="Delete">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
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