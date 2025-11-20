<?php
// Admin Table Component
require_once '../functions/admin_crud.php';

// Fetch all admins
$admins = get_all_admins();
?>

<div class="datatable-wrapper">
  <div class="datatable-header">
   
  </div>

  <div class="datatable-toolbar">
    <div class="row g-3">
      <div class="col-lg-4 col-md-6">
        <label for="searchInput" class="datatable-label">
          <i class="fa fa-search"></i> Search Admins
        </label>
        <div class="input-group">
          <span class="input-group-addon"><i class="fa fa-search"></i></span>
          <input type="text" id="searchInput" class="form-control" placeholder="Name, email, username, contact...">
        </div>
      </div>
      <div class="col-lg-3 col-md-4">
        <label for="statusFilter" class="datatable-label">
          <i class="fa fa-toggle-on"></i> Status
        </label>
        <select id="statusFilter" class="form-control">
          <option value="">All Status</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
        </select>
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="datatable-label">
          <i class="fa fa-info-circle"></i> Filter Info
        </label>
        <div class="filter-info bg-light">
          <span id="filterInfo" class="text-muted fst-italic"></span>
        </div>
      </div>
      <div class="col-lg-2 col-md-4">
        <label class="datatable-label">
          <i class="fa fa-broom"></i> Actions
        </label>
        <button id="clearFilters" class="btn btn-outline-danger w-100">
          <i class="fa fa-times"></i> Clear Filters
        </button>
      </div>
    </div>
  </div>

  <div class="datatable-status">
    <div class="datatable-status-item">
      <i class="fa fa-users text-primary"></i>
      <span>Showing <strong id="showingCount">0</strong> of <strong id="totalCount"><?= count($admins) ?></strong> admins</span>
    </div>
  </div>

  <div class="table-responsive">
    <table id="adminTable" class="table table-striped table-bordered dt-responsive nowrap jambo_table bulk_action">
      <thead>
        <tr class="headings">
          <th class="column-title">ID</th>
          <th class="column-title">Full Name</th>
          <th class="column-title">Email</th>
          <th class="column-title">Username</th>
          <th class="column-title">Contact Number</th>
          <th class="column-title">Role</th>
          <th class="column-title">Status</th>
          <th class="column-title">Created At</th>
          <th class="column-title">Updated At</th>
          <th class="column-title no-link last text-center"><span class="nobr">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $admin): ?>
          <tr class="even pointer"
              data-search="<?= htmlspecialchars(strtolower($admin['full_name'] . ' ' . $admin['email'] . ' ' . $admin['username'] . ' ' . $admin['contact_number'])) ?>"
              data-status="<?= htmlspecialchars($admin['status']) ?>"
              data-role="<?= htmlspecialchars($admin['role']) ?>">
            <td><?= htmlspecialchars($admin['admin_id']) ?></td>
            <td><?= htmlspecialchars($admin['full_name']) ?></td>
            <td><?= htmlspecialchars($admin['email']) ?></td>
            <td><?= htmlspecialchars($admin['username']) ?></td>
            <td><?= htmlspecialchars($admin['contact_number']) ?></td>
            <td><?= htmlspecialchars(ucfirst($admin['role'])) ?></td>
            <td><span class="status-<?= strtolower($admin['status']) ?>"><?= htmlspecialchars($admin['status']) ?></span></td>
            <td><?= htmlspecialchars($admin['created_at']) ?></td>
            <td><?= htmlspecialchars($admin['updated_at']) ?></td>
            <td class="action-btns text-center">
              <button class="btn btn-sm btn-primary edit-btn" data-id="<?= $admin['admin_id'] ?>">
                <i class="fa fa-edit"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="datatable-pagination" id="paginationControls">
    <button class="btn btn-default" id="prevPage" disabled>Previous</button>
    <span>Page <strong id="currentPage">1</strong> of <strong id="totalPages">1</strong></span>
    <button class="btn btn-default" id="nextPage" disabled>Next</button>
  </div>
</div>