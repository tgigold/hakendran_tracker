<?php
/**
 * Auth Class
 * Session-basierte Authentifizierung mit user.auth.php
 */

class Auth {
    private $db;
    private $users;

    public function __construct() {
        $this->db = Database::getInstance();

        // user.auth.php laden
        $userAuthPath = dirname(__DIR__, 2) . '/user.auth.php';
        if (!file_exists($userAuthPath)) {
            die('Benutzer-Authentifizierungsdatei nicht gefunden. Bitte führen Sie install.php aus.');
        }

        $this->users = require $userAuthPath;

        // Session starten falls noch nicht aktiv
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Benutzer einloggen
     */
    public function login($username, $password) {
        // Prüfen ob User existiert
        if (!isset($this->users[$username])) {
            return false;
        }

        $user = $this->users[$username];

        // Prüfen ob User aktiv ist
        if (!$user['is_active']) {
            return false;
        }

        // Passwort verifizieren
        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // User-Daten aus Datenbank laden
        $dbUser = $this->db->queryOne(
            "SELECT id, username, display_name, email FROM users WHERE username = ? AND is_active = 1",
            [$username]
        );

        if (!$dbUser) {
            return false;
        }

        // Session setzen
        $_SESSION['user_id'] = $dbUser['id'];
        $_SESSION['username'] = $dbUser['username'];
        $_SESSION['display_name'] = $dbUser['display_name'];
        $_SESSION['logged_in'] = true;

        // Last Login aktualisieren
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$dbUser['id']]
        );

        // Audit Log
        $this->logAction('login', null, null, 'User logged in');

        return true;
    }

    /**
     * Benutzer ausloggen
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logAction('logout', null, null, 'User logged out');
        }

        session_destroy();
        return true;
    }

    /**
     * Prüfen ob User eingeloggt ist
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Login erzwingen (Redirect wenn nicht eingeloggt)
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /backend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    /**
     * Aktuelle User-ID abrufen
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Aktuellen Username abrufen
     */
    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Anzeigename abrufen
     */
    public function getDisplayName() {
        return $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Unbekannt';
    }

    /**
     * Alle User-Daten abrufen
     */
    public function getUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'id' => $this->getUserId(),
            'username' => $this->getUsername(),
            'display_name' => $this->getDisplayName()
        ];
    }

    /**
     * Audit-Log-Eintrag erstellen
     */
    public function logAction($action, $entityType = null, $entityId = null, $details = null) {
        $userId = $this->getUserId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql = "INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        return $this->db->insert($sql, [
            $userId,
            $action,
            $entityType,
            $entityId,
            $details,
            $ipAddress,
            $userAgent
        ]);
    }

    /**
     * CSRF-Token generieren
     */
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * CSRF-Token validieren
     */
    public function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Passwort-Hash für user.auth.php generieren
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
