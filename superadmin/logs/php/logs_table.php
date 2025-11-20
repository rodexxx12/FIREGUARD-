<?php
require_once __DIR__ . '/../functions/fetch_users.php';
?>

<div class="row">
  <div class="col-md-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">System Logs</h5>
      </div>
      <div class="card-body">
        <!-- Enhanced Search and Filter Section -->
        <div class="search-filter-section mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-4 search-box">
              <label for="searchInput">Search</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search logs...">
              </div>
            </div>
            <div class="col-md-3 filter-box">
              <label for="logLevelFilter">Log Level</label>
              <select class="form-select" id="logLevelFilter">
                <option value="">All Levels</option>
                <option value="INFO">INFO</option>
                <option value="WARNING">WARNING</option>
                <option value="ERROR">ERROR</option>
              </select>
            </div>
            <div class="col-md-3 filter-box">
              <label for="eventTypeFilter">Event Type</label>
              <select class="form-select" id="eventTypeFilter">
                <option value="">All Events</option>
              </select>
            </div>
            <div class="col-md-2 clear-filters-box d-flex align-items-end">
              <button class="btn btn-primary me-2" onclick="refreshFilters()">
                <i class="fas fa-sync-alt"></i> Refresh
              </button>
              <button class="btn btn-secondary" id="clearFilters" onclick="clearFilters()">
                <i class="fas fa-times"></i> Clear
              </button>
            </div>
          </div>
        </div>

        <!-- Results Info Section -->
        <div class="results-info-section mb-3 d-flex justify-content-between align-items-center">
          <div class="results-counter">
            <i class="fas fa-database"></i> <span id="showingInfo">Showing 1 to 10 of <?= count($logs) ?> entries</span>
          </div>
          <div class="filter-info" id="filterInfo"></div>
        </div>

        <!-- Logs Table -->
        <div class="table-responsive">
          <table class="table table-striped" id="logsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Event Type</th>
                <th>Log Level</th>
                <th>Message</th>
                <th>Location</th>
                <th>User Action</th>
                <th>Fire Detected</th>
                <th>Created At</th>

              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
                <tr>
                  <td><?= htmlspecialchars($log['id']) ?></td>
                  <td><?= htmlspecialchars($log['event_type']) ?></td>
                  <td>
                    <span class="log-level-<?= strtolower($log['log_level']) ?>">
                      <?= htmlspecialchars($log['log_level']) ?>
                    </span>
                  </td>
                  <td>
                    <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($log['log_message']) ?>">
                      <?= htmlspecialchars($log['log_message']) ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($log['location'] ?? 'N/A') ?></td>
                  <td><?= htmlspecialchars($log['user_action'] ?? 'N/A') ?></td>
                  <td>
                    <?php if ($log['fire_detected']): ?>
                      <span class="badge bg-danger">Yes</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">No</span>
                    <?php endif; ?>
                  </td>
                  <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>

                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div>
            <span id="showingCount">Showing 1 to 10</span> of <span id="totalCount"><?= count($logs) ?></span> entries
          </div>
          <div>
            <button class="btn btn-sm btn-outline-primary" onclick="previousPage()">Previous</button>
            <span class="mx-2" id="pageInfo">Page 1</span>
            <button class="btn btn-sm btn-outline-primary" onclick="nextPage()">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let currentPage = 1;
const itemsPerPage = 10;
let filteredLogs = <?= json_encode($logs) ?>;
let allLogs = <?= json_encode($logs) ?>;

// Initialize filters
document.addEventListener('DOMContentLoaded', function() {
    loadEventTypes();
    updateTable();
});

function loadEventTypes() {
    const eventTypes = [...new Set(allLogs.map(log => log.event_type))];
    const select = document.getElementById('eventTypeFilter');
    select.innerHTML = '<option value="">All Events</option>';
    eventTypes.forEach(type => {
        const option = document.createElement('option');
        option.value = type;
        option.textContent = type;
        select.appendChild(option);
    });
}

function refreshFilters() {
    loadEventTypes();
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('logLevelFilter').value = '';
    document.getElementById('eventTypeFilter').value = '';
    filterLogs();
}

function filterLogs() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const logLevel = document.getElementById('logLevelFilter').value;
    const eventType = document.getElementById('eventTypeFilter').value;

    filteredLogs = allLogs.filter(log => {
        const matchesSearch = !searchTerm || 
            log.event_type.toLowerCase().includes(searchTerm) ||
            log.log_message.toLowerCase().includes(searchTerm) ||
            (log.user_action && log.user_action.toLowerCase().includes(searchTerm));
        
        const matchesLevel = !logLevel || log.log_level === logLevel;
        const matchesEventType = !eventType || log.event_type === eventType;

        return matchesSearch && matchesLevel && matchesEventType;
    });

    currentPage = 1;
    updateTable();
}

function updateTable() {
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageLogs = filteredLogs.slice(startIndex, endIndex);

    const tbody = document.querySelector('#logsTable tbody');
    tbody.innerHTML = '';

    pageLogs.forEach(log => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${log.id}</td>
            <td>${escapeHtml(log.event_type)}</td>
            <td><span class="log-level-${log.log_level.toLowerCase()}">${log.log_level}</span></td>
            <td><div class="text-truncate" style="max-width: 200px;" title="${escapeHtml(log.log_message)}">${escapeHtml(log.log_message)}</div></td>
            <td>${escapeHtml(log.location || 'N/A')}</td>
            <td>${escapeHtml(log.user_action || 'N/A')}</td>
            <td>${log.fire_detected ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
            <td>${new Date(log.created_at).toLocaleString()}</td>
        `;
        tbody.appendChild(row);
    });

    updatePagination();
}

function updatePagination() {
    const totalPages = Math.ceil(filteredLogs.length / itemsPerPage);
    const startItem = (currentPage - 1) * itemsPerPage + 1;
    const endItem = Math.min(currentPage * itemsPerPage, filteredLogs.length);

    document.getElementById('showingInfo').textContent = `Showing ${startItem} to ${endItem} of ${filteredLogs.length} entries`;
    document.getElementById('showingCount').textContent = `Showing ${startItem} to ${endItem}`;
    document.getElementById('totalCount').textContent = filteredLogs.length;
    document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;

    // Show filter info if any filter is active
    const logLevel = document.getElementById('logLevelFilter').value;
    const eventType = document.getElementById('eventTypeFilter').value;
    const searchTerm = document.getElementById('searchInput').value;
    let filterText = '';
    if (logLevel) filterText += `Log Level: <b>${logLevel}</b> `;
    if (eventType) filterText += `Event Type: <b>${eventType}</b> `;
    if (searchTerm) filterText += `Search: <b>${escapeHtml(searchTerm)}</b> `;
    document.getElementById('filterInfo').innerHTML = filterText.trim();
}

function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        updateTable();
    }
}

function nextPage() {
    const totalPages = Math.ceil(filteredLogs.length / itemsPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        updateTable();
    }
}



function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.getElementById('searchInput').addEventListener('input', filterLogs);
document.getElementById('logLevelFilter').addEventListener('change', filterLogs);
document.getElementById('eventTypeFilter').addEventListener('change', filterLogs);


</script> 