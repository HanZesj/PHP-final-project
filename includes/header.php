<?php
$currentUser = $auth->getCurrentUser();
?>
<header class="header">
    <div class="container">
        <div class="logo">
            <a href="<?= Security::isAdmin() ? 'admin/dashboard.php' : 'dashboard.php' ?>" style="color: white; text-decoration: none;">
                🗳️ Electronic Voting System
            </a>
        </div>
        
        <?php if (Security::isLoggedIn()): ?>
            <nav class="nav">
                <ul>
                    <?php if (Security::isAdmin()): ?>
                        <li><a href="admin/dashboard.php">Admin Dashboard</a></li>
                        <li><a href="admin/elections.php">Manage Elections</a></li>
                        <li><a href="admin/candidates.php">Manage Candidates</a></li>
                        <li><a href="admin/users.php">Manage Users</a></li>
                        <li><a href="admin/reports.php">Reports</a></li>
                    <?php else: ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="elections.php">Elections</a></li>
                        <li><a href="results.php">Results</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($currentUser['full_name']) ?></span>
                <span class="user-role"><?= ucfirst($currentUser['role']) ?></span>
                <a href="logout.php" class="btn btn-sm btn-secondary">Logout</a>
            </div>
        <?php else: ?>
            <nav class="nav">
                <ul>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</header>
