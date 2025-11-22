<?php
/**
 * Login-Seite
 */

require_once __DIR__ . '/../libraries/Database.php';
require_once __DIR__ . '/../libraries/Auth.php';
require_once __DIR__ . '/../libraries/Helpers.php';

$auth = new Auth();

// Bereits eingeloggt? Redirect zum Dashboard
if ($auth->isLoggedIn()) {
    Helpers::redirect('/backend/dashboard.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? '/backend/dashboard.php';

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = Helpers::sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Bitte geben Sie Benutzername und Passwort ein.';
    } else {
        if ($auth->login($username, $password)) {
            Helpers::redirect($redirect);
        } else {
            $error = 'Ungültiger Benutzername oder Passwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hakendran Gerichtstracker</title>
    <link rel="stylesheet" href="/assets/vendor/bulma.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <section class="hero is-fullheight" style="background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%);">
        <div class="hero-body">
            <div class="container">
                <div class="columns is-centered">
                    <div class="column is-5-tablet is-4-desktop">
                        <div class="box" style="border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                            <div style="text-align: center; margin-bottom: 2rem;">
                                <div style="width: 80px; height: 80px; margin: 0 auto; background-color: #125882; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" width="40" height="40">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </div>
                                <h1 class="title is-4" style="margin-top: 1rem;">Haken Dran Verfahrenstracker</h1>
                                <p class="subtitle is-6">Backend-Login</p>
                            </div>

                            <?php if ($error): ?>
                                <div class="notification is-danger is-light">
                                    <?= Helpers::e($error) ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="field">
                                    <label class="label">Benutzername</label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="text" name="username" placeholder="admin" required autofocus value="<?= Helpers::e($username ?? '') ?>">
                                        <span class="icon is-small is-left">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="12" cy="7" r="4"></circle>
                                            </svg>
                                        </span>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="label">Passwort</label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="password" name="password" placeholder="••••••••" required>
                                        <span class="icon is-small is-left">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </span>
                                    </div>
                                </div>

                                <div class="field">
                                    <button type="submit" class="button is-primary is-fullwidth">
                                        Anmelden
                                    </button>
                                </div>
                            </form>

                            <hr>

                            <div style="text-align: center;">
                                <a href="/index.php" class="button is-text">
                                    ← Zurück zur Startseite
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="/assets/js/app.js"></script>
</body>
</html>
