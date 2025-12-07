<div class="x_panel">
    <div class="x_title">
        <h2><strong>Device Management <small>Fire Detection Devices</small></strong></h2>
        <ul class="nav navbar-right panel_toolbox">
            <li>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                    <i class="fa fa-plus"></i> Add New Device
                </button>
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
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" id="device-search" name="search_term" class="form-control" placeholder="Search devices..." 
                                   value="<?= htmlspecialchars(isset($search_term) ? $search_term : (isset($_GET['search_term']) ? $_GET['search_term'] : '')) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="status" id="device-status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="approved" <?= (isset($status_filter) && $status_filter==='approved') || (isset($_GET['status']) && $_GET['status']==='approved') ? 'selected' : '' ?>>Approved</option>
                                <option value="pending" <?= (isset($status_filter) && $status_filter==='pending') || (isset($_GET['status']) && $_GET['status']==='pending') ? 'selected' : '' ?>>Pending</option>
                                <option value="deactivated" <?= (isset($status_filter) && $status_filter==='deactivated') || (isset($_GET['status']) && $_GET['status']==='deactivated') ? 'selected' : '' ?>>Deactivated</option>
                            </select>
                        </div>
                    </div>
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
                                <tr>
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