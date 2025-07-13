<?php
// Define security constant before any includes
define('VOTING_SYSTEM_SECURITY', true);

// Include security functions
require_once 'includes/security.php';

// Initialize secure session and set security headers
Security::setSecurityHeaders();
if (!Security::initSecureSession()) {
    header('Location: simple_login.php?error=session_expired');
    exit();
}

// Simple database connection for this basic system
$conn = Security::getSecureConnection();

// Simple authentication check
if (!Security::isLoggedIn()) {
    header('Location: simple_login.php');
    exit();
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    Security::secureLogout();
    header('Location: simple_login.php');
    exit();
}

// Generate CSRF token
$csrfToken = Security::generateCSRFToken();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token for sensitive operations
    if (in_array($_POST['action'], ['cast_vote']) && !Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    
    switch ($_POST['action']) {
        case 'cast_vote':
            echo handleCastVote($conn, $_SESSION['user_id']);
            break;
        case 'get_results':
            echo handleGetResults($conn);
            break;
        case 'extend_session':
            $_SESSION['last_activity'] = time();
            echo json_encode(['success' => true]);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit();
}

// Function to handle vote casting
function handleCastVote($conn, $userId) {
    try {
        $election_id = Security::validateInt($_POST['election_id'], 1);
        $candidate_id = Security::validateInt($_POST['candidate_id'], 1);
        
        if (!$election_id || !$candidate_id) {
            Security::logEvent('invalid_vote_attempt', 'Invalid election or candidate ID', $userId);
            return json_encode(['success' => false, 'message' => 'Invalid election or candidate']);
        }
        
        // Check if election is active and within voting period
        $stmt = $conn->prepare("SELECT * FROM elections WHERE election_id = ? AND status = 'active' AND start_date <= NOW() AND end_date >= NOW()");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
        
        if (!$election) {
            Security::logEvent('vote_attempt_inactive_election', 'Election not active or outside voting period', $userId);
            return json_encode(['success' => false, 'message' => 'Election is not active or voting period has ended']);
        }
        
        // Check if user has already voted in this election
        $stmt = $conn->prepare("SELECT vote_id FROM votes WHERE user_id = ? AND election_id = ?");
        $stmt->execute([$userId, $election_id]);
        if ($stmt->fetch()) {
            Security::logEvent('duplicate_vote_attempt', "Election ID: $election_id", $userId);
            return json_encode(['success' => false, 'message' => 'You have already voted in this election']);
        }
        
        // Verify candidate belongs to election
        $stmt = $conn->prepare("SELECT candidate_id FROM candidates WHERE candidate_id = ? AND election_id = ?");
        $stmt->execute([$candidate_id, $election_id]);
        if (!$stmt->fetch()) {
            Security::logEvent('invalid_candidate_vote', "Candidate ID: $candidate_id, Election ID: $election_id", $userId);
            return json_encode(['success' => false, 'message' => 'Invalid candidate for this election']);
        }
        
        // Cast the vote with transaction
        $conn->beginTransaction();
        
        try {
            // Insert vote
            $stmt = $conn->prepare("INSERT INTO votes (user_id, candidate_id, election_id) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $candidate_id, $election_id]);
            
            // Update candidate vote count
            $stmt = $conn->prepare("UPDATE candidates SET vote_count = vote_count + 1 WHERE candidate_id = ?");
            $stmt->execute([$candidate_id]);
            
            $conn->commit();
            
            Security::logEvent('vote_cast_success', "Election ID: $election_id, Candidate ID: $candidate_id", $userId);
            return json_encode(['success' => true, 'message' => 'Vote cast successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            Security::logEvent('vote_cast_transaction_failed', $e->getMessage(), $userId);
            return json_encode(['success' => false, 'message' => 'Failed to cast vote. Please try again.']);
        }
        
    } catch (Exception $e) {
        Security::logEvent('vote_cast_error', $e->getMessage(), $userId);
        return json_encode(['success' => false, 'message' => 'An error occurred while processing your vote']);
    }
}

// Function to handle getting results
function handleGetResults($conn) {
    try {
        $election_id = Security::validateInt($_POST['election_id'], 1);
        
        if (!$election_id) {
            return json_encode(['success' => false, 'message' => 'Invalid election ID']);
        }
        
        // Check if election exists
        $stmt = $conn->prepare("SELECT title FROM elections WHERE election_id = ?");
        $stmt->execute([$election_id]);
        if (!$stmt->fetch()) {
            return json_encode(['success' => false, 'message' => 'Election not found']);
        }
        
        $stmt = $conn->prepare("
            SELECT candidate_id, full_name, position, vote_count 
            FROM candidates 
            WHERE election_id = ? 
            ORDER BY vote_count DESC, full_name ASC
        ");
        $stmt->execute([$election_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_votes = array_sum(array_column($results, 'vote_count'));
        
        // Calculate percentages
        foreach ($results as &$result) {
            $result['percentage'] = $total_votes > 0 ? round(($result['vote_count'] / $total_votes) * 100, 2) : 0;
        }
        
        return json_encode([
            'success' => true, 
            'results' => $results, 
            'total_votes' => $total_votes
        ]);
        
    } catch (Exception $e) {
        Security::logEvent('results_fetch_error', $e->getMessage(), $_SESSION['user_id'] ?? null);
        return json_encode(['success' => false, 'message' => 'Error loading results']);
    }
}

// Get active elections
$stmt = $conn->prepare("SELECT * FROM elections WHERE status = 'active' ORDER BY start_date");
$stmt->execute();
$activeElections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if user has voted in each election and get candidates
foreach ($activeElections as &$election) {
    // Check if user voted
    $stmt = $conn->prepare("SELECT vote_id FROM votes WHERE user_id = ? AND election_id = ?");
    $stmt->execute([$_SESSION['user_id'], $election['election_id']]);
    $election['has_voted'] = $stmt->fetch() !== false;
    
    // Get candidates
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE election_id = ? ORDER BY full_name");
    $stmt->execute([$election['election_id']]);
    $election['candidates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electronic Voting System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .welcome {
            font-size: 24px;
            color: #333;
        }
        
        .user-details {
            color: #666;
            margin-top: 5px;
        }
        
        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .election-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .election-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .election-title {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .election-meta {
            opacity: 0.9;
        }
        
        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .candidate-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .candidate-card:hover,
        .candidate-card.selected {
            border-color: #667eea;
            background-color: #f8f9ff;
            transform: translateY(-2px);
        }
        
        .candidate-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .candidate-position {
            color: #667eea;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .candidate-bio {
            color: #666;
            font-size: 14px;
        }
        
        .vote-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .vote-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .vote-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
        }
        
        .vote-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .voted-badge {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .results-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .result-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 30px;
            flex: 1;
            margin: 0 15px;
            overflow: hidden;
            position: relative;
        }
        
        .result-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .result-text {
            position: absolute;
            top: 50%;
            left: 10px;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .no-elections {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            color: #666;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .candidates-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="user-info">
                <div>
                    <div class="welcome">
                        <i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!
                    </div>
                    <div class="user-details">
                        <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?> | 
                        <strong>Role:</strong> <?php echo ucfirst($user['role']); ?>
                    </div>
                </div>
                <div class="buttons">
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="admin.php" class="btn btn-primary">
                            <i class="fas fa-cog"></i> Admin Panel
                        </a>
                    <?php endif; ?>
                    <a href="results.php" class="btn btn-success">
                        <i class="fas fa-chart-bar"></i> View Results
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertMessage" style="display: none;"></div>

        <!-- Elections Section -->
        <?php if (empty($activeElections)): ?>
            <div class="no-elections">
                <i class="fas fa-vote-yea" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                <h2>No Active Elections</h2>
                <p>There are currently no active elections available for voting.</p>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Create Election
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($activeElections as $election): ?>
                <div class="election-card">
                    <div class="election-header">
                        <div class="election-title">
                            <?php echo htmlspecialchars($election['title']); ?>
                            <?php if ($election['has_voted']): ?>
                                <span class="voted-badge" style="float: right;">
                                    <i class="fas fa-check"></i> Voted
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="election-meta">
                            <?php echo htmlspecialchars($election['description']); ?><br>
                            <i class="fas fa-calendar"></i> 
                            <?php echo date('F j, Y g:i A', strtotime($election['start_date'])); ?> - 
                            <?php echo date('F j, Y g:i A', strtotime($election['end_date'])); ?>
                        </div>
                    </div>

                    <?php if (!$election['has_voted'] && !empty($election['candidates'])): ?>
                        <div class="candidates-grid">
                            <?php foreach ($election['candidates'] as $candidate): ?>
                                <div class="candidate-card" 
                                     data-candidate-id="<?php echo $candidate['candidate_id']; ?>"
                                     data-election-id="<?php echo $election['election_id']; ?>">
                                    <div class="candidate-name">
                                        <?php echo htmlspecialchars($candidate['full_name']); ?>
                                    </div>
                                    <div class="candidate-position">
                                        <?php echo htmlspecialchars($candidate['position']); ?>
                                    </div>
                                    <?php if ($candidate['bio']): ?>
                                        <div class="candidate-bio">
                                            <?php echo htmlspecialchars($candidate['bio']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="vote-section">
                            <button class="vote-btn" 
                                    id="voteBtn_<?php echo $election['election_id']; ?>" 
                                    data-election-id="<?php echo $election['election_id']; ?>"
                                    disabled>
                                <i class="fas fa-vote-yea"></i> Cast Your Vote
                            </button>
                        </div>
                    <?php elseif ($election['has_voted']): ?>
                        <div class="vote-section">
                            <p style="color: #28a745; font-weight: bold; font-size: 18px; margin-bottom: 15px;">
                                <i class="fas fa-check-circle"></i> 
                                You have successfully voted in this election!
                            </p>
                            <button class="btn btn-success" onclick="showResults(<?php echo $election['election_id']; ?>)">
                                <i class="fas fa-chart-bar"></i> View Results
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="vote-section">
                            <p style="color: #dc3545; font-weight: bold;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                No candidates available for this election.
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Results Section (hidden by default) -->
                    <div id="results_<?php echo $election['election_id']; ?>" class="results-section" style="display: none;">
                        <h3><i class="fas fa-chart-bar"></i> Election Results</h3>
                        <div id="resultsContent_<?php echo $election['election_id']; ?>">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p>Loading results...</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Voting system JavaScript - Global scope
        var selectedCandidates = {};

        // Handle candidate selection
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.addEventListener('click', function() {
                const electionId = this.dataset.electionId;
                const candidateId = this.dataset.candidateId;
                
                // Remove selection from other candidates in the same election
                document.querySelectorAll(`[data-election-id="${electionId}"]`).forEach(c => {
                    c.classList.remove('selected');
                });
                
                // Select this candidate
                this.classList.add('selected');
                selectedCandidates[electionId] = candidateId;
                
                // Enable vote button
                const voteBtn = document.getElementById(`voteBtn_${electionId}`);
                if (voteBtn) {
                    voteBtn.disabled = false;
                }
            });
        });

        // Handle vote casting
        document.querySelectorAll('[id^="voteBtn_"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const electionId = this.dataset.electionId;
                const candidateId = selectedCandidates[electionId];
                
                if (!candidateId) {
                    showAlert('Please select a candidate first', 'error');
                    return;
                }
                
                if (confirm('Are you sure you want to cast your vote? This action cannot be undone.')) {
                    castVote(electionId, candidateId);
                }
            });
        });

        // Cast vote function
        function castVote(electionId, candidateId) {
            const formData = new FormData();
            formData.append('action', 'cast_vote');
            formData.append('election_id', electionId);
            formData.append('candidate_id', candidateId);

            fetch('voting_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred while casting your vote', 'error');
                console.error('Error:', error);
            });
        }

        // Show results function
        function showResults(electionId) {
            const resultsDiv = document.getElementById(`results_${electionId}`);
            const resultsContent = document.getElementById(`resultsContent_${electionId}`);
            
            resultsDiv.style.display = 'block';
            
            const formData = new FormData();
            formData.append('action', 'get_results');
            formData.append('election_id', electionId);

            fetch('voting_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResults(resultsContent, data.results, data.total_votes);
                } else {
                    resultsContent.innerHTML = '<p>Error loading results</p>';
                }
            })
            .catch(error => {
                resultsContent.innerHTML = '<p>Error loading results</p>';
                console.error('Error:', error);
            });
        }

        // Display results function
        function displayResults(container, results, totalVotes) {
            let html = `<p><strong>Total Votes: ${totalVotes}</strong></p>`;
            
            results.forEach(result => {
                html += `
                    <div class="result-item">
                        <strong>${result.full_name}</strong>
                        <div class="result-bar">
                            <div class="result-fill" style="width: ${result.percentage}%"></div>
                            <div class="result-text">${result.percentage}%</div>
                        </div>
                        <span>${result.vote_count} votes</span>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Show alert function
        function showAlert(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}`;
            alertDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electronic Voting System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .voting-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .election-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .election-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        .election-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .election-description {
            opacity: 0.9;
        }
        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .candidate-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .candidate-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .candidate-card.selected {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        .candidate-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .candidate-party {
            color: #667eea;
            font-weight: 500;
            margin-bottom: 10px;
        }
        .candidate-description {
            color: #666;
            font-size: 14px;
        }
        .vote-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .vote-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .vote-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .voted-badge {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .results-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .result-bar {
            background: #e0e0e0;
            border-radius: 20px;
            height: 30px;
            margin: 10px 0;
            overflow: hidden;
            position: relative;
        }
        .result-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            border-radius: 20px;
            transition: width 0.5s ease;
        }
        .result-text {
            position: absolute;
            top: 50%;
            left: 10px;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .user-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="voting-container">
        <!-- User Information -->
        <div class="user-info">
            <h2><i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>Role:</strong> <?php echo ucfirst($user['role']); ?></p>
            <div style="float: right;">
                <a href="results.php" class="vote-button" style="text-decoration: none; margin-right: 10px;">
                    <i class="fas fa-chart-bar"></i> View Results
                </a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin/dashboard.php" class="vote-button" style="text-decoration: none; margin-right: 10px;">
                        <i class="fas fa-cog"></i> Admin Dashboard
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="vote-button" style="text-decoration: none; background: #dc3545;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            <div style="clear: both;"></div>
        </div>

        <!-- Alert Messages -->
        <div id="alertMessage" style="display: none;"></div>

        <!-- Elections Section -->
        <?php if (empty($activeElections)): ?>
            <div class="election-card">
                <div class="election-header">
                    <div class="election-title">No Active Elections</div>
                    <div class="election-description">There are currently no active elections available for voting.</div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($activeElections as $electionData): ?>
                <div class="election-card">
                    <div class="election-header">
                        <div class="election-title">
                            <?php echo htmlspecialchars($electionData['title']); ?>
                            <?php if ($electionData['has_voted']): ?>
                                <span class="voted-badge" style="float: right;">
                                    <i class="fas fa-check"></i> Voted
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="election-description">
                            <?php echo htmlspecialchars($electionData['description']); ?>
                        </div>
                        <div style="margin-top: 10px; font-size: 14px;">
                            <i class="fas fa-calendar"></i> 
                            Ends: <?php echo date('F j, Y g:i A', strtotime($electionData['end_date'])); ?>
                        </div>
                    </div>

                    <?php if (!$electionData['has_voted']): ?>
                        <div class="candidates-grid">
                            <?php foreach ($electionData['candidates'] as $candidateData): ?>
                                <div class="candidate-card" 
                                     data-candidate-id="<?php echo $candidateData['id']; ?>"
                                     data-election-id="<?php echo $electionData['id']; ?>">
                                    <div class="candidate-name">
                                        <?php echo htmlspecialchars($candidateData['name']); ?>
                                    </div>
                                    <div class="candidate-party">
                                        <?php echo htmlspecialchars($candidateData['party']); ?>
                                    </div>
                                    <div class="candidate-description">
                                        <?php echo htmlspecialchars($candidateData['description']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="text-align: center; padding: 20px;">
                            <button class="vote-button" 
                                    id="voteBtn_<?php echo $electionData['id']; ?>" 
                                    data-election-id="<?php echo $electionData['id']; ?>"
                                    disabled>
                                <i class="fas fa-vote-yea"></i> Cast Your Vote
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px;">
                            <p style="color: #28a745; font-weight: bold; font-size: 18px;">
                                <i class="fas fa-check-circle"></i> 
                                You have successfully voted in this election!
                            </p>
                            <button class="vote-button" 
                                    onclick="showResults(<?php echo $electionData['id']; ?>)">
                                <i class="fas fa-chart-bar"></i> View Results
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Results Section (hidden by default) -->
                    <div id="results_<?php echo $electionData['id']; ?>" class="results-section" style="display: none;">
                        <h3><i class="fas fa-chart-bar"></i> Election Results</h3>
                        <div id="resultsContent_<?php echo $electionData['id']; ?>">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p>Loading results...</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Hidden form for CSRF token -->
    <input type="hidden" id="csrfToken" value="<?php echo $csrfToken; ?>">

    <script src="assets/js/app.js"></script>
    <script>
        // Voting system JavaScript - Additional functionality
        // selectedCandidates already declared above

        // Handle candidate selection
        document.querySelectorAll('.candidate-card').forEach(card => {
            card.addEventListener('click', function() {
                const electionId = this.dataset.electionId;
                const candidateId = this.dataset.candidateId;
                
                // Remove selection from other candidates in the same election
                document.querySelectorAll(`[data-election-id="${electionId}"]`).forEach(c => {
                    c.classList.remove('selected');
                });
                
                // Select this candidate
                this.classList.add('selected');
                selectedCandidates[electionId] = candidateId;
                
                // Enable vote button
                const voteBtn = document.getElementById(`voteBtn_${electionId}`);
                voteBtn.disabled = false;
            });
        });

        // Handle vote casting
        document.querySelectorAll('[id^="voteBtn_"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const electionId = this.dataset.electionId;
                const candidateId = selectedCandidates[electionId];
                
                if (!candidateId) {
                    showAlert('Please select a candidate first', 'error');
                    return;
                }
                
                if (confirm('Are you sure you want to cast your vote? This action cannot be undone.')) {
                    castVote(electionId, candidateId);
                }
            });
        });

        // Cast vote function
        function castVote(electionId, candidateId) {
            const formData = new FormData();
            formData.append('action', 'cast_vote');
            formData.append('election_id', electionId);
            formData.append('candidate_id', candidateId);
            formData.append('csrf_token', document.getElementById('csrfToken').value);

            fetch('voting_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    // Reload page to update UI
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred while casting your vote', 'error');
                console.error('Error:', error);
            });
        }

        // Show results function
        function showResults(electionId) {
            const resultsDiv = document.getElementById(`results_${electionId}`);
            const resultsContent = document.getElementById(`resultsContent_${electionId}`);
            
            resultsDiv.style.display = 'block';
            
            const formData = new FormData();
            formData.append('action', 'get_results');
            formData.append('election_id', electionId);

            fetch('voting_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayResults(resultsContent, data.results, data.total_votes);
                } else {
                    resultsContent.innerHTML = '<p>Error loading results</p>';
                }
            })
            .catch(error => {
                resultsContent.innerHTML = '<p>Error loading results</p>';
                console.error('Error:', error);
            });
        }

        // Display results function
        function displayResults(container, results, totalVotes) {
            let html = `<p><strong>Total Votes: ${totalVotes}</strong></p>`;
            
            results.forEach(result => {
                html += `
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span><strong>${result.name}</strong> (${result.party})</span>
                            <span>${result.vote_count} votes (${result.percentage}%)</span>
                        </div>
                        <div class="result-bar">
                            <div class="result-fill" style="width: ${result.percentage}%"></div>
                            <div class="result-text">${result.percentage}%</div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Show alert function
        function showAlert(message, type) {
            const alertDiv = document.getElementById('alertMessage');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}`;
            alertDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        // Session management with timeout warning
        var sessionWarningShown = false;
        var lastActivity = Date.now();
        
        // Check session timeout every minute
        setInterval(() => {
            var timeSinceLastActivity = Date.now() - lastActivity;
            var sessionTimeoutWarning = 25 * 60 * 1000; // 25 minutes (5 min before 30 min timeout)
            var sessionTimeout = 30 * 60 * 1000; // 30 minutes
            
            if (timeSinceLastActivity > sessionTimeout) {
                alert('Your session has expired. You will be redirected to the login page.');
                window.location.href = 'simple_login.php?error=session_expired';
            } else if (timeSinceLastActivity > sessionTimeoutWarning && !sessionWarningShown) {
                sessionWarningShown = true;
                if (confirm('Your session will expire in 5 minutes. Do you want to extend it?')) {
                    extendSession();
                }
            }
        }, 60000); // Check every minute
        
        // Extend session function
        function extendSession() {
            fetch('voting_system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=extend_session'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    lastActivity = Date.now();
                    sessionWarningShown = false;
                    showAlert('Session extended successfully', 'success');
                }
            })
            .catch(error => {
                console.error('Error extending session:', error);
            });
        }
        
        // Reset activity timer on user interaction
        document.addEventListener('click', () => { lastActivity = Date.now(); });
        document.addEventListener('keypress', () => { lastActivity = Date.now(); });
        document.addEventListener('scroll', () => { lastActivity = Date.now(); });

        // Session management - legacy support
        setInterval(() => {
            fetch('voting_system.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=extend_session'
            });
        }, 300000); // Extend session every 5 minutes
    </script>
</body>
</html>
