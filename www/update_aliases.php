<?php
/**
 * Update-Script für Aliase-Tabelle
 * Einmalig ausführen: /update_aliases.php?run=1
 */

// Sicherheitscheck
if (!isset($_GET['run']) || $_GET['run'] !== '1') {
    die('Um dieses Update durchzuführen, rufen Sie die Seite mit ?run=1 auf.');
}

require_once __DIR__ . '/libraries/Database.php';

$db = Database::getInstance();
$pdo = $db->getPdo();

try {
    echo "<h2>Aliase-Update wird durchgeführt...</h2>";

    // Prüfen ob Tabelle bereits existiert
    $check = $pdo->query("SHOW TABLES LIKE 'track_party_aliases'");
    if ($check->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ Tabelle 'track_party_aliases' existiert bereits. Übersprungen.</p>";
    } else {
        // Tabelle erstellen
        $sql = "CREATE TABLE IF NOT EXISTS track_party_aliases (
            id INT PRIMARY KEY AUTO_INCREMENT,
            party_id INT NOT NULL,
            alias_name VARCHAR(300) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (party_id) REFERENCES track_parties(id) ON DELETE CASCADE,
            UNIQUE KEY unique_alias (party_id, alias_name),
            INDEX idx_alias_name (alias_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Tabelle 'track_party_aliases' wurde erfolgreich angelegt.</p>";
    }

    echo "<h3>Update erfolgreich abgeschlossen!</h3>";
    echo "<p><a href='/backend/parties.php'>→ Zur Beteiligte-Verwaltung</a></p>";
    echo "<p style='margin-top: 2rem; color: red;'><strong>WICHTIG:</strong> Löschen Sie diese Datei nach der Ausführung aus Sicherheitsgründen!</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}
