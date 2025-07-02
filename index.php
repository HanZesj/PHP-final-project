<?php
require_once 'includes/app.php';

// Redirect to appropriate dashboard based on user role
if (Security::isLoggedIn()) {
    if (Security::isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
} else {
    redirect('login.php');
}
?>
