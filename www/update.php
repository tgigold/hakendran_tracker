<?php
/**
 * Hakendran Gerichtstracker - Update Script
 * Migriert von Version 1.0 zu Version 2.0
 *
 * Änderungen:
 * - Tabellen-Präfix: track_
 * - MySQL-basierte Benutzerverwaltung
 * - Neue Farben
 */

// Sicherheitscheck
$updateKey = isset($_GET['key']) ? $_GET['key'] : '';
$expectedKey = ''; // Bitte eigenen Key setzen oder aus Config laden

if (empty($expectedKey)) {
    die('Bitte setzen Sie einen Update-Key in diesem Script (Zeile 13).');
}

if ($updateKey !== $expectedKey) {
    die('Ungültiger Update-Key. Bitte setzen Sie ?key=IHR_KEY in der URL.');
}

// Config laden
$configPath = dirname(__DIR__) . '/config.inc.php';
if (!file_exists($configPath)) {
    die('config.inc.php nicht gefunden.');
}

require_once $configPath;

// Datenbankverbindung
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
}

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$errors = [];
$success = '';
$migrationLog = [];

/**
 * Prüft ob alte Tabellen existieren
 */
function hasOldTables($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Prüft ob neue Tabellen existieren
 */
function hasNewTables($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'track_users'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// STEP 2: Migration durchführen
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Prüfen ob Migration nötig
        if (!hasOldTables($pdo)) {
            throw new Exception('Keine alten Tabellen gefunden. Entweder ist die Migration bereits erfolgt oder dies ist eine Neuinstallation.');
        }

        if (hasNewTables($pdo)) {
            throw new Exception('Neue Tabellen existieren bereits. Migration wurde möglicherweise bereits durchgeführt.');
        }

        // 1. Neue Tabellen aus schema.sql erstellen
        $schemaFile = dirname(__DIR__) . '/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('schema.sql nicht gefunden.');
        }

        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
        $migrationLog[] = 'Neue Tabellen mit track_ Präfix erstellt';

        // 2. Benutzerdaten migrieren und Passwort-Hashes aus user.auth.php laden
        $userAuthPath = dirname(__DIR__) . '/user.auth.php';
        if (file_exists($userAuthPath)) {
            $userAuthData = require $userAuthPath;

            foreach ($userAuthData as $username => $userData) {
                $stmt = $pdo->prepare("
                    INSERT INTO track_users (username, password_hash, display_name, email, is_active)
                    SELECT username, ?, display_name, email, is_active
                    FROM users
                    WHERE username = ?
                ");
                $stmt->execute([$userData['password'], $username]);
            }
            $migrationLog[] = 'Benutzerdaten migriert (' . count($userAuthData) . ' User)';
        } else {
            // Fallback: User ohne Passwort-Hash migrieren (muss später gesetzt werden)
            $pdo->exec("
                INSERT INTO track_users (username, password_hash, display_name, email, is_active, created_at, last_login)
                SELECT username, '', display_name, email, is_active, created_at, last_login
                FROM users
            ");
            $migrationLog[] = 'WARNUNG: User ohne Passwort-Hash migriert. Bitte Passwörter neu setzen!';
        }

        // 3. Parties migrieren
        $pdo->exec("
            INSERT INTO track_parties (id, name, type, country_code, is_big_tech, logo_url, website, created_at)
            SELECT id, name, type, country_code, is_big_tech, logo_url, website, created_at
            FROM parties
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_parties")->fetchColumn();
        $migrationLog[] = "Beteiligte migriert ({$count} Einträge)";

        // 4. Cases migrieren
        $pdo->exec("
            INSERT INTO track_cases
            SELECT * FROM cases
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_cases")->fetchColumn();
        $migrationLog[] = "Verfahren migriert ({$count} Einträge)";

        // 5. Case-Parties migrieren
        $pdo->exec("
            INSERT INTO track_case_parties
            SELECT * FROM case_parties
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_case_parties")->fetchColumn();
        $migrationLog[] = "Verfahrens-Beteiligte migriert ({$count} Einträge)";

        // 6. Case-Updates migrieren
        $pdo->exec("
            INSERT INTO track_case_updates
            SELECT * FROM case_updates
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_case_updates")->fetchColumn();
        $migrationLog[] = "Updates migriert ({$count} Einträge)";

        // 7. Legal Bases migrieren (nur wenn nicht aus schema.sql kamen)
        $existingCount = $pdo->query("SELECT COUNT(*) FROM track_legal_bases")->fetchColumn();
        if ($existingCount == 0) {
            $pdo->exec("
                INSERT INTO track_legal_bases
                SELECT * FROM legal_bases
            ");
            $count = $pdo->query("SELECT COUNT(*) FROM track_legal_bases")->fetchColumn();
            $migrationLog[] = "Rechtsgrundlagen migriert ({$count} Einträge)";
        }

        // 8. Case-Legal-Bases migrieren
        $pdo->exec("
            INSERT INTO track_case_legal_bases
            SELECT * FROM case_legal_bases
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_case_legal_bases")->fetchColumn();
        $migrationLog[] = "Verfahrens-Rechtsgrundlagen migriert ({$count} Einträge)";

        // 9. Sources migrieren
        $pdo->exec("
            INSERT INTO track_sources
            SELECT * FROM sources
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_sources")->fetchColumn();
        $migrationLog[] = "Quellen migriert ({$count} Einträge)";

        // 10. Tags migrieren (nur wenn nicht aus schema.sql kamen)
        $existingCount = $pdo->query("SELECT COUNT(*) FROM track_tags")->fetchColumn();
        if ($existingCount == 0) {
            $pdo->exec("
                INSERT INTO track_tags
                SELECT * FROM tags
            ");
            $count = $pdo->query("SELECT COUNT(*) FROM track_tags")->fetchColumn();
            $migrationLog[] = "Tags migriert ({$count} Einträge)";
        }

        // 11. Case-Tags migrieren
        $pdo->exec("
            INSERT INTO track_case_tags
            SELECT * FROM case_tags
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_case_tags")->fetchColumn();
        $migrationLog[] = "Verfahrens-Tags migriert ({$count} Einträge)";

        // 12. Audit-Log migrieren
        $pdo->exec("
            INSERT INTO track_audit_log
            SELECT * FROM audit_log
        ");
        $count = $pdo->query("SELECT COUNT(*) FROM track_audit_log")->fetchColumn();
        $migrationLog[] = "Audit-Log migriert ({$count} Einträge)";

        $pdo->commit();

        $success = 'Migration erfolgreich abgeschlossen!';
        $step = 3;
    } catch (Exception $e) {
        $pdo->rollback();
        $errors[] = 'Migration fehlgeschlagen: ' . $e->getMessage();
    }
}

// STEP 3: Alte Tabellen umbenennen (Backup)
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_old'])) {
    try {
        $tables = ['users', 'parties', 'cases', 'case_parties', 'case_updates', 'legal_bases',
                   'case_legal_bases', 'sources', 'tags', 'case_tags', 'audit_log'];

        foreach ($tables as $table) {
            $pdo->exec("RENAME TABLE `{$table}` TO `{$table}_backup_" . date('Ymd_His') . "`");
        }

        $success = 'Alte Tabellen wurden umbenannt (Backup erstellt).';
        $step = 4;
    } catch (Exception $e) {
        $errors[] = 'Backup fehlgeschlagen: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update - Hakendran Gerichtstracker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #125882 0%, #00b595 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { margin-bottom: 20px; color: #125882; }
        .error { background: #fed7d7; color: #c53030; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .success { background: #c6f6d5; color: #2f855a; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .info { background: #bee3f8; color: #2c5282; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .log { background: #f7fafc; padding: 15px; border-radius: 6px; margin: 20px 0; max-height: 300px; overflow-y: auto; }
        .log p { margin: 5px 0; font-family: monospace; font-size: 13px; }
        .btn {
            background: #125882;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn:hover { background: #0d4160; }
        .btn-danger { background: #c53030; }
        .btn-danger:hover { background: #9b2c2c; }
        ul { margin-left: 20px; margin-top: 10px; }
        code { background: #edf2f7; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Update: Version 2.0</h1>
        <p style="margin-bottom: 30px; color: #666;">Migration zu track_ Präfix und MySQL-basierter Benutzerverwaltung</p>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($migrationLog)): ?>
            <div class="log">
                <strong>Migrations-Log:</strong>
                <?php foreach ($migrationLog as $log): ?>
                    <p>✓ <?= htmlspecialchars($log) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="info">
                <h3>Was wird aktualisiert?</h3>
                <ul>
                    <li>Alle Tabellen erhalten den Präfix <code>track_</code></li>
                    <li>Benutzerverwaltung wird in MySQL integriert</li>
                    <li>Passwort-Hashes werden aus <code>user.auth.php</code> übernommen</li>
                    <li>Alle Daten werden migriert</li>
                    <li>Alte Tabellen werden umbenannt (Backup)</li>
                </ul>
            </div>

            <div class="info" style="margin-top: 20px;">
                <h3>Vorbereitung:</h3>
                <ul>
                    <li><strong>Backup:</strong> Erstellen Sie ein vollständiges Datenbank-Backup</li>
                    <li><strong>Wartungsmodus:</strong> Setzen Sie die Website in den Wartungsmodus</li>
                    <li><strong>Dateien:</strong> Stellen Sie sicher, dass alle neuen Dateien hochgeladen sind</li>
                </ul>
            </div>

            <form method="POST">
                <input type="hidden" name="step" value="2">
                <button type="submit" class="btn">Migration starten</button>
            </form>

        <?php elseif ($step === 2): ?>
            <p>Die Migration läuft...</p>

        <?php elseif ($step === 3): ?>
            <div class="info">
                <h3>Migration abgeschlossen!</h3>
                <p>Alle Daten wurden erfolgreich in die neuen Tabellen migriert.</p>
                <p style="margin-top: 10px;">Die alten Tabellen existieren noch. Möchten Sie sie als Backup umbenennen?</p>
            </div>

            <form method="POST">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="backup_old" value="1">
                <button type="submit" class="btn">Alte Tabellen umbenennen (Backup)</button>
                <a href="/index.php" class="btn" style="background: #718096; text-decoration: none; display: inline-block;">Ohne Backup fortfahren</a>
            </form>

        <?php elseif ($step === 4): ?>
            <div class="success">
                <h3>Update erfolgreich abgeschlossen!</h3>
            </div>

            <div class="info">
                <h3>Nächste Schritte:</h3>
                <ol>
                    <li>Löschen Sie <code>update.php</code> aus Sicherheitsgründen</li>
                    <li>Die Datei <code>user.auth.php</code> wird nicht mehr benötigt (kann gelöscht werden)</li>
                    <li>Testen Sie alle Funktionen</li>
                    <li>Bei Problemen: Alte Tabellen sind als <code>*_backup_*</code> verfügbar</li>
                </ol>
            </div>

            <p style="margin-top: 30px;">
                <a href="/index.php" class="btn">Zur Startseite</a>
                <a href="/backend/login.php" class="btn">Zum Login</a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
