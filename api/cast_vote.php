<?php
require_once '../includes/app.php';

header('Content-Type: application/json');

try {
    // Require login
    if (!Security::isLoggedIn()) {
        throw new Exception('Authentication required');
    }
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate CSRF token
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Security::validateCSRF($csrfToken)) {
        throw new Exception('Invalid security token');
    }
    
    // Validate required fields
    if (empty($input['election_id']) || empty($input['candidate_id'])) {
        throw new Exception('Election ID and Candidate ID are required');
    }
    
    $electionId = (int)$input['election_id'];
    $candidateId = (int)$input['candidate_id'];
    
    // Cast vote
    $result = $vote->castVote($electionId, $candidateId);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
