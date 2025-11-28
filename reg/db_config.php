<?php
$dbConfigPath = realpath(__DIR__ . '/../db/db.php');
if ($dbConfigPath === false || !file_exists($dbConfigPath)) {
    throw new RuntimeException('Database configuration file is missing or unreadable.');
}
require_once $dbConfigPath;