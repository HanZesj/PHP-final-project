<?php
require_once 'includes/app.php';

// Require login
if (!Security::isLoggedIn()) {
    redirect('login.php');
}

$currentUser = $auth->getCurrentUser();
$activeElections = $election->getActiveElections();
$allElections = $election->getAllElections();

// Check voting status for active elections
foreach ($activeElections as &$elec) {
    $elec['user_has_voted'] = $vote->hasUserVoted($currentUser['id'], $elec['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Dashboard - Electronic Voting System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Welcome, <?= htmlspecialchars($currentUser['full_name']) ?></h1>
                <div class="user-role status-<?= $currentUser['role'] ?>"><?= ucfirst($currentUser['role']) ?></div>
            </div>
            
            <!-- Active Elections -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🗳️ Active Elections</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($activeElections)): ?>
                        <div class="text-center text-muted">
                            <h3>No Active Elections</h3>
                            <p>There are currently no elections in progress. Check back later or view upcoming elections below.</p>
                        </div>
                    <?php else: ?>
                        <div class="election-grid">
                            <?php foreach ($activeElections as $elec): ?>
                                <div class="election-card">
                                    <div class="card-body">
                                        <h3><?= htmlspecialchars($elec['title']) ?></h3>
                                        <p class="text-muted"><?= htmlspecialchars($elec['description']) ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="election-status status-active">Active</div>
                                            <div class="text-muted"><?= $elec['candidate_count'] ?> candidates</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                Ends: <?= formatDate($elec['end_date']) ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($elec['user_has_voted']): ?>
                                            <div class="alert alert-success">
                                                ✅ You have already voted in this election
                                            </div>
                                            <a href="results.php?id=<?= $elec['id'] ?>" class="btn btn-secondary">View Results</a>
                                        <?php else: ?>
                                            <a href="vote.php?id=<?= $elec['id'] ?>" class="btn btn-primary">Vote Now</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- All Elections -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📊 All Elections</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($allElections)): ?>
                        <div class="text-center text-muted">
                            <p>No elections have been created yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Election</th>
                                        <th>Status</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Candidates</th>
                                        <th>Votes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allElections as $elec): ?>
                                        <?php 
                                        $status = getElectionStatus($elec);
                                        $userVoted = $vote->hasUserVoted($currentUser['id'], $elec['id']);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($elec['title']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($elec['description']) ?></small>
                                            </td>
                                            <td>
                                                <span class="election-status status-<?= $status ?>">
                                                    <?= ucfirst($status) ?>
                                                </span>
                                            </td>
                                            <td><?= formatDate($elec['start_date']) ?></td>
                                            <td><?= formatDate($elec['end_date']) ?></td>
                                            <td><?= $elec['candidate_count'] ?></td>
                                            <td><?= $elec['vote_count'] ?></td>
                                            <td>
                                                <?php if ($status === 'active'): ?>
                                                    <?php if ($userVoted): ?>
                                                        <span class="text-success">✅ Voted</span>
                                                        <br><a href="results.php?id=<?= $elec['id'] ?>" class="btn btn-sm btn-secondary">Results</a>
                                                    <?php else: ?>
                                                        <a href="vote.php?id=<?= $elec['id'] ?>" class="btn btn-sm btn-primary">Vote</a>
                                                    <?php endif; ?>
                                                <?php elseif ($status === 'completed'): ?>
                                                    <a href="results.php?id=<?= $elec['id'] ?>" class="btn btn-sm btn-secondary">View Results</a>
                                                <?php else: ?>
                                                    <span class="text-muted">Not started</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- User Information -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">👤 Your Account Information</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= htmlspecialchars($currentUser['username']) ?></div>
                            <div class="stat-label">Username</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= htmlspecialchars($currentUser['email']) ?></div>
                            <div class="stat-label">Email</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= ucfirst($currentUser['role']) ?></div>
                            <div class="stat-label">Role</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= count($activeElections) ?></div>
                            <div class="stat-label">Active Elections</div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="profile.php" class="btn btn-secondary">Edit Profile</a>
                        <a href="change_password.php" class="btn btn-warning">Change Password</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/app.js"></script>
</body>
</html>
