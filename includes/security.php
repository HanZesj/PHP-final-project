<?php
/**
 * Enhanced Security Functions for Electronic Voting System
 * Implements comprehensive security measures as per guidelines
 */

// Prevent direct access
if (!defined('VOTING_SYSTEM_SECURITY')) {
    define('VOTING_SYSTEM_SECURITY', true);
}

class Security {
    
    /**
     * Generate CSRF Token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF Token
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input to prevent XSS attacks
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Validate username (alphanumeric and underscore only)
     */
    public static function validateUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        if (strlen($password) < 8) {
            return "Password must be at least 8 characters long.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return "Password must contain at least one uppercase letter.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            return "Password must contain at least one lowercase letter.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            return "Password must contain at least one number.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return "Password must contain at least one special character.";
        }
        return true;
    }
    
    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate anonymized voter hash
     */
    public static function generateVoterHash($userId, $electionId) {
        return hash('sha256', $userId . $electionId . 'voting_salt_2025');
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRF() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $ip_fields = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log security events
     */
    public static function logEvent($action, $details = null, $userId = null) {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId ?: ($_SESSION['user_id'] ?? null),
                $action,
                $details,
                self::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
    
    /**
     * Secure session initialization
     */
    public static function initSecureSession() {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Set session timeout (30 minutes)
        ini_set('session.gc_maxlifetime', 1800);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            self::secureLogout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Secure logout
     */
    public static function secureLogout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Rate limiting for login attempts
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) { // 15 minutes
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        $key = 'login_' . $identifier;
        
        // Clean old entries
        foreach ($_SESSION['rate_limit'] as $k => $data) {
            if ($now - $data['first_attempt'] > $timeWindow) {
                unset($_SESSION['rate_limit'][$k]);
            }
        }
        
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [
                'attempts' => 1,
                'first_attempt' => $now
            ];
            return true;
        }
        
        $data = $_SESSION['rate_limit'][$key];
        
        if ($now - $data['first_attempt'] > $timeWindow) {
            // Reset counter
            $_SESSION['rate_limit'][$key] = [
                'attempts' => 1,
                'first_attempt' => $now
            ];
            return true;
        }
        
        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }
        
        $_SESSION['rate_limit'][$key]['attempts']++;
        return true;
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; font-src \'self\' https://cdnjs.cloudflare.com; script-src \'self\' \'unsafe-inline\';');
    }
    
    /**
     * Validate integer input
     */
    public static function validateInt($value, $min = null, $max = null) {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) return false;
        if ($min !== null && $value < $min) return false;
        if ($max !== null && $value > $max) return false;
        return $value;
    }
    
    /**
     * Secure database connection
     */
    public static function getSecureConnection() {
        static $conn = null;
        
        if ($conn === null) {
            $host = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'voting_system';
            
            try {
                $conn = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_FOUND_ROWS => true
                ]);
            } catch(PDOException $e) {
                self::logEvent('database_connection_failed', $e->getMessage());
                die("Connection failed. Please try again later.");
            }
        }
        
        return $conn;
    }
}
?>
