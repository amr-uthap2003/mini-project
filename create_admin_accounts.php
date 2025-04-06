<?php
require_once 'db_connection.php';

// Create admin account
$admin = registerUser(
    $conn,
    'admin',
    'admin@brightmind.com',
    '0123456789',
    'admin123',
    1  // role_id for admin
);

// Create counselor account
$counselor = registerUser(
    $conn,
    'counselor',
    'counselor@brightmind.com',
    '0123456788',
    'counselor123',
    3  // role_id for counselor
);

echo "<pre>";
print_r($admin);
print_r($counselor);
echo "</pre>";
?>