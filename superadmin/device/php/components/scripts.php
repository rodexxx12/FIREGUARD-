</div>
</div>
<?php
include('../../../components/scripts.php');
?>
<!-- jQuery (load first) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../../../vendors/jquery/dist/jquery.min.js"></script>

<!-- Bootstrap JS Bundle with Popper (load after jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

<!-- Pass PHP data to JavaScript -->
<script>
    // Global variables for chart data
    window.monthlyLabels = <?= json_encode($monthlyLabels) ?>;
    window.monthlyData = <?= json_encode($monthlyData) ?>;
    window.statusChangeData = <?= json_encode($statusChangeData) ?>;
    window.searchTerm = "<?= isset($_POST['search_term']) ? addslashes($_POST['search_term']) : '' ?>";
</script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../../vendors/Chart.js/dist/Chart.min.js"></script>

<!-- Other vendor scripts -->
<script src="../../../vendors/fastclick/lib/fastclick.js"></script>
<script src="../../../vendors/nprogress/nprogress.js"></script>
<script src="../../../vendors/gauge.js/dist/gauge.min.js"></script>
<script src="../../../vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
<script src="../../../vendors/iCheck/icheck.min.js"></script>
<script src="../../../vendors/skycons/skycons.js"></script>

<!-- Flot -->
<script src="../../../vendors/Flot/jquery.flot.js"></script>
<script src="../../../vendors/Flot/jquery.flot.pie.js"></script>
<script src="../../../vendors/Flot/jquery.flot.time.js"></script>
<script src="../../../vendors/Flot/jquery.flot.stack.js"></script>
<script src="../../../vendors/Flot/jquery.flot.resize.js"></script>
<!-- Flot plugins -->
<script src="../../../vendors/flot.orderbars/js/jquery.flot.orderBars.js"></script>
<script src="../../../vendors/flot-spline/js/jquery.flot.spline.min.js"></script>
<script src="../../../vendors/flot.curvedlines/curvedLines.js"></script>

<!-- DateJS -->
<script src="../../../vendors/DateJS/build/date.js"></script>

<!-- JQVMap -->
<script src="../../../vendors/jqvmap/dist/jquery.vmap.js"></script>
<script src="../../../vendors/jqvmap/dist/maps/jquery.vmap.world.js"></script>
<script src="../../../vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"></script>

<!-- bootstrap-daterangepicker -->
<script src="../../../vendors/moment/min/moment.min.js"></script>
<script src="../../../vendors/bootstrap-daterangepicker/daterangepicker.js"></script>

<!-- Custom Theme Scripts -->
<script src="../../../build/js/custom.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Load our custom script last -->
<script src="../js/script.js"></script>
<script src="../js/device_script.js"></script>
</body>
</html> 