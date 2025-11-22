<?php
/**
 * Hakendran Big Tech Verfahrenstracker
 * Installations-Script
 */

// Sicherheitscheck: Installation nur einmal erlauben
$configPath = dirname(__DIR__) . '/config.inc.php';

if (file_exists($configPath)) {
    die('<h1>Installation bereits abgeschlossen</h1><p>Bitte löschen Sie install.php oder config.inc.php für eine Neuinstallation.</p>');
}

// Session starten für Formulardaten
session_start();

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$errors = [];
$success = '';

// ============================================
// STEP 2: Datenbank-Verbindung testen
// ============================================
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['db_host'] = trim($_POST['db_host']);
    $_SESSION['db_name'] = trim($_POST['db_name']);
    $_SESSION['db_user'] = trim($_POST['db_user']);
    $_SESSION['db_pass'] = trim($_POST['db_pass']);
    $_SESSION['db_charset'] = 'utf8mb4';

    try {
        $dsn = "mysql:host={$_SESSION['db_host']};charset={$_SESSION['db_charset']}";
        $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Prüfen ob Datenbank existiert, sonst erstellen
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$_SESSION['db_name']}'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("CREATE DATABASE `{$_SESSION['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $success = "Datenbank '{$_SESSION['db_name']}' wurde erstellt.";
        } else {
            $success = "Datenbank '{$_SESSION['db_name']}' existiert bereits.";
        }

        $step = 3;
    } catch (PDOException $e) {
        $errors[] = "Datenbankverbindung fehlgeschlagen: " . $e->getMessage();
        $step = 2;
    }
}

// ============================================
// STEP 3: Schema importieren
// ============================================
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_schema'])) {
    try {
        $dsn = "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset={$_SESSION['db_charset']}";
        $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Schema-Datei einlesen
        $schemaFile = dirname(__DIR__) . '/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("schema.sql nicht gefunden!");
        }

        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);

        $success = "Datenbank-Tabellen wurden erfolgreich erstellt.";
        $step = 4;
    } catch (Exception $e) {
        $errors[] = "Schema-Import fehlgeschlagen: " . $e->getMessage();
    }
}

// ============================================
// STEP 4: Admin-User erstellen
// ============================================
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $_SESSION['admin_username'] = trim($_POST['admin_username']);
    $_SESSION['admin_password'] = $_POST['admin_password'];
    $_SESSION['admin_email'] = trim($_POST['admin_email']);
    $_SESSION['admin_displayname'] = trim($_POST['admin_displayname']);

    if (strlen($_SESSION['admin_password']) < 8) {
        $errors[] = "Passwort muss mindestens 8 Zeichen lang sein.";
    }

    if (empty($errors)) {
        try {
            // User in Datenbank eintragen
            $dsn = "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset={$_SESSION['db_charset']}";
            $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // @ Präfix hinzufügen falls nicht vorhanden
            $username = $_SESSION['admin_username'];
            if (substr($username, 0, 1) !== '@') {
                $username = '@' . $username;
            }

            // Passwort hashen
            $passwordHash = password_hash($_SESSION['admin_password'], PASSWORD_ARGON2ID);

            $stmt = $pdo->prepare("INSERT INTO track_users (username, password_hash, display_name, email, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([
                $username,
                $passwordHash,
                $_SESSION['admin_displayname'],
                $_SESSION['admin_email']
            ]);

            $success = "Admin-User wurde erfolgreich erstellt.";
            $step = 5;
        } catch (Exception $e) {
            $errors[] = "User-Erstellung fehlgeschlagen: " . $e->getMessage();
        }
    }
}

// ============================================
// STEP 5: Config-Datei erstellen
// ============================================
if ($step === 5 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_config'])) {
    try {
        // config.inc.php erstellen
        $configContent = "<?php\n";
        $configContent .= "/**\n";
        $configContent .= " * Hakendran Tracker - Konfiguration\n";
        $configContent .= " * WICHTIG: Diese Datei sollte außerhalb des Webroots liegen!\n";
        $configContent .= " * Erstellt: " . date('Y-m-d H:i:s') . "\n";
        $configContent .= " */\n\n";
        $configContent .= "define('DB_HOST', '{$_SESSION['db_host']}');\n";
        $configContent .= "define('DB_NAME', '{$_SESSION['db_name']}');\n";
        $configContent .= "define('DB_USER', '{$_SESSION['db_user']}');\n";
        $configContent .= "define('DB_PASS', '{$_SESSION['db_pass']}');\n";
        $configContent .= "define('DB_CHARSET', '{$_SESSION['db_charset']}');\n\n";
        $configContent .= "// Zeitzone\n";
        $configContent .= "date_default_timezone_set('Europe/Berlin');\n\n";
        $configContent .= "// Session-Einstellungen\n";
        $configContent .= "ini_set('session.cookie_httponly', 1);\n";
        $configContent .= "ini_set('session.cookie_secure', 0); // Auf 1 setzen bei HTTPS\n";
        $configContent .= "ini_set('session.use_strict_mode', 1);\n";

        if (file_put_contents($configPath, $configContent) === false) {
            throw new Exception("Konnte config.inc.php nicht erstellen!");
        }

        // Session bereinigen
        session_destroy();

        $success = "Installation erfolgreich abgeschlossen!";
        $step = 6;
    } catch (Exception $e) {
        $errors[] = "Config-Erstellung fehlgeschlagen: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Hakendran Gerichtstracker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #999;
            position: relative;
            z-index: 1;
        }
        .step.active {
            background: #667eea;
            color: white;
        }
        .step.completed {
            background: #48bb78;
            color: white;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }
        .btn:hover {
            background: #5568d3;
        }
        .error {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #c53030;
        }
        .success {
            background: #c6f6d5;
            color: #2f855a;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2f855a;
        }
        .info {
            background: #bee3f8;
            color: #2c5282;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2c5282;
        }
        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        .completion {
            text-align: center;
        }
        .completion svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
        }
        .completion h2 {
            color: #48bb78;
            margin-bottom: 10px;
        }
        .completion-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        .completion-actions a {
            flex: 1;
            padding: 12px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
        }
        .completion-actions .btn-primary {
            background: #667eea;
            color: white;
        }
        .completion-actions .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Haken Dran Verfahrenstracker</h1>
        <p class="subtitle">Installation</p>

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

        <?php if ($step < 6): ?>
        <div class="step-indicator">
            <div class="step <?= $step >= 1 ? 'active' : '' ?>">1</div>
            <div class="step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">2</div>
            <div class="step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">3</div>
            <div class="step <?= $step >= 4 ? 'active' : '' ?> <?= $step > 4 ? 'completed' : '' ?>">4</div>
            <div class="step <?= $step >= 5 ? 'active' : '' ?> <?= $step > 5 ? 'completed' : '' ?>">5</div>
        </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h2>Willkommen!</h2>
            <div class="info">
                <p><strong>Dieses Script richtet Ihren Gerichtstracker ein.</strong></p>
                <p style="margin-top: 10px;">Sie benötigen:</p>
                <ul style="margin-left: 20px; margin-top: 5px;">
                    <li>MySQL-Datenbank (8.0+)</li>
                    <li>PHP 8.0+</li>
                    <li>Schreibrechte im übergeordneten Verzeichnis</li>
                </ul>
            </div>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <button type="submit" class="btn">Installation starten</button>
            </form>

        <?php elseif ($step === 2): ?>
            <h2>Schritt 1: Datenbank-Verbindung</h2>
            <form method="POST">
                <input type="hidden" name="step" value="2">

                <div class="form-group">
                    <label>Datenbank-Host</label>
                    <input type="text" name="db_host" value="<?= $_SESSION['db_host'] ?? 'localhost' ?>" required>
                    <p class="help-text">Meistens: localhost</p>
                </div>

                <div class="form-group">
                    <label>Datenbankname</label>
                    <input type="text" name="db_name" value="<?= $_SESSION['db_name'] ?? 'hakendran_tracker' ?>" required>
                    <p class="help-text">Wird erstellt, falls nicht vorhanden</p>
                </div>

                <div class="form-group">
                    <label>Datenbank-Benutzer</label>
                    <input type="text" name="db_user" value="<?= $_SESSION['db_user'] ?? '' ?>" required>
                </div>

                <div class="form-group">
                    <label>Datenbank-Passwort</label>
                    <input type="password" name="db_pass" value="<?= $_SESSION['db_pass'] ?? '' ?>">
                </div>

                <button type="submit" class="btn">Verbindung testen</button>
            </form>

        <?php elseif ($step === 3): ?>
            <h2>Schritt 2: Tabellen erstellen</h2>
            <div class="info">
                <p>Die Datenbank-Verbindung wurde erfolgreich getestet!</p>
                <p style="margin-top: 10px;">Klicken Sie auf "Tabellen erstellen" um das Datenbank-Schema zu importieren.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="import_schema" value="1">
                <button type="submit" class="btn">Tabellen erstellen</button>
            </form>

        <?php elseif ($step === 4): ?>
            <h2>Schritt 3: Admin-User anlegen</h2>
            <form method="POST">
                <input type="hidden" name="step" value="4">
                <input type="hidden" name="create_user" value="1">

                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="admin_username" value="<?= $_SESSION['admin_username'] ?? 'admin' ?>" required>
                    <p class="help-text">Wird automatisch mit @ Präfix gespeichert (z.B. @admin)</p>
                </div>

                <div class="form-group">
                    <label>Anzeigename</label>
                    <input type="text" name="admin_displayname" value="<?= $_SESSION['admin_displayname'] ?? '' ?>" required>
                </div>

                <div class="form-group">
                    <label>E-Mail (optional)</label>
                    <input type="email" name="admin_email" value="<?= $_SESSION['admin_email'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label>Passwort</label>
                    <input type="password" name="admin_password" required>
                    <p class="help-text">Mindestens 8 Zeichen</p>
                </div>

                <button type="submit" class="btn">Admin erstellen</button>
            </form>

        <?php elseif ($step === 5): ?>
            <h2>Schritt 4: Konfiguration speichern</h2>
            <div class="info">
                <p><strong>Wichtig:</strong> Die Konfigurationsdatei wird erstellt:</p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li><code>config.inc.php</code> - Datenbank-Verbindung und Einstellungen</li>
                </ul>
                <p style="margin-top: 10px;">⚠️ <strong>Sicherheitshinweis:</strong> Verschieben Sie diese Datei nach der Installation außerhalb des Webroots!</p>
                <p style="margin-top: 10px;">Benutzer werden in der MySQL-Datenbank (Tabelle: track_users) gespeichert.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="step" value="5">
                <input type="hidden" name="create_config" value="1">
                <button type="submit" class="btn">Konfiguration erstellen</button>
            </form>

        <?php elseif ($step === 6): ?>
            <div class="completion">
                <svg viewBox="0 0 24 24" fill="none" stroke="#48bb78" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <h2>Installation abgeschlossen!</h2>
                <p style="color: #666; margin-bottom: 20px;">Der Hakendran Gerichtstracker wurde erfolgreich installiert.</p>

                <div class="info" style="text-align: left;">
                    <p><strong>Nächste Schritte:</strong></p>
                    <ol style="margin-left: 20px; margin-top: 10px;">
                        <li>Verschieben Sie <code>config.inc.php</code> außerhalb von <code>/www/</code></li>
                        <li>Löschen Sie <code>install.php</code> aus Sicherheitsgründen</li>
                        <li>Laden Sie die benötigten CSS/JS-Bibliotheken in <code>/www/assets/vendor/</code> herunter</li>
                        <li>Lesen Sie <code>www/assets/vendor/VENDOR_INFO.txt</code> für Details</li>
                        <li>Weitere Benutzer können über die MySQL-Datenbank (Tabelle: <code>track_users</code>) hinzugefügt werden</li>
                    </ol>
                </div>

                <div class="completion-actions">
                    <a href="index.php" class="btn-primary">Zur Startseite</a>
                    <a href="backend/login.php" class="btn-secondary">Zum Login</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
