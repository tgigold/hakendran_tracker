<?php
/**
 * Backend Dashboard
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../libraries/Database.php';
require_once __DIR__ . '/../libraries/Auth.php';
require_once __DIR__ . '/../libraries/Helpers.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

// Statistiken
$stats = [
    'total_cases' => $db->count('track_cases'),
    'public_cases' => $db->count('track_cases', 'public_visibility = 1'),
    'draft_cases' => $db->count('track_cases', 'public_visibility = 0'),
    'total_parties' => $db->count('track_parties'),
    'big_tech' => $db->count('track_parties', 'is_big_tech = 1'),
    'total_updates' => $db->count('track_case_updates'),
];

// Neueste Cases
$latestCases = $db->query("
    SELECT * FROM track_cases
    ORDER BY created_at DESC
    LIMIT 5
");

// Kürzlich aktualisierte Cases
$recentlyUpdated = $db->query("
    SELECT * FROM track_cases
    ORDER BY updated_at DESC
    LIMIT 5
");

// Audit Log (letzte Aktivitäten)
$recentActivity = $db->query("
    SELECT al.*, u.display_name
    FROM track_audit_log al
    LEFT JOIN track_users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");
?>

<div class="container">
    <section class="section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 class="title">Dashboard</h1>
                <p class="subtitle">Willkommen, <?= Helpers::e($auth->getDisplayName()) ?>!</p>
            </div>
            <div class="buttons">
                <a href="/backend/case-form.php" class="button is-primary">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </span>
                    <span>Neues Verfahren</span>
                </a>
            </div>
        </div>

        <!-- Statistik-Cards -->
        <div class="columns is-multiline" style="margin-bottom: 2rem;">
            <div class="column is-2">
                <div class="box stat-card">
                    <div class="stat-value"><?= $stats['total_cases'] ?></div>
                    <div class="stat-label">Verfahren</div>
                </div>
            </div>
            <div class="column is-2">
                <div class="box stat-card">
                    <div class="stat-value" style="color: #10B981;"><?= $stats['public_cases'] ?></div>
                    <div class="stat-label">Öffentlich</div>
                </div>
            </div>
            <div class="column is-2">
                <div class="box stat-card">
                    <div class="stat-value" style="color: #F59E0B;"><?= $stats['draft_cases'] ?></div>
                    <div class="stat-label">Entwürfe</div>
                </div>
            </div>
            <div class="column is-2">
                <div class="box stat-card">
                    <div class="stat-value"><?= $stats['total_parties'] ?></div>
                    <div class="stat-label">Beteiligte</div>
                </div>
            </div>
            <div class="column is-2">
                <div class="box stat-card">
                    <div class="stat-value" style="color: #EF4444;"><?= $stats['big_tech'] ?></div>
                    <div class="stat-label">Big Tech</div>
                </div>
            </div>
            <div class="column is-2">
                <div class="box stat-card">
                    <div class="stat-value"><?= $stats['total_updates'] ?></div>
                    <div class="stat-label">Updates</div>
                </div>
            </div>
        </div>

        <div class="columns">
            <!-- Neueste Cases -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Neueste Verfahren</h2>
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>Titel</th>
                                <th>Status</th>
                                <th>Erstellt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestCases as $case): ?>
                            <tr>
                                <td>
                                    <a href="/case.php?id=<?= $case['id'] ?>">
                                        <?= Helpers::e(Helpers::truncate($case['title'], 40)) ?>
                                    </a>
                                    <?php if (!$case['public_visibility']): ?>
                                        <span class="tag is-warning is-small">Entwurf</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= Helpers::statusBadge($case['status']) ?></td>
                                <td class="is-size-7"><?= Helpers::timeAgo($case['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="/cases.php" class="button is-light is-fullwidth">Alle anzeigen</a>
                </div>
            </div>

            <!-- Kürzlich aktualisiert -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Kürzlich aktualisiert</h2>
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>Titel</th>
                                <th>Status</th>
                                <th>Geändert</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentlyUpdated as $case): ?>
                            <tr>
                                <td>
                                    <a href="/backend/case-form.php?id=<?= $case['id'] ?>">
                                        <?= Helpers::e(Helpers::truncate($case['title'], 40)) ?>
                                    </a>
                                </td>
                                <td><?= Helpers::statusBadge($case['status']) ?></td>
                                <td class="is-size-7"><?= Helpers::timeAgo($case['updated_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="/backend/parties.php" class="button is-light is-fullwidth">Beteiligte verwalten</a>
                </div>
            </div>
        </div>

        <!-- Aktivitätslog -->
        <div class="box">
            <h2 class="title is-5">Letzte Aktivitäten</h2>
            <table class="table is-fullwidth is-hoverable">
                <thead>
                    <tr>
                        <th>Aktion</th>
                        <th>Benutzer</th>
                        <th>Details</th>
                        <th>Zeit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $log): ?>
                    <tr>
                        <td><span class="tag is-light"><?= Helpers::e($log['action']) ?></span></td>
                        <td><?= Helpers::e($log['display_name'] ?? 'System') ?></td>
                        <td class="is-size-7"><?= Helpers::e(Helpers::truncate($log['details'] ?? '', 50)) ?></td>
                        <td class="is-size-7"><?= Helpers::timeAgo($log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Schnellzugriff -->
        <div class="box">
            <h2 class="title is-5">Schnellzugriff</h2>
            <div class="buttons">
                <a href="/backend/case-form.php" class="button is-primary">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </span>
                    <span>Neues Verfahren</span>
                </a>
                <a href="/backend/parties.php" class="button is-link">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </span>
                    <span>Beteiligte verwalten</span>
                </a>
                <a href="/stats.php" class="button is-info">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </span>
                    <span>Statistiken</span>
                </a>
                <a href="/cases.php" class="button is-light">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                    </span>
                    <span>Verfahren durchsuchen</span>
                </a>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
