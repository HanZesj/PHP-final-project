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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_username = Security::sanitizeInput($_POST['username'] ?? '');
    $input_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = Security::sanitizeInput($_POST['full_name'] ?? '');
    $role = Security::sanitizeInput($_POST['role'] ?? 'voter');
    
    // Enhanced validation
    if (empty($input_username) || empty($input_password) || empty($confirm_password) || empty($full_name)) {
        $error = 'All fields are required.';
    } elseif (!Security::validateUsername($input_username)) {
        $error = 'Username must be 3-50 characters long and contain only letters, numbers, and underscores.';
    } elseif (strlen($input_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check password strength
        $passwordValidation = Security::validatePasswordStrength($input_password);
        if ($passwordValidation !== true) {
            $error = $passwordValidation;
        } elseif ($input_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (!in_array($role, ['voter', 'admin'])) {
            $error = 'Invalid role selected.';
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$input_username]);
            
            if ($stmt->fetch()) {
                $error = 'Username already exists. Please choose a different username.';
            } else {
                // Hash password and insert user
                $hashed_password = Security::hashPassword($input_password);
                
                try {
                    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$input_username, $hashed_password, $full_name, $role]);
                    
                    $success = 'Account created successfully! You can now login.';
                    Security::logEvent('user_registration_success', null, $input_username);
                    
                    // Clear form data
                    $_POST = [];
                    
                } catch(PDOException $e) {
                    $error = 'Registration failed. Please try again.';
                    Security::logEvent('user_registration_failed', $e->getMessage(), $input_username);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Electronic Voting System</title>
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
        
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
        
        .register-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .register-body {
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
        
        .form-control, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
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
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .info-box strong {
            color: #333;
        }
        
        .role-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-user-plus"></i> Create Account</h1>
            <p>Join the Electronic Voting System</p>
        </div>
        
        <div class="register-body">
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
                    <label for="full_name" class="form-label">
                        <i class="fas fa-user"></i> Full Name
                    </label>
                    <input type="text" 
                           id="full_name" 
                           name="full_name" 
                           class="form-control" 
                           required 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-at"></i> Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-control" 
                           required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="Choose a username (min 3 characters)">
                </div>
                
                <div class="form-group">
                    <label for="role" class="form-label">
                        <i class="fas fa-user-tag"></i> Role
                    </label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="voter" <?php echo ($_POST['role'] ?? 'voter') === 'voter' ? 'selected' : ''; ?>>Voter</option>
                        <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                    <div class="role-info">
                        <strong>Voter:</strong> Can participate in elections<br>
                        <strong>Admin:</strong> Can create elections and manage candidates
                    </div>
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
                           placeholder="Enter password (min 6 characters)">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password
                    </label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control" 
                           required
                           placeholder="Confirm your password">
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            
            <div class="login-link">
                <p>Already have an account? <a href="simple_login.php">Login here</a></p>
            </div>
            
            <div class="info-box">
                <strong>Security Features:</strong><br>
                • Passwords are securely hashed<br>
                • One vote per user per election<br>
                • Anonymous voting system<br>
                • Secure session management
            </div>
        </div>
    </div>
</body>
</html>
