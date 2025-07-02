<?php
/**
 * Session Management
 * Secure session handling with timeout and validation
 */

class SessionManager {
    
    private static $sessionTimeout = 1800; // 30 minutes
    private static $sessionName = 'VOTING_SESS';
    
    /**
     * Initialize secure session
     */
    public static function start() {
        // Configure session security
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_name(self::$sessionName);
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Check session timeout
        self::checkTimeout();
        
        // Validate session
        self::validateSession();
    }
    
    /**
     * Check session timeout
     */
    private static function checkTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > self::$sessionTimeout) {
                self::destroy();
                header('Location: login.php?timeout=1');
                exit();
            }
        }
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Validate session integrity
     */
    private static function validateSession() {
        if (isset($_SESSION['user_id'])) {
            // Validate session in database
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM user_sessions WHERE session_id = ? AND user_id = ? AND expires_at > NOW() AND is_active = 1");
                $stmt->execute([session_id(), $_SESSION['user_id']]);
                
                if (!$stmt->fetch()) {
                    self::destroy();
                    header('Location: login.php?invalid=1');
                    exit();
                }
                
                // Update session activity
                $stmt = $db->prepare("UPDATE user_sessions SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE session_id = ?");
                $stmt->execute([self::$sessionTimeout, session_id()]);
                
            } catch (Exception $e) {
                error_log("Session validation error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Create user session
     */
    public static function createSession($user) {
        try {
            $db = getDB();
            
            // Clean old sessions
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ? OR expires_at < NOW()");
            $stmt->execute([$user['id']]);
            
            // Create new session record
            $sessionId = session_id();
            $expiresAt = date('Y-m-d H:i:s', time() + self::$sessionTimeout);
            
            $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_id, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $user['id'],
                $sessionId,
                $expiresAt,
                Security::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['last_activity'] = time();
            $_SESSION['last_regeneration'] = time();
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            Security::logEvent('LOGIN_SUCCESS', 'User logged in successfully', $user['id']);
            
        } catch (Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            throw new Exception("Failed to create session");
        }
    }
    
    /**
     * Destroy session
     */
    public static function destroy() {
        if (isset($_SESSION['user_id'])) {
            try {
                $db = getDB();
                $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_id = ?");
                $stmt->execute([session_id()]);
                
                Security::logEvent('LOGOUT', 'User logged out', $_SESSION['user_id']);
            } catch (Exception $e) {
                error_log("Session destruction error: " . $e->getMessage());
            }
        }
        
        session_unset();
        session_destroy();
        
        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
    
    /**
     * Check if session is valid
     */
    public static function isValid() {
        return isset($_SESSION['user_id']) && isset($_SESSION['last_activity']);
    }
    
    /**
     * Get session timeout remaining
     */
    public static function getTimeoutRemaining() {
        if (isset($_SESSION['last_activity'])) {
            return max(0, self::$sessionTimeout - (time() - $_SESSION['last_activity']));
        }
        return 0;
    }
}
?>
