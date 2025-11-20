<?php
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../../index.php");
    exit();
} 