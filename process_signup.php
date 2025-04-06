<?php
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    
    $result = registerUser($conn, $username, $email, $phone, $password);
    
    if ($result['success']) {
        header("Location: login.php?registered=true");
        exit;
    } else {
        header("Location: signup.php?error=" . urlencode($result['message']));
        exit;
    }
}