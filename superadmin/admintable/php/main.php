<?php
require_once __DIR__ . '../../functions/functions.php';

// Fetch statistics from database
try {
    require_once __DIR__ . '/../functions/db_connect.php';
    
    // Get total admins
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin");
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
       
        <div class="row">
          <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="x_panel">
              <div class="x_title x_title--flex">
           
                <div class="x_title_actions">
                  <button type="button" class="btn btn-success" id="addAdminBtn">
                    <i class="fa fa-user-plus"></i> Add New Admin
                  </button>
                </div>
                <ul class="nav navbar-right panel_toolbox">
                  <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a>
                  </li>
                  <li><a class="close-link"><i class="fa fa-close"></i></a>
                  </li>
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
    <script src="../js/script.js"></script>
    <script src="../js/admin_crud.js"></script>
</body>
</html>