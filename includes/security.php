<?php
/**
 * Security Helper Functions
 * Input validation, sanitization, and security utilities
 */

class Security {
    
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
     * Rate limiting check
     */
    public static function checkRateLimit($action, $limit = 5, $window = 300) {
        $ip = self::getClientIP();
        $key = "rate_limit_{$action}_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'timestamp' => time()];
        }
        
        $data = $_SESSION[$key];
        
        // Reset if window expired
        if (time() - $data['timestamp'] > $window) {
            $_SESSION[$key] = ['count' => 1, 'timestamp' => time()];
            return true;
        }
        
        // Check limit
        if ($data['count'] >= $limit) {
            return false;
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }
}
?>
