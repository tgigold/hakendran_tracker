<?php
/**
 * Benutzerverwaltung
 */

$pageTitle = 'Benutzer verwalten';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../libraries/Database.php';
require_once __DIR__ . '/../libraries/Auth.php';
require_once __DIR__ . '/../libraries/Helpers.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';

// Neuen Benutzer erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = Helpers::sanitize($_POST['username'] ?? '');
    $displayName = Helpers::sanitize($_POST['display_name'] ?? '');
    $email = Helpers::sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validierung
    if (empty($username)) {
        $errors[] = 'Benutzername ist erforderlich.';
    } elseif (substr($username, 0, 1) === '@') {
        $errors[] = 'Benutzername darf nicht mit @ beginnen. Nur der Anzeigename hat @ (z.B. Benutzername: admin, Anzeigename: @admin).';
    }

    if (empty($displayName)) {
        $errors[] = 'Anzeigename ist erforderlich.';
    } elseif (substr($displayName, 0, 1) !== '@') {
        $errors[] = 'Anzeigename muss mit @ beginnen (z.B. @admin).';
    }

    if (empty($password)) {
        $errors[] = 'Passwort ist erforderlich.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
    }

    if (empty($errors)) {
        try {
            // Prüfen ob Username bereits existiert
            $exists = $db->queryOne("SELECT id FROM track_users WHERE username = ?", [$username]);
            if ($exists) {
                $errors[] = "Benutzername '{$username}' ist bereits vergeben.";
            } else {
                $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
                $db->insert(
                    "INSERT INTO track_users (username, password_hash, display_name, email, is_active) VALUES (?, ?, ?, ?, 1)",
                    [$username, $passwordHash, $displayName, $email]
                );
                $success = "Benutzer '{$username}' wurde erfolgreich erstellt.";
                $auth->logAction('user_created', 'user', null, "Created user: {$username}");
            }
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Erstellen: ' . $e->getMessage();
        }
    }
}

// Benutzer deaktivieren/aktivieren
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $userId = intval($_GET['toggle']);
    $user = $db->queryOne("SELECT id, username, is_active FROM track_users WHERE id = ?", [$userId]);

    if ($user) {
        // Verhindere, dass der aktuelle Benutzer sich selbst deaktiviert
        if ($userId == $auth->getUserId()) {
            $errors[] = "Sie können sich nicht selbst deaktivieren.";
        } else {
            $newStatus = $user['is_active'] ? 0 : 1;
            $db->execute("UPDATE track_users SET is_active = ? WHERE id = ?", [$newStatus, $userId]);
            $action = $newStatus ? 'aktiviert' : 'deaktiviert';
            $success = "Benutzer '{$user['username']}' wurde {$action}.";
            $auth->logAction('user_toggled', 'user', $userId, "User {$action}: {$user['username']}");
        }
    }
}

// Passwort zurücksetzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = intval($_POST['user_id']);
    $newPassword = $_POST['new_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
    } else {
        $user = $db->queryOne("SELECT username FROM track_users WHERE id = ?", [$userId]);
        if ($user) {
            $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
            $db->execute("UPDATE track_users SET password_hash = ? WHERE id = ?", [$passwordHash, $userId]);
            $success = "Passwort für '{$user['username']}' wurde zurückgesetzt.";
            $auth->logAction('password_reset', 'user', $userId, "Password reset for: {$user['username']}");
        }
    }
}

// Alle Benutzer laden
$users = $db->query("
    SELECT id, username, display_name, email, is_active, created_at, last_login
    FROM track_users
    ORDER BY created_at DESC
");
?>

<div class="container">
    <section class="section">
        <nav class="breadcrumb" aria-label="breadcrumbs">
            <ul>
                <li><a href="/backend/dashboard.php">Dashboard</a></li>
                <li class="is-active"><a href="#" aria-current="page">Benutzer</a></li>
            </ul>
        </nav>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="title">Benutzerverwaltung</h1>
            <button class="button is-primary" onclick="document.getElementById('createModal').classList.add('is-active')">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </span>
                <span>Neuer Benutzer</span>
            </button>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="notification is-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= Helpers::e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="notification is-success">
                <?= Helpers::e($success) ?>
            </div>
        <?php endif; ?>

        <!-- Benutzer-Tabelle -->
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable">
                <thead>
                    <tr>
                        <th>Benutzername</th>
                        <th>Anzeigename</th>
                        <th>E-Mail</th>
                        <th>Status</th>
                        <th>Letzter Login</th>
                        <th>Erstellt</th>
                        <th style="width: 200px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <span class="has-text-weight-semibold"><?= Helpers::e($user['username']) ?></span>
                            <?php if ($user['id'] == $auth->getUserId()): ?>
                                <span class="tag is-small is-info">Sie</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Helpers::e($user['display_name']) ?></td>
                        <td><?= Helpers::e($user['email']) ?></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="tag is-success">Aktiv</span>
                            <?php else: ?>
                                <span class="tag is-danger">Deaktiviert</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <?= Helpers::formatDate($user['last_login']) ?>
                            <?php else: ?>
                                <span class="has-text-grey">Nie</span>
                            <?php endif; ?>
                        </td>
                        <td><?= Helpers::formatDate($user['created_at']) ?></td>
                        <td>
                            <div class="buttons">
                                <?php if ($user['id'] != $auth->getUserId()): ?>
                                    <a href="?toggle=<?= $user['id'] ?>"
                                       class="button is-small <?= $user['is_active'] ? 'is-warning' : 'is-success' ?>"
                                       onclick="return confirm('Möchten Sie diesen Benutzer <?= $user['is_active'] ? 'deaktivieren' : 'aktivieren' ?>?')">
                                        <?= $user['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?>
                                    </a>
                                <?php endif; ?>
                                <button class="button is-small is-light" onclick="showResetPasswordModal(<?= $user['id'] ?>, '<?= Helpers::e($user['username']) ?>')">
                                    Passwort
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="notification is-info is-light" style="margin-top: 2rem;">
            <p><strong>Hinweis:</strong> Benutzer können nicht gelöscht werden, um die Datenintegrität zu wahren. Deaktivieren Sie Benutzer stattdessen.</p>
            <p style="margin-top: 0.5rem;"><strong>Login:</strong> Benutzername ohne @, Anzeigename mit @ (z.B. Benutzername: admin, Anzeigename: @admin).</p>
        </div>
    </section>
</div>

<!-- Modal: Neuer Benutzer -->
<div class="modal" id="createModal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Neuer Benutzer</p>
            <button class="delete" aria-label="close" onclick="document.getElementById('createModal').classList.remove('is-active')"></button>
        </header>
        <form method="POST">
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Benutzername (für Login)</label>
                    <div class="control">
                        <input class="input" type="text" name="username" placeholder="admin" required>
                    </div>
                    <p class="help">Ohne @ (z.B. admin, editor)</p>
                </div>

                <div class="field">
                    <label class="label">Anzeigename (öffentlich sichtbar)</label>
                    <div class="control">
                        <input class="input" type="text" name="display_name" placeholder="@Administrator" required>
                    </div>
                    <p class="help">Muss mit @ beginnen (z.B. @admin)</p>
                </div>

                <div class="field">
                    <label class="label">E-Mail (optional)</label>
                    <div class="control">
                        <input class="input" type="email" name="email" placeholder="admin@example.com">
                    </div>
                </div>

                <div class="field">
                    <label class="label">Passwort</label>
                    <div class="control">
                        <input class="input" type="password" name="password" required>
                    </div>
                    <p class="help">Mindestens 8 Zeichen</p>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button type="submit" name="create_user" class="button is-primary">Benutzer erstellen</button>
                <button type="button" class="button" onclick="document.getElementById('createModal').classList.remove('is-active')">Abbrechen</button>
            </footer>
        </form>
    </div>
</div>

<!-- Modal: Passwort zurücksetzen -->
<div class="modal" id="resetPasswordModal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Passwort zurücksetzen</p>
            <button class="delete" aria-label="close" onclick="document.getElementById('resetPasswordModal').classList.remove('is-active')"></button>
        </header>
        <form method="POST">
            <section class="modal-card-body">
                <input type="hidden" name="user_id" id="reset_user_id">
                <p class="mb-4">Neues Passwort für Benutzer: <strong id="reset_username"></strong></p>

                <div class="field">
                    <label class="label">Neues Passwort</label>
                    <div class="control">
                        <input class="input" type="password" name="new_password" required>
                    </div>
                    <p class="help">Mindestens 8 Zeichen</p>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button type="submit" name="reset_password" class="button is-warning">Passwort zurücksetzen</button>
                <button type="button" class="button" onclick="document.getElementById('resetPasswordModal').classList.remove('is-active')">Abbrechen</button>
            </footer>
        </form>
    </div>
</div>

<script>
function showResetPasswordModal(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('resetPasswordModal').classList.add('is-active');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
