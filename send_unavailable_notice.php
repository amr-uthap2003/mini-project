<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['booking_id'])) {
    header("Location: class_management.php?booking_id=" . $_GET['booking_id'] . "&cancel=1");
    exit();
}

header("Location: class_management.php");
exit();
?>