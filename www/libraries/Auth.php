<?php
/**
 * Auth Class
 * Session-basierte Authentifizierung mit MySQL
 * Version 2.0 - MySQL-basierte Benutzerverwaltung
 */

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();

        // Session starten falls noch nicht aktiv
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Benutzer einloggen
     */
    public function login($username, $password) {
        // User aus Datenbank laden (Username ohne @ Präfix)
        $user = $this->db->queryOne(
            "SELECT id, username, password_hash, display_name, email, is_active FROM track_users WHERE username = ?",
            [$username]
        );

        if (!$user) {
            return false;
        }

        // Prüfen ob User aktiv ist
        if (!$user['is_active']) {
            return false;
        }

        // Passwort verifizieren
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Session setzen
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['logged_in'] = true;

        // Last Login aktualisieren
        $this->db->execute(
            "UPDATE track_users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
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

        // Session-Variablen löschen
        $_SESSION = [];

        // Session-Cookie löschen
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Session zerstören
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

        $sql = "INSERT INTO track_audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
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
     * Passwort-Hash generieren
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Neuen Benutzer erstellen
     */
    public function createUser($username, $password, $displayName, $email = '') {
        // @ Präfix hinzufügen
        if (substr($username, 0, 1) !== '@') {
            $username = '@' . $username;
        }

        $passwordHash = self::hashPassword($password);

        $sql = "INSERT INTO track_users (username, password_hash, display_name, email, is_active)
                VALUES (?, ?, ?, ?, 1)";

        return $this->db->insert($sql, [$username, $passwordHash, $displayName, $email]);
    }
}
