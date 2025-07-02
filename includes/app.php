<?php
/**
 * Application Bootstrap
 * Initialize the application with all required components
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Include configuration and classes
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/session.php';
require_once 'classes/Auth.php';
require_once 'classes/Election.php';
require_once 'classes/Candidate.php';
require_once 'classes/Vote.php';

// Start session management
SessionManager::start();

// Global helper functions
function redirect($url, $exit = true) {
    header("Location: $url");
    if ($exit) exit();
}

function showAlert($message, $type = 'info') {
    return "<div class='alert alert-{$type}' role='alert'>{$message}</div>";
}

function formatDate($date, $format = 'Y-m-d H:i') {
    return date($format, strtotime($date));
}

function isElectionActive($election) {
    $now = new DateTime();
    $start = new DateTime($election['start_date']);
    $end = new DateTime($election['end_date']);
    return $now >= $start && $now < $end;
}

function getElectionStatus($election) {
    $now = new DateTime();
    $start = new DateTime($election['start_date']);
    $end = new DateTime($election['end_date']);
    
    if ($now < $start) {
        return 'pending';
    } elseif ($now >= $start && $now < $end) {
        return 'active';
    } else {
        return 'completed';
    }
}

// Initialize application classes
$auth = new Auth();
$election = new Election();
$candidate = new Candidate();
$vote = new Vote();

// CSRF token for forms
$csrfToken = Security::generateCSRF();
?>
