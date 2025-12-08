<?php
require_once __DIR__ . '../../functions/functions.php';

// Fetch statistics from database
try {
    require_once __DIR__ . '/../functions/db_connect.php';
    
    // Get total admins
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM admin");
    $stmt->execute();
    $totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get active admins
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM admin WHERE status = 'Active'");
    $stmt->execute();
    $activeAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

    // Get admins registered this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as recent FROM admin WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recentAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];

} catch (PDOException $e) {
    $totalAdmins = 0;
    $activeAdmins = 0;
    $recentAdmins = 0;
}
?>
<?php include('../../components/header.php'); ?>
<link rel="stylesheet" href="../css/style.css">
<style>
    /* Burger Menu Toggle Button Styles - Green with Animations */
    .filter-toggle-btn {
        display: flex !important;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: auto;
        height: auto;
        background: rgba(38, 185, 154, 0.08);
        border: 1px solid rgba(38, 185, 154, 0.2);
        cursor: pointer;
        padding: 6px 8px;
        margin: 0;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        visibility: visible !important;
        opacity: 1 !important;
        border-radius: 6px;
        position: relative;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(38, 185, 154, 0.15);
    }
    
    .filter-toggle-btn:hover {
        transform: scale(1.1);
        background: rgba(38, 185, 154, 0.15);
        border-color: rgba(38, 185, 154, 0.4);
        box-shadow: 0 3px 8px rgba(38, 185, 154, 0.25);
    }
    
    .filter-toggle-btn.active {
        transform: scale(1.1) rotate(90deg);
        background: rgba(38, 185, 154, 0.2);
        border-color: rgba(38, 185, 154, 0.5);
        box-shadow: 0 3px 10px rgba(38, 185, 154, 0.3);
    }
    
    .burger-line {
        width: 22px;
        height: 3px;
        background-color: #26B99A;
        border-radius: 3px;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        display: block;
        margin: 2.5px 0;
        position: relative;
        box-shadow: 0 2px 4px rgba(38, 185, 154, 0.4);
    }
    
    .filter-toggle-btn:hover .burger-line {
        background-color: #1e9d82;
        box-shadow: 0 3px 6px rgba(38, 185, 154, 0.5);
        transform: scaleX(1.1);
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(1) {
        transform: rotate(45deg) translate(6px, 6px);
        background-color: #26B99A;
        width: 22px;
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(2) {
        opacity: 0;
        transform: scaleX(0);
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(3) {
        transform: rotate(-45deg) translate(6px, -6px);
        background-color: #26B99A;
        width: 22px;
    }
    
    /* Filter Overlay with Animation */
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
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .filter-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    /* Filter Panel - Side Panel Style with Enhanced Animations */
    .filter-panel {
        position: fixed;
        top: 0;
        right: -400px;
        width: 380px;
        height: 100%;
        background-color: #fff;
        box-shadow: -2px 0 20px rgba(0,0,0,0.3);
        z-index: 1000;
        transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        padding: 2rem;
        transform: translateX(0);
    }
    
    .filter-panel.active {
        right: 0;
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    @keyframes slideInRight {
        0% {
            transform: translateX(100%);
            opacity: 0;
        }
        60% {
            transform: translateX(-5%);
        }
        100% {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .filter-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e0e0e0;
        animation: fadeInDown 0.5s ease 0.2s both;
    }
    
    @keyframes fadeInDown {
        0% {
            opacity: 0;
            transform: translateY(-20px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
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
        animation: fadeInUp 0.5s ease both;
    }
    
    .filter-panel.active .filter-group:nth-child(1) { animation-delay: 0.1s; }
    .filter-panel.active .filter-group:nth-child(2) { animation-delay: 0.15s; }
    .filter-panel.active .filter-group:nth-child(3) { animation-delay: 0.2s; }
    .filter-panel.active .filter-group:nth-child(4) { animation-delay: 0.25s; }
    .filter-panel.active .filter-group:nth-child(5) { animation-delay: 0.3s; }
    .filter-panel.active .filter-group:nth-child(6) { animation-delay: 0.35s; }
    
    @keyframes fadeInUp {
        0% {
            opacity: 0;
            transform: translateY(20px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .filter-group label {
        font-weight: 600;
        color: #555;
        font-size: 0.95rem;
    }
    
    .filter-group .form-control,
    .filter-group .form-select,
    .filter-group .btn {
        width: 100%;
    }
    
    .filter-group .form-control:focus,
    .filter-group .form-select:focus {
        border-color: #26B99A;
        box-shadow: 0 0 0 3px rgba(38, 185, 154, 0.1);
    }
    
    @media (max-width: 768px) {
        .filter-panel {
            width: 100%;
            right: -100%;
        }
    }
    
    .panel_toolbox {
        float: right;
        margin: 0;
        margin-right: 0 !important;
        list-style: none;
        padding: 0;
        min-width: 70px;
        display: flex;
        align-items: center;
        order: 3;
    }
    
    .panel_toolbox li {
        float: left;
        cursor: pointer;
        margin-left: 5px;
        display: flex;
        align-items: center;
        min-height: 32px;
    }
    
    .panel_toolbox li:first-child {
        margin-left: 0;
    }
    
    .panel_toolbox li a {
        padding: 5px;
        color: #C5C7CB;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        height: 100%;
    }
    
    .panel_toolbox li a:hover {
        color: #26B99A;
    }
    
    #filterToggleBtn {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: relative;
        z-index: 10;
        align-items: center;
        justify-content: center;
        min-height: 32px;
    }
    
    #filterToggleBtn .burger-line {
        background-color: #26B99A !important;
        display: block !important;
        visibility: visible !important;
        width: 22px !important;
        height: 3px !important;
    }
</style>
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
                        <label for="searchInput" class="form-label">Search Admins:</label>
                        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Name, email, username, contact...">
                    </div>
                    
                    <!-- Filter Options -->
                    <div class="filter-group">
                        <h6 class="mb-2"><i class="fas fa-filter"></i> Filter Options</h6>
                        <label for="statusFilter" class="form-label">Status:</label>
                        <select id="statusFilter" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Filter Info:</label>
                        <div class="filter-info bg-light p-2">
                            <span id="filterInfo" class="text-muted fst-italic"></span>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="filter-group">
                        <h6 class="mb-2"><i class="fas fa-cog"></i> Actions</h6>
                        <button id="clearFilters" class="btn btn-outline-danger btn-sm" style="width: 100%;">
                            <i class="fa fa-times"></i> Clear All Filters
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="addAdminBtn" style="width: 100%; margin-top: 10px;">
                            <i class="fa fa-user-plus"></i> Add New Admin
                        </button>
                    </div>
                    
                    <div class="filter-group" style="display: none;">
                        <small class="text-muted" style="display: block; margin-top: 10px;">
                            Showing <span id="showingCount">0</span> of <span id="totalCount"><?= count($admins) ?></span> admins
                        </small>
                    </div>
                </div>
            </div>
       
        <div class="row">
          <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
              <div class="x_title x_title--flex">
                <h4>Admin Records</h4>
                <ul class="nav navbar-right panel_toolbox" style="margin-right: 0; padding-right: 0;">
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
                <?php include('user_table.php'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Admin Modal -->
      <?php include('edit_user_modal.php'); ?>
      <!-- /Edit Admin Modal -->

      <!-- Add Admin Modal -->
      <?php include('add_admin_modal.php'); ?>
      <!-- /Add Admin Modal -->

      <?php include('../../components/footer.php'); ?>
    </div>
  </div>

   <!-- JS Scripts -->
   <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- jQuery -->
    <script src="../../../vendors/jquery/dist/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="../../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- FastClick -->
    <script src="../../../vendors/fastclick/lib/fastclick.js"></script>
    <!-- NProgress -->
    <script src="../../../vendors/nprogress/nprogress.js"></script>
    <!-- iCheck -->
    <script src="../../../vendors/iCheck/icheck.min.js"></script>

    <!-- Custom Theme Scripts -->
    <script src="../../../build/js/custom.min.js"></script>
    
    <!-- Filter Panel Toggle Function - Must be defined globally for onclick handlers -->
    <script>
        // Filter Panel Toggle Function - Must be defined globally for onclick handlers
        function toggleFilterPanel() {
            const filterPanel = document.getElementById('filterPanel');
            const filterOverlay = document.getElementById('filterOverlay');
            const filterToggleBtn = document.getElementById('filterToggleBtn');
            
            if (filterPanel && filterOverlay && filterToggleBtn) {
                filterPanel.classList.toggle('active');
                filterOverlay.classList.toggle('active');
                filterToggleBtn.classList.toggle('active');
            }
        }
    </script>
    
    <script src="../js/script.js"></script>
    <script src="../js/admin_crud.js"></script>
    
    <script>
        $(document).ready(function() {
            // Burger Menu Toggle Functionality for Side Panel
            $('#filterToggleBtn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleFilterPanel();
            });
            
            // Close filter panel when clicking overlay
            document.addEventListener('click', function(event) {
                const filterPanel = document.getElementById('filterPanel');
                const filterOverlay = document.getElementById('filterOverlay');
                const filterToggleBtn = document.getElementById('filterToggleBtn');
                
                // If clicking outside the panel and overlay is active, close it
                if (filterOverlay && filterOverlay.classList.contains('active') && 
                    filterPanel && !filterPanel.contains(event.target) && 
                    filterToggleBtn && !filterToggleBtn.contains(event.target)) {
                    toggleFilterPanel();
                }
            });
        });
    </script>
</body>
</html>