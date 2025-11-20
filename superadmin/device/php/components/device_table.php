<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
            <i class="fas fa-plus-circle"></i> Add New Device
        </button>
        <form method="GET" class="d-flex align-items-center" style="gap:10px;" id="device-filter-form">
            <input type="text" id="device-search" name="search_term" class="form-control" placeholder="Search devices..." 
                   value="<?= htmlspecialchars(isset($search_term) ? $search_term : (isset($_GET['search_term']) ? $_GET['search_term'] : '')) ?>">
            <select name="status" id="device-status" class="form-select">
                <option value="">All Statuses</option>
                <option value="approved" <?= (isset($status_filter) && $status_filter==='approved') || (isset($_GET['status']) && $_GET['status']==='approved') ? 'selected' : '' ?>>Approved</option>
                <option value="pending" <?= (isset($status_filter) && $status_filter==='pending') || (isset($_GET['status']) && $_GET['status']==='pending') ? 'selected' : '' ?>>Pending</option>
                <option value="deactivated" <?= (isset($status_filter) && $status_filter==='deactivated') || (isset($_GET['status']) && $_GET['status']==='deactivated') ? 'selected' : '' ?>>Deactivated</option>
            </select>
        </form>
    </div>
    
    <div class="card-body">
        <?php if (empty($devices)): ?>
            <div class="alert alert-info">No fire detection devices found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
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
                                        <i class="fas fa-circle"></i>
                                        <?= ucfirst($device['status']) ?>
                                    </span>
                                </td>
                                <td class="action-btns">
                                    <?php if ($device['status'] === 'approved'): ?>
                                        <!-- Approved device actions -->
                                        <button type="button" class="btn btn-warning btn-sm set-pending-btn" 
                                                data-device-id="<?= $device['admin_device_id'] ?>" 
                                                data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                title="Set to Pending">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm deactivate-btn" 
                                                data-device-id="<?= $device['admin_device_id'] ?>" 
                                                data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php elseif ($device['status'] === 'pending'): ?>
                                        <!-- Pending device actions -->
                                        <button type="button" class="btn btn-success btn-sm approve-btn" 
                                                data-device-id="<?= $device['admin_device_id'] ?>" 
                                                data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm deactivate-btn" 
                                                data-device-id="<?= $device['admin_device_id'] ?>" 
                                                data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php elseif ($device['status'] === 'deactivated'): ?>
                                        <!-- Deactivated device actions -->
                                        <button type="button" class="btn btn-success btn-sm approve-btn" 
                                                data-device-id="<?= $device['admin_device_id'] ?>" 
                                                data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-warning btn-sm set-pending-btn" 
                                                data-device-id="<?= $device['admin_device_id'] ?>" 
                                                data-device-number="<?= htmlspecialchars($device['device_number']) ?>"
                                                title="Set to Pending">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <!-- Delete button for all statuses -->
                                    <form method="POST" style="display:inline;" class="delete-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $device['admin_device_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div> 

<div id="device-pagination">
<?php if (isset($total_pages) && $total_pages > 1): ?>
<nav aria-label="Devices pagination" class="mt-3" data-current-page="<?= isset($page)?(int)$page:(isset($_GET['page'])?(int)$_GET['page']:1) ?>">
    <ul class="pagination justify-content-center">
        <?php 
            $current_page = isset($page) ? (int)$page : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
            $queryBase = [
                'search_term' => isset($search_term) ? $search_term : (isset($_GET['search_term']) ? $_GET['search_term'] : ''),
                'status' => isset($status_filter) ? $status_filter : (isset($_GET['status']) ? $_GET['status'] : ''),
            ];
            function buildQuery($base, $pageNum) {
                $params = $base;
                $params['page'] = $pageNum;
                $parts = [];
                foreach ($params as $k => $v) {
                    if ($v !== '' && $v !== null) {
                        $parts[] = urlencode($k) . '=' . urlencode($v);
                    }
                }
                return '?' . implode('&', $parts);
            }
        ?>
        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current_page > 1 ? buildQuery($queryBase, $current_page - 1) : '#' ?>" data-page="<?= $current_page - 1 ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                <a class="page-link" href="<?= buildQuery($queryBase, $i) ?>" data-page="<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $current_page < $total_pages ? buildQuery($queryBase, $current_page + 1) : '#' ?>" data-page="<?= $current_page + 1 ?>">Next</a>
        </li>
    </ul>
    <div class="text-center small text-muted">Showing 10 per page • Page <?= $current_page ?> of <?= $total_pages ?></div>
</nav>
<?php endif; ?>
</div>