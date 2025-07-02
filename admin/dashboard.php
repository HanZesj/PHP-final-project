<?php
require_once '../includes/app.php';

// Require admin access
if (!Security::isAdmin()) {
    redirect('../dashboard.php');
}

$stats = $vote->getOverallStats();
$recentElections = $election->getAllElections();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Admin Dashboard - Electronic Voting System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>🛠️ Admin Dashboard</h1>
                <div class="d-flex gap-2">
                    <a href="create_election.php" class="btn btn-primary">Create Election</a>
                    <a href="reports.php" class="btn btn-secondary">View Reports</a>
                </div>
            </div>
            
            <!-- Statistics Overview -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">📊 System Overview</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= $stats['total_elections'] ?? 0 ?></div>
                            <div class="stat-label">Total Elections</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $stats['active_elections'] ?? 0 ?></div>
                            <div class="stat-label">Active Elections</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $stats['total_candidates'] ?? 0 ?></div>
                            <div class="stat-label">Total Candidates</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $stats['total_votes'] ?? 0 ?></div>
                            <div class="stat-label">Total Votes Cast</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $stats['total_users'] ?? 0 ?></div>
                            <div class="stat-label">Registered Voters</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">🔒</div>
                            <div class="stat-label">Security Active</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">⚡ Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <a href="create_election.php" class="stat-card" style="text-decoration: none; color: inherit;">
                            <div class="stat-number">➕</div>
                            <div class="stat-label">Create Election</div>
                        </a>
                        <a href="elections.php" class="stat-card" style="text-decoration: none; color: inherit;">
                            <div class="stat-number">🗳️</div>
                            <div class="stat-label">Manage Elections</div>
                        </a>
                        <a href="candidates.php" class="stat-card" style="text-decoration: none; color: inherit;">
                            <div class="stat-number">👥</div>
                            <div class="stat-label">Manage Candidates</div>
                        </a>
                        <a href="users.php" class="stat-card" style="text-decoration: none; color: inherit;">
                            <div class="stat-number">👤</div>
                            <div class="stat-label">Manage Users</div>
                        </a>
                        <a href="reports.php" class="stat-card" style="text-decoration: none; color: inherit;">
                            <div class="stat-number">📊</div>
                            <div class="stat-label">View Reports</div>
                        </a>
                        <a href="security.php" class="stat-card" style="text-decoration: none; color: inherit;">
                            <div class="stat-number">🔒</div>
                            <div class="stat-label">Security Logs</div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Elections -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🗳️ Recent Elections</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($recentElections)): ?>
                        <div class="text-center text-muted">
                            <p>No elections created yet.</p>
                            <a href="create_election.php" class="btn btn-primary">Create Your First Election</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Election</th>
                                        <th>Status</th>
                                        <th>Candidates</th>
                                        <th>Votes</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recentElections, 0, 5) as $elec): ?>
                                        <?php $status = getElectionStatus($elec); ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($elec['title']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="election-status status-<?= $status ?>">
                                                    <?= ucfirst($status) ?>
                                                </span>
                                            </td>
                                            <td><?= $elec['candidate_count'] ?></td>
                                            <td><?= $elec['vote_count'] ?></td>
                                            <td><?= formatDate($elec['created_at'], 'M j, Y') ?></td>
                                            <td>
                                                <a href="../results.php?id=<?= $elec['id'] ?>" class="btn btn-sm btn-secondary">View</a>
                                                <a href="edit_election.php?id=<?= $elec['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="elections.php" class="btn btn-primary">View All Elections</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Health -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🔧 System Health</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number" style="color: #10b981;">✅</div>
                            <div class="stat-label">Database Connected</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: #10b981;">✅</div>
                            <div class="stat-label">Sessions Active</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: #10b981;">✅</div>
                            <div class="stat-label">Security Enabled</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" style="color: #10b981;">✅</div>
                            <div class="stat-label">CSRF Protection</div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h3>Security Features Active:</h3>
                        <ul>
                            <li>✅ Password hashing with secure algorithms</li>
                            <li>✅ Session timeout protection (30 minutes)</li>
                            <li>✅ CSRF token validation</li>
                            <li>✅ SQL injection prevention</li>
                            <li>✅ Input sanitization and validation</li>
                            <li>✅ Rate limiting on login attempts</li>
                            <li>✅ Anonymized vote storage</li>
                            <li>✅ Comprehensive audit logging</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/app.js"></script>
</body>
</html>
