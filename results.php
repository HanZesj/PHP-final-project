<?php
require_once 'includes/app.php';

// Require login
if (!Security::isLoggedIn()) {
    redirect('login.php');
}

$electionId = $_GET['id'] ?? 0;

// Get election results
$results = $election->getElectionResults($electionId);

if (!$results['success']) {
    redirect('dashboard.php');
}

$electionData = $results['election'];
$candidates = $results['candidates'];
$totalVotes = $results['total_votes'];
$status = getElectionStatus($electionData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Results - <?= htmlspecialchars($electionData['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content election-results">
            <input type="hidden" id="electionId" value="<?= $electionId ?>">
            
            <div class="card">
                <div class="card-header text-center">
                    <h1 class="card-title"><?= htmlspecialchars($electionData['title']) ?></h1>
                    <p class="text-muted"><?= htmlspecialchars($electionData['description']) ?></p>
                    <div class="election-status status-<?= $status ?>">
                        <?= ucfirst($status) ?>
                        <?php if ($status === 'active'): ?>
                            - Live Results
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="stats-grid mb-4">
                        <div class="stat-card">
                            <div class="stat-number total-votes"><?= $totalVotes ?></div>
                            <div class="stat-label">Total Votes Cast</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= count($candidates) ?></div>
                            <div class="stat-label">Candidates</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= formatDate($electionData['start_date'], 'M j') ?></div>
                            <div class="stat-label">Started</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= formatDate($electionData['end_date'], 'M j') ?></div>
                            <div class="stat-label">Ends</div>
                        </div>
                    </div>
                    
                    <?php if ($totalVotes === 0): ?>
                        <div class="text-center text-muted">
                            <h3>No votes cast yet</h3>
                            <p>Results will appear here once voting begins.</p>
                        </div>
                    <?php else: ?>
                        <div class="results-container">
                            <?php 
                            $position = 1;
                            foreach ($candidates as $cand): 
                                $percentage = $totalVotes > 0 ? round(($cand['vote_count'] / $totalVotes) * 100, 2) : 0;
                            ?>
                                <div class="result-item" data-candidate-id="<?= $cand['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="position-badge">
                                                <?php if ($position === 1 && $cand['vote_count'] > 0): ?>
                                                    🥇
                                                <?php elseif ($position === 2 && $cand['vote_count'] > 0): ?>
                                                    🥈
                                                <?php elseif ($position === 3 && $cand['vote_count'] > 0): ?>
                                                    🥉
                                                <?php else: ?>
                                                    #<?= $position ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h3 style="margin: 0;"><?= htmlspecialchars($cand['name']) ?></h3>
                                                <?php if ($cand['party']): ?>
                                                    <div class="text-muted"><?= htmlspecialchars($cand['party']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="vote-count" style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                                                <?= $cand['vote_count'] ?>
                                            </div>
                                            <div class="percentage text-muted"><?= $percentage ?>%</div>
                                        </div>
                                    </div>
                                    
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?= $percentage ?>%;"></div>
                                    </div>
                                    
                                    <?php if ($cand['description']): ?>
                                        <div class="mt-2 text-muted" style="font-size: 0.875rem;">
                                            <?= htmlspecialchars($cand['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <hr>
                            <?php 
                            $position++;
                            endforeach; 
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($status === 'active'): ?>
                        <div class="alert alert-info text-center">
                            <strong>🔄 Live Results:</strong> These results update automatically every 30 seconds while the election is active.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Election Details -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📊 Election Details</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h3>Timeline</h3>
                            <ul class="list-unstyled">
                                <li><strong>Start:</strong> <?= formatDate($electionData['start_date']) ?></li>
                                <li><strong>End:</strong> <?= formatDate($electionData['end_date']) ?></li>
                                <li><strong>Duration:</strong> 
                                    <?php
                                    $start = new DateTime($electionData['start_date']);
                                    $end = new DateTime($electionData['end_date']);
                                    $interval = $start->diff($end);
                                    echo $interval->format('%d days, %h hours');
                                    ?>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h3>Statistics</h3>
                            <ul class="list-unstyled">
                                <li><strong>Total Candidates:</strong> <?= count($candidates) ?></li>
                                <li><strong>Total Votes:</strong> <?= $totalVotes ?></li>
                                <li><strong>Created by:</strong> <?= htmlspecialchars($electionData['created_by_name']) ?></li>
                                <li><strong>Created:</strong> <?= formatDate($electionData['created_at']) ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if ($status === 'completed'): ?>
                        <div class="mt-4">
                            <h3>🏆 Final Results Summary</h3>
                            <?php if ($totalVotes > 0): ?>
                                <?php $winner = $candidates[0]; ?>
                                <div class="alert alert-success">
                                    <strong>Winner:</strong> <?= htmlspecialchars($winner['name']) ?>
                                    <?php if ($winner['party']): ?>
                                        (<?= htmlspecialchars($winner['party']) ?>)
                                    <?php endif; ?>
                                    with <?= $winner['vote_count'] ?> votes 
                                    (<?= round(($winner['vote_count'] / $totalVotes) * 100, 2) ?>%)
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <strong>No Winner:</strong> No votes were cast in this election.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="text-center mt-4">
                <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                <?php if (Security::isAdmin()): ?>
                    <a href="admin/elections.php" class="btn btn-secondary">Manage Elections</a>
                    <button onclick="generateReport()" class="btn btn-info">Generate Report</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Auto-refresh for active elections
        <?php if ($status === 'active'): ?>
        setInterval(() => {
            VotingApp.updateResults();
        }, 30000);
        <?php endif; ?>
        
        // Generate report function (admin only)
        function generateReport() {
            window.open(`admin/generate_report.php?election_id=<?= $electionId ?>`, '_blank');
        }
        
        // Chart visualization (if chart library is available)
        document.addEventListener('DOMContentLoaded', function() {
            // Simple text-based chart for now
            // Could be enhanced with Chart.js or similar library
            
            <?php if ($status === 'active'): ?>
            // Show live indicator
            const indicator = document.createElement('div');
            indicator.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #10b981;
                color: white;
                padding: 10px 15px;
                border-radius: 25px;
                font-size: 14px;
                z-index: 1000;
                animation: pulse 2s infinite;
            `;
            indicator.innerHTML = '🔴 LIVE';
            document.body.appendChild(indicator);
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.5; }
                    100% { opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            <?php endif; ?>
        });
        
        // Time remaining countdown for active elections
        <?php if ($status === 'active'): ?>
        function updateTimeRemaining() {
            const endDate = new Date('<?= $electionData['end_date'] ?>').getTime();
            const now = new Date().getTime();
            const distance = endDate - now;
            
            if (distance < 0) {
                location.reload(); // Reload to show completed status
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            
            let timeString = '';
            if (days > 0) timeString += days + ' days, ';
            if (hours > 0) timeString += hours + ' hours, ';
            timeString += minutes + ' minutes remaining';
            
            // Add to status if not exists
            let timeElement = document.getElementById('timeRemaining');
            if (!timeElement) {
                timeElement = document.createElement('div');
                timeElement.id = 'timeRemaining';
                timeElement.className = 'text-center text-muted mt-2';
                document.querySelector('.election-status').parentNode.appendChild(timeElement);
            }
            timeElement.textContent = timeString;
        }
        
        updateTimeRemaining();
        setInterval(updateTimeRemaining, 60000); // Update every minute
        <?php endif; ?>
    </script>
</body>
</html>
