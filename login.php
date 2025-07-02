<?php
require_once 'includes/app.php';

// Redirect if already logged in
if (Security::isLoggedIn()) {
    redirect(Security::isAdmin() ? 'admin/dashboard.php' : 'dashboard.php');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
        
        if ($result['success']) {
            redirect(Security::isAdmin() ? 'admin/dashboard.php' : 'dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}

// Check for session timeout or invalid session
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please log in again.';
} elseif (isset($_GET['invalid'])) {
    $error = 'Invalid session. Please log in again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Login - Electronic Voting System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="main-content">
            <div class="card" style="max-width: 400px; margin: 2rem auto;">
                <div class="card-header text-center">
                    <h1 class="card-title">Electronic Voting System</h1>
                    <p class="text-muted">Secure & Transparent Elections</p>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username or Email</label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-control" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   autocomplete="username">
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   required
                                   autocomplete="current-password">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <p>Don't have an account? <a href="register.php">Register here</a></p>
                    </div>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <strong>Demo Accounts:</strong><br>
                            Admin: admin / admin123<br>
                            Or register a new voter account
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="card" style="max-width: 600px; margin: 2rem auto;">
                <div class="card-header">
                    <h2 class="card-title">System Features</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number">🔒</div>
                            <div class="stat-label">Secure Authentication</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">🗳️</div>
                            <div class="stat-label">Anonymous Voting</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">📊</div>
                            <div class="stat-label">Real-time Results</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">✅</div>
                            <div class="stat-label">Vote Integrity</div>
                        </div>
                    </div>
                    
                    <h3>Security Features:</h3>
                    <ul>
                        <li>Password hashing with secure algorithms</li>
                        <li>Session management with timeout protection</li>
                        <li>CSRF protection on all forms</li>
                        <li>SQL injection prevention with prepared statements</li>
                        <li>Rate limiting on login attempts</li>
                        <li>Anonymized vote storage</li>
                        <li>Comprehensive audit logging</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
