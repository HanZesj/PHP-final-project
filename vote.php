<?php
require_once 'includes/app.php';

// Require login
if (!Security::isLoggedIn()) {
    redirect('login.php');
}

$electionId = $_GET['id'] ?? 0;
$currentUser = $auth->getCurrentUser();

// Get election details
$electionData = $election->getElectionById($electionId);
if (!$electionData) {
    redirect('dashboard.php');
}

// Check if election is active
$status = getElectionStatus($electionData);
if ($status !== 'active') {
    redirect('dashboard.php');
}

// Check if user has already voted
if ($vote->hasUserVoted($currentUser['id'], $electionId)) {
    redirect("results.php?id={$electionId}");
}

// Get candidates
$candidates = $candidate->getCandidatesByElection($electionId);

if (empty($candidates)) {
    $error = "No candidates available for this election.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Vote - <?= htmlspecialchars($electionData['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content voting-area">
            <div class="card">
                <div class="card-header text-center">
                    <h1 class="card-title"><?= htmlspecialchars($electionData['title']) ?></h1>
                    <p class="text-muted"><?= htmlspecialchars($electionData['description']) ?></p>
                    <div class="election-status status-active">Election Active</div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Important:</strong> You can only vote once in this election. Your vote is anonymous and cannot be changed once submitted.
                    </div>
                    
                    <div class="text-center mb-4">
                        <h2>Select Your Candidate</h2>
                        <p>Click on a candidate card to select them, then click the vote button.</p>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php else: ?>
                        <form id="voteForm" data-no-refresh>
                            <input type="hidden" id="electionId" value="<?= $electionId ?>">
                            
                            <div class="candidate-grid">
                                <?php foreach ($candidates as $cand): ?>
                                    <div class="candidate-card" data-candidate-id="<?= $cand['id'] ?>">
                                        <div class="candidate-photo">
                                            <?php if ($cand['photo_url']): ?>
                                                <img src="<?= htmlspecialchars($cand['photo_url']) ?>" alt="<?= htmlspecialchars($cand['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                            <?php else: ?>
                                                👤
                                            <?php endif; ?>
                                        </div>
                                        <div class="candidate-name"><?= htmlspecialchars($cand['name']) ?></div>
                                        <?php if ($cand['party']): ?>
                                            <div class="candidate-party"><?= htmlspecialchars($cand['party']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($cand['description']): ?>
                                            <div class="text-muted" style="font-size: 0.875rem;">
                                                <?= htmlspecialchars(substr($cand['description'], 0, 100)) ?>
                                                <?= strlen($cand['description']) > 100 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" id="voteButton" class="btn btn-success btn-lg" disabled>
                                    Select a candidate to vote
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Election Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📋 Election Information</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= count($candidates) ?></div>
                            <div class="stat-label">Candidates</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= formatDate($electionData['start_date'], 'M j, Y') ?></div>
                            <div class="stat-label">Started</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= formatDate($electionData['end_date'], 'M j, Y') ?></div>
                            <div class="stat-label">Ends</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="timeRemaining">Calculating...</div>
                            <div class="stat-label">Time Remaining</div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h3>How Voting Works:</h3>
                        <ol>
                            <li><strong>Select:</strong> Click on your preferred candidate</li>
                            <li><strong>Review:</strong> Make sure you've selected the right candidate</li>
                            <li><strong>Vote:</strong> Click the vote button to cast your ballot</li>
                            <li><strong>Confirm:</strong> Your vote will be securely recorded and anonymized</li>
                        </ol>
                        
                        <h3>Security & Privacy:</h3>
                        <ul>
                            <li>🔒 Your vote is completely anonymous</li>
                            <li>🛡️ All data is encrypted and secure</li>
                            <li>✅ Vote integrity is maintained with cryptographic hashing</li>
                            <li>🔍 Results are transparent and verifiable</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Vote Confirmation Modal -->
    <div id="voteModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Your Vote</h2>
            <p>Are you sure you want to vote for <strong id="selectedCandidateName"></strong>?</p>
            <p class="text-warning"><strong>Warning:</strong> This action cannot be undone.</p>
            <div class="d-flex gap-2 mt-3">
                <button onclick="confirmVote()" class="btn btn-success">Yes, Cast My Vote</button>
                <button onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Countdown timer for election end
        function updateCountdown() {
            const endDate = new Date('<?= $electionData['end_date'] ?>').getTime();
            const now = new Date().getTime();
            const distance = endDate - now;
            
            if (distance < 0) {
                document.getElementById('timeRemaining').textContent = 'Election Ended';
                // Redirect to results
                setTimeout(() => {
                    window.location.href = 'results.php?id=<?= $electionId ?>';
                }, 5000);
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            let timeString = '';
            if (days > 0) timeString += days + 'd ';
            if (hours > 0) timeString += hours + 'h ';
            timeString += minutes + 'm ' + seconds + 's';
            
            document.getElementById('timeRemaining').textContent = timeString;
        }
        
        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
        
        // Enhanced vote confirmation
        let originalSubmitVote = VotingApp.submitVote;
        VotingApp.submitVote = function() {
            if (!this.selectedCandidate) {
                this.showAlert('Please select a candidate', 'warning');
                return;
            }
            
            const selectedCard = document.querySelector(`[data-candidate-id="${this.selectedCandidate}"]`);
            const candidateName = selectedCard.querySelector('.candidate-name').textContent;
            
            document.getElementById('selectedCandidateName').textContent = candidateName;
            document.getElementById('voteModal').style.display = 'block';
        };
        
        function confirmVote() {
            document.getElementById('voteModal').style.display = 'none';
            
            // Call original submit vote function
            const voteButton = document.getElementById('voteButton');
            const originalText = voteButton.textContent;
            voteButton.disabled = true;
            voteButton.innerHTML = '<span class="loading"></span> Casting Vote...';
            
            fetch('api/cast_vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    election_id: document.getElementById('electionId').value,
                    candidate_id: VotingApp.selectedCandidate
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    VotingApp.showAlert(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'results.php?id=' + document.getElementById('electionId').value;
                    }, 2000);
                } else {
                    VotingApp.showAlert(data.message, 'danger');
                    voteButton.disabled = false;
                    voteButton.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Vote submission failed:', error);
                VotingApp.showAlert('Failed to submit vote. Please try again.', 'danger');
                voteButton.disabled = false;
                voteButton.textContent = originalText;
            });
        }
        
        function closeModal() {
            document.getElementById('voteModal').style.display = 'none';
        }
        
        // Prevent back button after voting starts
        history.pushState(null, null, location.href);
        window.addEventListener('popstate', function(event) {
            if (VotingApp.selectedCandidate) {
                if (!confirm('Are you sure you want to leave? Your selection will be lost.')) {
                    history.pushState(null, null, location.href);
                }
            }
        });
    </script>
</body>
</html>
