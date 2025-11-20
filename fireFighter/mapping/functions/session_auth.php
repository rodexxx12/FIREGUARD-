<?php

function validateFirefighterSession() {
    session_start();
    if (!isset($_SESSION['firefighter_id'])) {
        header("Location: ../../../index.php");
        exit();
    }
}
?> 