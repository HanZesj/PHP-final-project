<?php
require_once 'includes/app.php';

// Logout user
$auth->logout();

// Redirect to login page
redirect('login.php');
?>
