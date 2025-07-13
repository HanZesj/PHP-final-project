<?php
// Define security constant
define('VOTING_SYSTEM_SECURITY', true);

// Include security functions
require_once 'includes/security.php';

// Initialize secure session
Security::setSecurityHeaders();
Security::initSecureSession();

// Check if user is already logged in
if (Security::isLoggedIn()) {
    header('Location: voting_system.php');
    exit();
}

// Redirect to login page
header('Location: simple_login.php');
exit();
?>
