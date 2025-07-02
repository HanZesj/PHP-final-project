<?php
require_once '../includes/app.php';

header('Content-Type: application/json');

try {
    // Check session validity
    $timeoutRemaining = SessionManager::getTimeoutRemaining();
    $isValid = SessionManager::isValid();
    
    $response = [
        'valid' => $isValid,
        'timeout_remaining' => $timeoutRemaining,
        'warning' => $timeoutRemaining > 0 && $timeoutRemaining < 300 // 5 minutes
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'valid' => false,
        'error' => $e->getMessage()
    ]);
}
?>
