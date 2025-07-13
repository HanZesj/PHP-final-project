<?php
// Define security constant before any includes
define('VOTING_SYSTEM_SECURITY', true);

// Include security functions
require_once 'includes/security.php';

// Initialize secure session and set security headers
Security::setSecurityHeaders();
Security::initSecureSession();

// Database connection
$conn = Security::getSecureConnection();

// Redirect if already logged in
if (Security::isLoggedIn()) {
    header('Location: voting_system.php');
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_username = Security::sanitizeInput($_POST['username'] ?? '');
    $input_password = $_POST['password'] ?? '';
    
    // Rate limiting check
    $clientIP = Security::getClientIP();
    if (!Security::checkRateLimit($clientIP)) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
        Security::logEvent('login_rate_limit_exceeded', null, $input_username);
    } elseif (empty($input_username) || empty($input_password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Get user from database
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$input_username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && Security::verifyPassword($input_password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['last_activity'] = time();
            
            // Reset rate limiting on successful login
            unset($_SESSION['rate_limit']['login_' . $clientIP]);
            
            Security::logEvent('user_login_success', null, $user['user_id']);
            header('Location: voting_system.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
            Security::logEvent('user_login_failed', null, $input_username);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Electronic Voting System</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
        
        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .demo-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .demo-info strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-vote-yea"></i> Voting System</h1>
            <p>Secure & Transparent Elections</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-control" 
                           required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           required
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="register-link">
                <p>Don't have an account? <a href="simple_register.php">Register here</a></p>
            </div>
            
            <div class="demo-info">
                <strong>Getting Started:</strong><br>
                1. Register a new account<br>
                2. First registered user can be made admin in database<br>
                3. Admins can create elections and add candidates
            </div>
        </div>
    </div>
</body>
</html>
