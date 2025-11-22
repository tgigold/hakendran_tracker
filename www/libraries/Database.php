<?php
/**
 * Database Class
 * PDO-Wrapper mit Prepared Statements für sichere Datenbankabfragen
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $configPath = dirname(__DIR__, 2) . '/config.inc.php';

        if (!file_exists($configPath)) {
            die('Konfigurationsdatei nicht gefunden. Bitte führen Sie install.php aus.');
        }

        require_once $configPath;

        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    /**
     * Singleton-Instanz abrufen
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * PDO-Instanz abrufen
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * SELECT-Abfrage (mehrere Zeilen)
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * SELECT-Abfrage (eine Zeile)
     */
    public function queryOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database queryOne error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * INSERT-Abfrage
     * @return int|false Last Insert ID oder false
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * UPDATE/DELETE-Abfrage
     * @return int|false Anzahl betroffener Zeilen oder false
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Zählt Datensätze
     */
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $result = $this->queryOne($sql, $params);
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * Transaktion starten
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Transaktion committen
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Transaktion zurückrollen
     */
    public function rollback() {
        return $this->pdo->rollback();
    }

    /**
     * Prüft ob Verbindung besteht
     */
    public function isConnected() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
