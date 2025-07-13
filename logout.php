<?php
// Define security constant before any includes
define('VOTING_SYSTEM_SECURITY', true);

// Include security functions
require_once 'includes/security.php';

// Start session to log the logout event
Security::initSecureSession();

// Log logout event before destroying session
if (isset($_SESSION['user_id'])) {
    Security::logEvent('user_logout', null, $_SESSION['user_id']);
}

// Perform secure logout
Security::secureLogout();

// Set security headers
Security::setSecurityHeaders();

// Redirect to login page
header('Location: simple_login.php?message=logged_out');
exit();
?>
