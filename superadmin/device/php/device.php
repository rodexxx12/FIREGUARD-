<?php
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../../index.php");
    exit();
}

// Handle AJAX requests first, before any HTML output
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../functions/ajax_handler.php';
    $handler = new AjaxHandler();
    $response = $handler->handleRequest();
    $handler->sendResponse($response);
    exit; // Ensure no further output
}

// Include functions for regular page load
require_once '../functions/functions.php';
?>

<?php include 'components/header.php'; ?>
<?php include 'components/navigation.php'; ?>

<main class="main-content">
    <div class="row">
        <?php include 'components/statistics_cards.php'; ?>
    </div>

    <?php include 'components/device_table.php'; ?>

</main>

<?php include 'components/add_device_modal.php'; ?>
<?php include 'components/scripts.php'; ?>