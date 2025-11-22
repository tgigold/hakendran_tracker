<?php
// Session und Libraries laden falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!class_exists('Database')) {
    require_once __DIR__ . '/../libraries/Database.php';
}
if (!class_exists('Auth')) {
    require_once __DIR__ . '/../libraries/Auth.php';
}
if (!class_exists('Helpers')) {
    require_once __DIR__ . '/../libraries/Helpers.php';
}

// Auth-Instanz (falls benötigt)
$auth = new Auth();
$isLoggedIn = $auth->isLoggedIn();
$currentUser = $isLoggedIn ? $auth->getUser() : null;

// Aktuelle Seite ermitteln
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Helpers::e($pageTitle) . ' - ' : '' ?>Haken Dran Verfahrenstracker</title>

    <!-- Bulma CSS -->
    <link rel="stylesheet" href="/assets/vendor/bulma.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><circle cx='12' cy='12' r='10' fill='%23125882'/></svg>">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar is-dark" role="navigation" aria-label="main navigation">
        <div class="container">
            <div class="navbar-brand">
                <a class="navbar-item" href="/index.php">
                    <span style="font-weight: 700; font-size: 1.25rem;">Haken Dran Verfahrenstracker</span>
                </a>

                <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="mainNavbar">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </a>
            </div>

            <div id="mainNavbar" class="navbar-menu">
                <div class="navbar-start">
                    <a class="navbar-item <?= $currentPage === 'index' ? 'is-active' : '' ?>" href="/index.php">
                        Startseite
                    </a>
                    <a class="navbar-item <?= $currentPage === 'cases' ? 'is-active' : '' ?>" href="/cases.php">
                        Verfahren
                    </a>
                    <a class="navbar-item <?= $currentPage === 'stats' ? 'is-active' : '' ?>" href="/stats.php">
                        Statistiken
                    </a>
                </div>

                <div class="navbar-end">
                    <!-- Darkmode Toggle -->
                    <div class="navbar-item">
                        <button class="button is-dark" id="darkModeToggle" title="Darkmode umschalten">
                            <span class="icon" id="darkModeIcon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                </svg>
                            </span>
                        </button>
                    </div>

                    <?php if ($isLoggedIn): ?>
                        <div class="navbar-item has-dropdown is-hoverable">
                            <a class="navbar-link">
                                <?= Helpers::e($currentUser['display_name']) ?>
                            </a>

                            <div class="navbar-dropdown is-right">
                                <a class="navbar-item" href="/backend/dashboard.php">
                                    Dashboard
                                </a>
                                <a class="navbar-item" href="/backend/case-form.php">
                                    Neues Verfahren
                                </a>
                                <a class="navbar-item" href="/backend/parties.php">
                                    Beteiligte verwalten
                                </a>
                                <hr class="navbar-divider">
                                <a class="navbar-item" href="/backend/logout.php">
                                    Abmelden
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="navbar-item">
                            <a class="button is-primary" href="/backend/login.php">
                                Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
