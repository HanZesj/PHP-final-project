<?php
require_once '../includes/app.php';

header('Content-Type: application/json');

try {
    if (!Security::isLoggedIn()) {
        throw new Exception('Authentication required');
    }
    
    $electionId = $_GET['id'] ?? 0;
    
    // Get election results
    $results = $election->getElectionResults($electionId);
    
    if (!$results['success']) {
        throw new Exception($results['message']);
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
