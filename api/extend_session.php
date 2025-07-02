<?php
require_once '../includes/app.php';

header('Content-Type: application/json');

try {
    if (!Security::isLoggedIn()) {
        throw new Exception('Authentication required');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Update session activity
    $_SESSION['last_activity'] = time();
    
    // Update database session
    $db = getDB();
    $stmt = $db->prepare("UPDATE user_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL 1800 SECOND) WHERE session_id = ?");
    $stmt->execute([session_id()]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Session extended successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
