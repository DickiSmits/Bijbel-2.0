<?php
/**
 * Authentication Class
 * 
 * Handelt authenticatie en autorisatie af
 */

class Auth {
    
    /**
     * Start sessie indien nog niet gestart
     */
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    /**
     * Controleer of gebruiker is ingelogd als admin
     */
    public static function isAdmin() {
        self::startSession();
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    /**
     * Log in als admin
     */
    public static function login($password) {
        if ($password === ADMIN_PASSWORD) {
            self::startSession();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['login_time'] = time();
            return true;
        }
        return false;
    }
    
    /**
     * Log uit
     */
    public static function logout() {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }
    
    /**
     * Controleer of sessie nog geldig is
     */
    public static function checkSession() {
        self::startSession();
        
        if (!self::isAdmin()) {
            return false;
        }
        
        // Controleer sessie timeout
        if (isset($_SESSION['login_time'])) {
            $elapsed = time() - $_SESSION['login_time'];
            if ($elapsed > SESSION_LIFETIME) {
                self::logout();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Vereist admin toegang - redirect naar login indien niet ingelogd
     */
    public static function requireAdmin() {
        if (!self::isAdmin()) {
            header('Location: ?mode=login');
            exit;
        }
    }
    
    /**
     * Genereer CSRF token
     */
    public static function generateToken() {
        self::startSession();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valideer CSRF token
     */
    public static function validateToken($token) {
        self::startSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
