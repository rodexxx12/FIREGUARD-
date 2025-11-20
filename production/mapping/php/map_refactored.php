<?php
// Include data processing component
include 'components/data_processor.php';
?>

<?php include 'components/header.php'; ?>

<?php include 'components/navigation.php'; ?>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar: Status, Legend, Alerts -->
    <aside class="col-lg-4 col-xl-3 mb-4 mb-lg-0">
      <?php include 'components/status_cards.php'; ?>
      <?php include 'components/legend_panel.php'; ?>
      <?php include 'components/alerts_panel.php'; ?>
    </aside>
    <!-- Main Map -->
    <section class="col-lg-8 col-xl-9">
      <?php include 'components/map_controls.php'; ?>
    </section>
  </div>
</div>

<?php include 'components/building_modal.php'; ?>
<?php include 'components/scripts.php'; ?> 