<?php
/**
 * User Authentication Class
 * Handles login, registration, and user management
 */

class Auth {
    
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * User login
     */
    public function login($username, $password) {
        try {
            // Rate limiting
            if (!Security::checkRateLimit('login', 5, 300)) {
                throw new Exception("Too many login attempts. Please try again later.");
            }
            
            // Input validation
            $username = Security::sanitizeInput($username);
            
            if (empty($username) || empty($password)) {
                throw new Exception("Username and password are required.");
            }
            
            // Get user from database
            $stmt = $this->db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
                Security::logEvent('LOGIN_FAILED', "Failed login attempt for: {$username}");
                throw new Exception("Invalid username or password.");
            }
            
            // Create session
            SessionManager::createSession($user);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'full_name' => $user['full_name']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * User registration
     */
    public function register($data) {
        try {
            // Validate required fields
            $requiredFields = ['username', 'email', 'password', 'confirm_password', 'full_name'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("All fields are required.");
                }
            }
            
            // Sanitize inputs
            $username = Security::sanitizeInput($data['username']);
            $email = Security::sanitizeInput($data['email']);
            $fullName = Security::sanitizeInput($data['full_name']);
            $password = $data['password'];
            $confirmPassword = $data['confirm_password'];
            
            // Validate username
            if (!Security::validateUsername($username)) {
                throw new Exception("Username must be 3-50 characters long and contain only letters, numbers, and underscores.");
            }
            
            // Validate email
            if (!Security::validateEmail($email)) {
                throw new Exception("Please enter a valid email address.");
            }
            
            // Validate password
            if (strlen($password) < 6) {
                throw new Exception("Password must be at least 6 characters long.");
            }
            
            if ($password !== $confirmPassword) {
                throw new Exception("Passwords do not match.");
            }
            
            // Check if username or email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                throw new Exception("Username or email already exists.");
            }
            
            // Hash password
            $passwordHash = Security::hashPassword($password);
            
            // Insert user
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash, $fullName]);
            
            $userId = $this->db->lastInsertId();
            
            Security::logEvent('USER_REGISTERED', "New user registered: {$username}", $userId);
            
            return [
                'success' => true,
                'message' => 'Registration successful! You can now log in.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        SessionManager::destroy();
        return ['success' => true, 'message' => 'Logged out successfully.'];
    }
    
    /**
     * Get current user info
     */
    public function getCurrentUser() {
        if (!Security::isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, role, full_name, has_voted FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error getting current user: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($currentPassword, $newPassword, $confirmPassword) {
        try {
            if (!Security::isLoggedIn()) {
                throw new Exception("You must be logged in to change password.");
            }
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                throw new Exception("All fields are required.");
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception("New passwords do not match.");
            }
            
            if (strlen($newPassword) < 6) {
                throw new Exception("New password must be at least 6 characters long.");
            }
            
            // Verify current password
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !Security::verifyPassword($currentPassword, $user['password_hash'])) {
                throw new Exception("Current password is incorrect.");
            }
            
            // Update password
            $newPasswordHash = Security::hashPassword($newPassword);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
            
            Security::logEvent('PASSWORD_CHANGED', 'User changed password');
            
            return [
                'success' => true,
                'message' => 'Password changed successfully.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>
