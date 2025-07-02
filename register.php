<?php
require_once 'includes/app.php';

// Redirect if already logged in
if (Security::isLoggedIn()) {
    redirect(Security::isAdmin() ? 'admin/dashboard.php' : 'dashboard.php');
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = $auth->register($_POST);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Register - Electronic Voting System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="main-content">
            <div class="card" style="max-width: 500px; margin: 2rem auto;">
                <div class="card-header text-center">
                    <h1 class="card-title">Register New Account</h1>
                    <p class="text-muted">Join the Electronic Voting System</p>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                            <br><a href="login.php" class="btn btn-primary mt-2">Login Now</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" data-validate>
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            
                            <div class="form-group">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" 
                                       id="full_name" 
                                       name="full_name" 
                                       class="form-control" 
                                       required 
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                       autocomplete="name">
                                <div class="form-text">Enter your full legal name</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       class="form-control" 
                                       required 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                       autocomplete="username">
                                <div class="form-text">3-50 characters, letters, numbers, and underscores only</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control" 
                                       required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       autocomplete="email">
                                <div class="form-text">We'll use this for important voting notifications</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-control" 
                                       required
                                       autocomplete="new-password">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       required
                                       autocomplete="new-password">
                                <div class="form-text">Re-enter your password</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <input type="checkbox" required> 
                                    I agree to the <a href="#" data-modal-target="termsModal">Terms of Service</a> and <a href="#" data-modal-target="privacyModal">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                                Create Account
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="login.php">Login here</a></p>
                    </div>
                </div>
            </div>
            
            <div class="card" style="max-width: 600px; margin: 2rem auto;">
                <div class="card-header">
                    <h2 class="card-title">Why Register?</h2>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number">🗳️</div>
                            <div class="stat-label">Participate in Elections</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">🔍</div>
                            <div class="stat-label">View Real-time Results</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">📊</div>
                            <div class="stat-label">Access Election History</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">🔔</div>
                            <div class="stat-label">Get Election Notifications</div>
                        </div>
                    </div>
                    
                    <h3>Your Privacy & Security:</h3>
                    <ul>
                        <li><strong>Anonymized Voting:</strong> Your vote is completely anonymous and cannot be traced back to you</li>
                        <li><strong>Secure Storage:</strong> All personal data is encrypted and securely stored</li>
                        <li><strong>No Spam:</strong> We only send essential election-related notifications</li>
                        <li><strong>Data Protection:</strong> Your information is never shared with third parties</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Terms of Service Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <h2>Terms of Service</h2>
            <p><strong>Electronic Voting System Terms of Service</strong></p>
            <ol>
                <li><strong>Eligibility:</strong> You must be eligible to vote in the elections you participate in.</li>
                <li><strong>One Vote Per Election:</strong> You may cast only one vote per election.</li>
                <li><strong>Truthful Information:</strong> You must provide accurate registration information.</li>
                <li><strong>Account Security:</strong> You are responsible for maintaining the security of your account.</li>
                <li><strong>Prohibited Activities:</strong> No vote buying, selling, or coercion is allowed.</li>
                <li><strong>System Integrity:</strong> Any attempt to compromise the system will result in account termination.</li>
                <li><strong>Compliance:</strong> You must comply with all applicable laws and regulations.</li>
            </ol>
        </div>
    </div>
    
    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <h2>Privacy Policy</h2>
            <p><strong>How We Protect Your Privacy</strong></p>
            <h3>Information We Collect:</h3>
            <ul>
                <li>Name, username, and email address (for account creation)</li>
                <li>Voting participation (but not your actual vote choices)</li>
                <li>System usage logs (for security purposes)</li>
            </ul>
            <h3>How We Use Your Information:</h3>
            <ul>
                <li>To enable your participation in elections</li>
                <li>To send important election notifications</li>
                <li>To maintain system security and integrity</li>
                <li>To generate anonymous usage statistics</li>
            </ul>
            <h3>Vote Anonymization:</h3>
            <p>Your actual vote choices are completely anonymized using cryptographic hashing. We cannot determine how you voted, ensuring complete ballot secrecy.</p>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
