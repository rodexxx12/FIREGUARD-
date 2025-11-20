<?php
require_once __DIR__ . '../../functions/functions.php';

// Fetch statistics from database
try {
    require_once __DIR__ . '/../functions/db_connect.php';
    
    $pdo = getDatabaseConnection();
    
    // Get total logs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs");
    $totalLogs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get error logs
    $stmt = $pdo->prepare("SELECT COUNT(*) as error_count FROM system_logs WHERE log_level = 'ERROR'");
    $stmt->execute();
    $errorLogs = $stmt->fetch(PDO::FETCH_ASSOC)['error_count'];

    // Get logs from this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as recent FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recentLogs = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];

    // Get fire detections
    $stmt = $pdo->query("SELECT COUNT(*) as fire_count FROM system_logs WHERE fire_detected = 1");
    $fireDetections = $stmt->fetch(PDO::FETCH_ASSOC)['fire_count'];
    
} catch (PDOException $e) {
    $totalLogs = 0;
    $errorLogs = 0;
    $recentLogs = 0;
    $fireDetections = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <title>FireGuard | System Logs</title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <!-- Font Awesome 6 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <!-- Gentelella Template CSS -->
  <link href=".../.././vendors/nprogress/nprogress.css" rel="stylesheet">
  <link href="../../../vendors/iCheck/skins/flat/green.css" rel="stylesheet">
  <link href="../../../vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <link href="../../../build/css/custom.min.css" rel="stylesheet">
  <link href="../css/style.css" rel="stylesheet">
  <style>
    /* Simple table styles */
    #logsTable thead th {
        background: #ffffff !important;
        color: #333333 !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        padding: 12px 8px !important;
        border: 1px solid #dee2e6 !important;
    }
    
    #logsTable tbody tr {
        border-bottom: 1px solid #e0e0e0 !important;
    }
    
    #logsTable tbody tr:hover {
        background-color: #ffffff !important;
    }
    
    #logsTable tbody td {
        padding: 12px 8px !important;
        border: 1px solid #e0e0e0 !important;
        font-size: 14px !important;
        color: #333333 !important;
    }
    
    .table-responsive {
        background: #ffffff !important;
        border: 1px solid #e0e0e0 !important;
        border-radius: 8px !important;
    }
    
    /* Log level badges */
    .log-level-info {
        background-color: #ffffff !important;
        color: #0c5460 !important;
        padding: 4px 8px !important;
        border-radius: 4px !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        border: 2px solid #17a2b8 !important;
    }
    
    .log-level-warning {
        background-color: #ffffff !important;
        color: #856404 !important;
        padding: 4px 8px !important;
        border-radius: 4px !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        border: 2px solid #ffc107 !important;
    }
    
    .log-level-error {
        background-color: #ffffff !important;
        color: #721c24 !important;
        padding: 4px 8px !important;
        border-radius: 4px !important;
        font-size: 12px !important;
        font-weight: 500 !important;
        border: 2px solid #dc3545 !important;
    }
    

    

    
    /* Statistics Cards Styles */
    .stats-card {
        padding: 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s ease;
    }
    
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .stats-icon {
        margin-right: 15px;
        opacity: 0.9;
    }
    
    .stats-info h3 {
        margin: 0;
        font-size: 28px;
        font-weight: bold;
    }
    
    .stats-info p {
        margin: 5px 0 0 0;
        font-size: 14px;
        opacity: 0.9;
    }
  </style>
</head>
<body class="nav-md">
  <div class="container body">
    <div class="main_container">
              <!-- sidebar menu -->
              <?php include('../../components/sidebar.php'); ?>

          </div>
          <!-- /sidebar menu -->
        </div>
      </div>

      <!-- top navigation -->
      <?php include("../../components/navigation.php")?>
      <!-- /top navigation -->

     <!-- Page Content -->
<div class="right_col" role="main">
<div class="">
  <div class="page-title">
    <div class="title_right">
      <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
        <!-- Optional search content -->
      </div>
    </div>
  </div>

  <div class="clearfix"></div>

  <!-- Statistics Cards -->
  <div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
      <div class="stats-card bg-primary text-white">
        <div class="stats-icon">
          <i class="fas fa-list-alt"></i>
        </div>
        <div class="stats-info">
          <h3 id="totalLogs"><?= $totalLogs ?></h3>
          <p>Total Logs</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
      <div class="stats-card bg-danger text-white">
        <div class="stats-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stats-info">
          <h3 id="errorLogs"><?= $errorLogs ?></h3>
          <p>Error Logs</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
      <div class="stats-card bg-warning text-white">
      <div class="stats-icon">
          <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stats-info">
          <h3 id="recentLogs"><?= $recentLogs ?></h3>
          <p>This Month</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
      <div class="stats-card bg-success text-white">
        <div class="stats-icon">
          <i class="fas fa-fire"></i>
        </div>
        <div class="stats-info">
          <h3 id="fireDetections"><?= $fireDetections ?></h3>
          <p>Fire Detections</p>
        </div>
      </div>
    </div>
  </div>

    <div class="row">
        <div class="col-md-12 col-sm-12">
      
            <div class="clearfix"></div>
            </div>

                <div class="x_content">
                  <div class="row">
                    <div class="col-sm-12">
                      <div class="card-box table-responsive">
                        <div class="container mt-4">
                          <?php include('logs_table.php'); ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- /Page Content -->


    </div>
  </div>

  </div><?php include('../../components/footer.php'); ?></div>

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
</body>
</html>