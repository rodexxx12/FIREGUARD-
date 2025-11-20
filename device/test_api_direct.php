<?php
// Test the API directly
echo "Testing SMS API directly...\n";

// Set the action
$_GET['action'] = 'check_database';

// Include the API
include 'sms_test_api.php';
?>


