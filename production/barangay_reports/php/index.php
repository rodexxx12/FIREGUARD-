<?php
session_start();
require_once '../../../db/db.php';

// Handle AJAX request for barangay data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_barangay_data') {
    $monthFilter = $_POST['month'] ?? 'all';
    $barangays = getBarangayReportsData($monthFilter);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'barangays' => $barangays]);
    exit;
}

// Get barangay data with report statistics
function getBarangayReportsData($monthFilter = null) {
    $conn = getDatabaseConnection();
    
    $monthCondition = ($monthFilter && $monthFilter !== 'all') ? 
        'AND YEAR(sir.date_occurrence) = YEAR(CURDATE()) AND MONTH(sir.date_occurrence) = ?' : '';
    
    $query = "
        SELECT 
            br.id, br.barangay_name, br.ir_number, br.latitude, br.longitude,
            COUNT(sir.id) as total_reports,
            COALESCE(SUM(sir.fatalities), 0) as total_fatalities,
            COALESCE(SUM(sir.injured), 0) as total_injured,
            COALESCE(SUM(sir.establishments_affected), 0) as total_affected,
            COALESCE(SUM(sir.estimated_damage), 0) as total_damage,
            COUNT(CASE WHEN sir.reports_status = 'final' THEN 1 END) as completed_reports,
            COUNT(CASE WHEN sir.reports_status = 'draft' THEN 1 END) as draft_reports,
            COUNT(CASE WHEN sir.reports_status = 'pending_review' THEN 1 END) as pending_reports,
            COUNT(CASE WHEN sir.reports_status = 'approved' THEN 1 END) as approved_reports
        FROM barangay br
        LEFT JOIN (
            SELECT 
                fd.id AS fire_data_id,
                COALESCE(fd.barangay_id, b.barangay_id) AS barangay_id
            FROM fire_data fd
            LEFT JOIN buildings b ON fd.building_id = b.id
        ) fd_map ON fd_map.barangay_id = br.id
        LEFT JOIN spot_investigation_reports sir ON fd_map.fire_data_id = sir.fire_data_id
        {$monthCondition}
        GROUP BY br.id, br.barangay_name, br.ir_number, br.latitude, br.longitude
        ORDER BY total_reports DESC, br.barangay_name ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($monthFilter && $monthFilter !== 'all' ? [$monthFilter] : []);
    return $stmt->fetchAll();
}

// Get all barangays (for dropdown)
function getAllBarangays() {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT DISTINCT br.id, br.barangay_name FROM barangay br ORDER BY br.barangay_name ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get available months for filter dropdown
function getAvailableMonths() {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            MONTH(sir.date_occurrence) as month_num,
            MONTHNAME(sir.date_occurrence) as month_name,
            YEAR(sir.date_occurrence) as year
        FROM spot_investigation_reports sir
        WHERE sir.date_occurrence IS NOT NULL
        ORDER BY YEAR(sir.date_occurrence) DESC, MONTH(sir.date_occurrence) DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get current filters from URL parameters
$monthFilter = $_GET['month'] ?? 'all';

$barangays = getBarangayReportsData($monthFilter);
$allBarangays = getAllBarangays();
$availableMonths = getAvailableMonths();
$totalBarangays = count($barangays);
?>

<?php include '../../components/header.php'; ?>

<style>
.barangay-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #e0e0e0;
}

.barangay-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}

.container-fluid {
    padding: 2rem 3rem;
}

.card {
    margin-bottom: 2rem;
}

.card-body {
    padding: 2rem;
}

.barangay-card .card-body {
    padding: 1.5rem;
}

.barangay-card .card-title {
    font-size: 1.1rem;
    color: #333;
}

.barangay-card .h6 {
    font-size: 1rem;
}

.btn {
    font-size: 1rem;
    padding: 0.75rem 1.5rem;
}

.btn-sm {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

.barangay-card .bg-light {
    background-color: #f8f9fa !important;
}

/* Filter Toggle Styles - Floating Button */
.filter-toggle-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    background-color: #ff6b35;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 6px;
    padding: 12px;
    transition: all 0.3s ease;
    z-index: 1001;
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.4);
}

.filter-toggle-btn:hover {
    background-color: #ff5722;
    box-shadow: 0 6px 20px rgba(255, 107, 53, 0.5);
    transform: scale(1.1);
}

.filter-toggle-btn.active {
    background-color: #e64a19;
}

.filter-toggle-btn.active:hover {
    background-color: #d84315;
    transform: scale(1.1);
}

.burger-line {
    width: 28px;
    height: 3px;
    background-color: #fff;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.filter-toggle-btn.active .burger-line:nth-child(1) {
    transform: rotate(45deg) translate(8px, 8px);
}

.filter-toggle-btn.active .burger-line:nth-child(2) {
    opacity: 0;
}

.filter-toggle-btn.active .burger-line:nth-child(3) {
    transform: rotate(-45deg) translate(8px, -8px);
}

@media (max-width: 768px) {
    .filter-toggle-btn {
        bottom: 20px;
        right: 20px;
        width: 56px;
        height: 56px;
    }
}

/* Filter Overlay */
.filter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.filter-overlay.active {
    opacity: 1;
    visibility: visible;
}

/* Filter Panel */
.filter-panel {
    position: fixed;
    top: 0;
    right: -400px;
    width: 380px;
    height: 100%;
    background-color: #fff;
    box-shadow: -2px 0 10px rgba(0,0,0,0.2);
    z-index: 1000;
    transition: right 0.3s ease;
    overflow-y: auto;
    padding: 2rem;
}

.filter-panel.active {
    right: 0;
}

.filter-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e0e0e0;
}

.filter-panel-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
}

.filter-panel-body {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.filter-group label {
    font-weight: 600;
    color: #555;
    font-size: 0.95rem;
}

.filter-group .form-select,
.filter-group .btn {
    width: 100%;
}

@media (max-width: 768px) {
    .filter-panel {
        width: 100%;
        right: -100%;
    }
}
</style>

<!-- Include header components -->
<?php include '../../components/header.php'; ?>

</head>
  <!-- Include header with all necessary libraries -->
  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
    <div class="container-fluid">
            
                
                <!-- Filter Overlay -->
                <div class="filter-overlay" id="filterOverlay" onclick="toggleFilterPanel()"></div>
                
                <!-- Filter Panel -->
                <div class="filter-panel" id="filterPanel">
                    <div class="filter-panel-header">
                        <h3><i class="fas fa-filter me-2"></i>Filters</h3>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleFilterPanel()" title="Close Filters">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="filter-panel-body">
                        <div class="filter-group">
                            <label for="monthFilter">Month:</label>
                            <select id="monthFilter" class="form-select" onchange="filterByMonth()">
                                <option value="all" <?php echo $monthFilter === 'all' ? 'selected' : ''; ?>>All Months</option>
                                <?php foreach ($availableMonths as $month): ?>
                                <option value="<?php echo $month['month_num']; ?>" <?php echo $monthFilter == $month['month_num'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($month['month_name'] . ' ' . $month['year']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-secondary btn-sm" onclick="resetMonthFilter()" title="Reset Month Filter">
                                <i class="fas fa-times me-1"></i>Reset Month
                            </button>
                        </div>
                        <div class="filter-group">
                            <label for="sortSelect">Sort by:</label>
                            <select id="sortSelect" class="form-select" onchange="sortBarangays()">
                                <option value="incidents_desc">Incidents (High to Low)</option>
                                <option value="incidents_asc">Incidents (Low to High)</option>
                                <option value="name_asc">Name (A to Z)</option>
                                <option value="name_desc">Name (Z to A)</option>
                                <option value="damage_desc">Damage (High to Low)</option>
                                <option value="damage_asc">Damage (Low to High)</option>
                            </select>
                            <button class="btn btn-outline-secondary btn-sm" onclick="resetSortFilter()" title="Reset Sort Filter">
                                <i class="fas fa-times me-1"></i>Reset Sort
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Floating Filter Toggle Button -->
                <button class="filter-toggle-btn" id="filterToggleBtn" onclick="toggleFilterPanel()" title="Toggle Filters">
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                </button>
                
                <!-- Barangay Cards Grid -->
                <div class="row g-3 mb-3" id="barangayGrid">
                    <!-- Cards will be rendered by JavaScript -->
                </div>
        
                <!-- Pagination -->
                <div class="row">
                    <div class="col-12">
                        <div class="text-center mb-3">
                            <span id="showingInfo" class="text-muted"></span>
                        </div>
                        <nav aria-label="Barangay pagination">
                            <ul class="pagination justify-content-center" id="paginationContainer">
                                <!-- Pagination buttons will be generated dynamically -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Optimized JavaScript with better performance
        const BarangayManager = {
            currentPage: 1,
            itemsPerPage: 12,
            barangays: <?php echo json_encode($barangays); ?>,
            currentMonthFilter: '<?php echo $monthFilter; ?>',
            
            init() {
                this.renderBarangays();
                this.updatePagination();
            },
            
            viewBarangayReports(barangayId, barangayName) {
                let url = `reports.php?barangay_id=${barangayId}&name=${encodeURIComponent(barangayName)}`;
                if (this.currentMonthFilter !== 'all') {
                    url += `&month=${this.currentMonthFilter}`;
                }
                window.location.href = url;
            },
            
            viewMonthlyReports(barangayId) {
                let url = `reports.php?barangay_id=${barangayId}&period=monthly`;
                if (this.currentMonthFilter !== 'all') {
                    url += `&month=${this.currentMonthFilter}`;
                }
                window.location.href = url;
            },
            
            resetMonthFilter() {
                document.getElementById('monthFilter').value = 'all';
                this.currentMonthFilter = 'all';
                this.loadBarangayData('all');
            },
            
            resetSortFilter() {
                document.getElementById('sortSelect').value = 'incidents_desc';
                this.barangays.sort((a, b) => b.total_reports - a.total_reports);
                this.currentPage = 1;
                this.renderBarangays();
            },
            
            filterByMonth() {
                const monthValue = document.getElementById('monthFilter').value;
                this.currentMonthFilter = monthValue;
                this.loadBarangayData(monthValue);
            },
            
            async loadBarangayData(monthFilter) {
                const grid = document.getElementById('barangayGrid');
                grid.innerHTML = '<div class="col-12 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                try {
                    const formData = new FormData();
                    formData.append('month', monthFilter);
                    formData.append('action', 'get_barangay_data');
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.barangays = data.barangays;
                        this.currentPage = 1;
                        this.renderBarangays();
                        this.updateShowingInfo();
                    } else {
                        throw new Error(data.message || 'Unknown error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    grid.innerHTML = '<div class="col-12 text-center text-danger">Error loading data. Please try again.</div>';
                }
            },
            
            updateShowingInfo() {
                const showingInfoElement = document.getElementById('showingInfo');
                if (!showingInfoElement) {
                    return; // Element doesn't exist, skip update
                }
                const startItem = this.barangays.length > 0 ? (this.currentPage - 1) * this.itemsPerPage + 1 : 0;
                const endItem = Math.min(this.currentPage * this.itemsPerPage, this.barangays.length);
                showingInfoElement.textContent = 
                    `Showing ${startItem}-${endItem} of ${this.barangays.length} IR numbers`;
            },
            
            sortBarangays() {
                const sortValue = document.getElementById('sortSelect').value;
                
                const sortFunctions = {
                    'incidents_desc': (a, b) => b.total_reports - a.total_reports,
                    'incidents_asc': (a, b) => a.total_reports - b.total_reports,
                    'name_asc': (a, b) => a.barangay_name.localeCompare(b.barangay_name),
                    'name_desc': (a, b) => b.barangay_name.localeCompare(a.barangay_name),
                    'damage_desc': (a, b) => b.total_damage - a.total_damage,
                    'damage_asc': (a, b) => a.total_damage - b.total_damage
                };
                
                this.barangays.sort(sortFunctions[sortValue]);
                this.currentPage = 1;
                this.renderBarangays();
            },
            
            renderBarangays() {
                const grid = document.getElementById('barangayGrid');
                const startIndex = (this.currentPage - 1) * this.itemsPerPage;
                const endIndex = startIndex + this.itemsPerPage;
                const pageBarangays = this.barangays.slice(startIndex, endIndex);
                
                // Use DocumentFragment for better performance
                const fragment = document.createDocumentFragment();
                
                pageBarangays.forEach(barangay => {
                    const col = document.createElement('div');
                    col.className = 'col-lg-4 col-md-6 mb-3';
                    col.innerHTML = `
                        <div class="card barangay-card h-100 shadow-sm border-0">
                            <div class="card-body p-3">
                                <h6 class="card-title mb-3 fw-bold" style="font-size: 1.1rem;">
                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>${barangay.barangay_name}
                                </h6>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="small text-dark fw-bold mb-1">INCIDENTS</div>
                                            <div class="h6 mb-0 text-dark fw-bold">${barangay.total_reports || 0}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="small text-dark fw-bold mb-1">AFFECTED</div>
                                            <div class="h6 mb-0 text-dark fw-bold">${barangay.total_affected || 0}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="small text-dark fw-bold mb-1">INJURED</div>
                                            <div class="h6 mb-0 text-dark fw-bold">${barangay.total_injured || 0}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="small text-dark fw-bold mb-1">RESCUED</div>
                                            <div class="h6 mb-0 text-dark fw-bold">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="small text-dark fw-bold mb-1">DECEASED</div>
                                            <div class="h6 mb-0 text-dark fw-bold">${barangay.total_fatalities || 0}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="small text-dark fw-bold mb-1">LOSS</div>
                                            <div class="small mb-0 text-dark fw-bold">$${parseFloat(barangay.total_damage || 0).toLocaleString()}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button class="btn btn-warning w-100 btn-sm fw-bold" 
                                        onclick="BarangayManager.viewMonthlyReports(${barangay.id})"
                                        style="background-color: #ffc107; border-color: #ffc107;">
                                    <i class="fas fa-calendar me-1"></i>See Details
                                </button>
                            </div>
                        </div>
                    `;
                    fragment.appendChild(col);
                });
                
                grid.innerHTML = '';
                grid.appendChild(fragment);
                this.updatePagination();
            },
            
            changePage(direction) {
                const totalPages = Math.ceil(this.barangays.length / this.itemsPerPage);
                
                if (direction === -1 && this.currentPage > 1) {
                    this.currentPage--;
                } else if (direction === 1 && this.currentPage < totalPages) {
                    this.currentPage++;
                } else if (typeof direction === 'number') {
                    this.currentPage = direction;
                }
                
                this.renderBarangays();
                
                // Smooth scroll to top
                const contentArea = document.querySelector('.main-card');
                if (contentArea) {
                    contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            },
            
            updatePagination() {
                const totalPages = Math.ceil(this.barangays.length / this.itemsPerPage);
                const paginationContainer = document.getElementById('paginationContainer');
                
                if (totalPages <= 1) {
                    paginationContainer.style.display = 'none';
                    this.updateShowingInfo();
                    return;
                }
                
                paginationContainer.style.display = 'flex';
                paginationContainer.innerHTML = '';
                
                // Previous button
                const prevBtn = document.createElement('li');
                prevBtn.className = `page-item ${this.currentPage === 1 ? 'disabled' : ''}`;
                prevBtn.innerHTML = `<button class="page-link" onclick="BarangayManager.changePage(-1)" ${this.currentPage === 1 ? 'disabled' : ''}>Previous</button>`;
                paginationContainer.appendChild(prevBtn);
                
                // Page numbers with optimized logic
                const maxVisiblePages = 5;
                let startPage = Math.max(1, this.currentPage - Math.floor(maxVisiblePages / 2));
                let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                
                if (endPage - startPage + 1 < maxVisiblePages) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                }
                
                // First page and ellipsis
                if (startPage > 1) {
                    const firstPage = document.createElement('li');
                    firstPage.className = 'page-item';
                    firstPage.innerHTML = `<button class="page-link" onclick="BarangayManager.changePage(1)">1</button>`;
                    paginationContainer.appendChild(firstPage);
                    
                    if (startPage > 2) {
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = '<span class="page-link">...</span>';
                        paginationContainer.appendChild(ellipsis);
                    }
                }
                
                // Page numbers
                for (let i = startPage; i <= endPage; i++) {
                    const pageBtn = document.createElement('li');
                    pageBtn.className = `page-item ${i === this.currentPage ? 'active' : ''}`;
                    pageBtn.innerHTML = `<button class="page-link" onclick="BarangayManager.changePage(${i})">${i}</button>`;
                    paginationContainer.appendChild(pageBtn);
                }
                
                // Last page and ellipsis
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = '<span class="page-link">...</span>';
                        paginationContainer.appendChild(ellipsis);
                    }
                    
                    const lastPage = document.createElement('li');
                    lastPage.className = 'page-item';
                    lastPage.innerHTML = `<button class="page-link" onclick="BarangayManager.changePage(${totalPages})">${totalPages}</button>`;
                    paginationContainer.appendChild(lastPage);
                }
                
                // Next button
                const nextBtn = document.createElement('li');
                nextBtn.className = `page-item ${this.currentPage === totalPages ? 'disabled' : ''}`;
                nextBtn.innerHTML = `<button class="page-link" onclick="BarangayManager.changePage(1)" ${this.currentPage === totalPages ? 'disabled' : ''}>Next</button>`;
                paginationContainer.appendChild(nextBtn);
                
                this.updateShowingInfo();
            }
        };
        
        // Global functions for backward compatibility
        function viewBarangayReports(barangayId, barangayName) {
            BarangayManager.viewBarangayReports(barangayId, barangayName);
        }
        
        function viewMonthlyReports(barangayId) {
            BarangayManager.viewMonthlyReports(barangayId);
        }
        
        function resetMonthFilter() {
            BarangayManager.resetMonthFilter();
        }
        
        function resetSortFilter() {
            BarangayManager.resetSortFilter();
        }
        
        function filterByMonth() {
            BarangayManager.filterByMonth();
        }
        
        function sortBarangays() {
            BarangayManager.sortBarangays();
        }
        
        function changePage(direction) {
            BarangayManager.changePage(direction);
        }
        
        // Filter Panel Toggle Function
        function toggleFilterPanel() {
            const filterPanel = document.getElementById('filterPanel');
            const filterOverlay = document.getElementById('filterOverlay');
            const filterToggleBtn = document.getElementById('filterToggleBtn');
            
            filterPanel.classList.toggle('active');
            filterOverlay.classList.toggle('active');
            filterToggleBtn.classList.toggle('active');
        }
        
        // Close filter panel when clicking overlay
        document.addEventListener('click', function(event) {
            const filterPanel = document.getElementById('filterPanel');
            const filterOverlay = document.getElementById('filterOverlay');
            const filterToggleBtn = document.getElementById('filterToggleBtn');
            
            // If clicking outside the panel and overlay is active, close it
            if (filterOverlay.classList.contains('active') && 
                !filterPanel.contains(event.target) && 
                !filterToggleBtn.contains(event.target)) {
                toggleFilterPanel();
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            BarangayManager.init();
        });
    </script>
    
   <!-- Include header components -->
 <?php include '../../components/scripts.php'; ?>
</body>
</html>
