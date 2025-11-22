<?php
/**
 * Case-Detailansicht
 */

require_once __DIR__ . '/libraries/Database.php';
require_once __DIR__ . '/libraries/Auth.php';
require_once __DIR__ . '/libraries/Helpers.php';
require_once __DIR__ . '/libraries/Parsedown.php';

$db = Database::getInstance();
$auth = new Auth();
$parsedown = new Parsedown();

// Case ID
$caseId = intval($_GET['id'] ?? 0);

if ($caseId === 0) {
    header('Location: /cases.php');
    exit;
}

// Case laden
$case = $db->queryOne("
    SELECT c.*,
           u_created.display_name as created_by_name,
           u_updated.display_name as updated_by_name
    FROM cases c
    LEFT JOIN users u_created ON c.created_by = u_created.id
    LEFT JOIN users u_updated ON c.updated_by = u_updated.id
    WHERE c.id = ?
", [$caseId]);

if (!$case) {
    header('Location: /cases.php');
    exit;
}

// Nicht öffentliche Cases nur für eingeloggte User
if (!$case['public_visibility'] && !$auth->isLoggedIn()) {
    header('Location: /cases.php');
    exit;
}

// Beteiligte laden
$parties = $db->query("
    SELECT cp.*, p.name, p.type, p.is_big_tech, p.website
    FROM case_parties cp
    INNER JOIN parties p ON cp.party_id = p.id
    WHERE cp.case_id = ?
    ORDER BY cp.role, p.name
", [$caseId]);

$groupedParties = [
    'plaintiff' => [],
    'defendant' => [],
    'intervenor' => [],
    'amicus' => []
];

foreach ($parties as $party) {
    $groupedParties[$party['role']][] = $party;
}

// Updates/Timeline laden
$updates = $db->query("
    SELECT u.*, us.display_name as created_by_name
    FROM case_updates u
    LEFT JOIN users us ON u.created_by = us.id
    WHERE u.case_id = ?
    ORDER BY u.update_date DESC, u.created_at DESC
", [$caseId]);

// Rechtsgrundlagen laden
$legalBases = $db->query("
    SELECT lb.*
    FROM legal_bases lb
    INNER JOIN case_legal_bases clb ON lb.id = clb.legal_basis_id
    WHERE clb.case_id = ?
    ORDER BY lb.category, lb.code
", [$caseId]);

// Tags laden
$tags = $db->query("
    SELECT t.*
    FROM tags t
    INNER JOIN case_tags ct ON t.id = ct.tag_id
    WHERE ct.case_id = ?
    ORDER BY t.name
", [$caseId]);

// Quellen laden
$sources = $db->query("
    SELECT * FROM sources
    WHERE case_id = ?
    ORDER BY date_published DESC, created_at DESC
", [$caseId]);

$pageTitle = $case['title'];
require_once __DIR__ . '/templates/header.php';
?>

<div class="container">
    <section class="section">
        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="breadcrumbs">
            <ul>
                <li><a href="/index.php">Startseite</a></li>
                <li><a href="/cases.php">Verfahren</a></li>
                <li class="is-active"><a href="#" aria-current="page"><?= Helpers::truncate($case['title'], 50) ?></a></li>
            </ul>
        </nav>

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem;">
            <div style="flex: 1;">
                <h1 class="title is-3"><?= Helpers::e($case['title']) ?></h1>
                <div style="margin-top: 0.5rem;">
                    <?= Helpers::statusBadge($case['status']) ?>
                    <?php if ($case['country_code']): ?>
                        <span class="tag is-light"><?= Helpers::countryFlag($case['country_code']) ?> <?= Helpers::countryName($case['country_code']) ?></span>
                    <?php endif; ?>
                    <span class="tag is-light"><?= Helpers::legalActionTypeLabel($case['legal_action_type']) ?></span>
                    <?php if (!$case['public_visibility']): ?>
                        <span class="tag is-warning">Nicht öffentlich</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($auth->isLoggedIn()): ?>
            <div class="buttons">
                <a href="/backend/case-form.php?id=<?= $case['id'] ?>" class="button is-primary">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </span>
                    <span>Bearbeiten</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="columns">
            <!-- Hauptinhalt -->
            <div class="column is-8">
                <!-- Übersicht -->
                <div class="box">
                    <h2 class="title is-5">📋 Übersicht</h2>

                    <table class="table is-fullwidth">
                        <tbody>
                            <?php if ($case['case_number']): ?>
                            <tr>
                                <th style="width: 200px;">Aktenzeichen</th>
                                <td><?= Helpers::e($case['case_number']) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($case['court_file']): ?>
                            <tr>
                                <th>Gerichtsakte</th>
                                <td><?= Helpers::e($case['court_file']) ?></td>
                            </tr>
                            <?php endif; ?>

                            <tr>
                                <th>Verfahrensart</th>
                                <td><?= Helpers::legalActionTypeLabel($case['legal_action_type']) ?></td>
                            </tr>

                            <?php if ($case['cause_of_action']): ?>
                            <tr>
                                <th>Rechtsgebiet</th>
                                <td><?= Helpers::e($case['cause_of_action']) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($case['court_name']): ?>
                            <tr>
                                <th>Gericht</th>
                                <td>
                                    <?= Helpers::e($case['court_name']) ?>
                                    <?php if ($case['court_level']): ?>
                                        <span class="tag is-light is-small"><?= Helpers::courtLevelLabel($case['court_level']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($case['date_filed']): ?>
                            <tr>
                                <th>Eingereicht am</th>
                                <td><?= Helpers::formatDate($case['date_filed']) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($case['next_hearing_date']): ?>
                            <tr>
                                <th>Nächste Anhörung</th>
                                <td><strong><?= Helpers::formatDate($case['next_hearing_date']) ?></strong></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($case['date_concluded']): ?>
                            <tr>
                                <th>Abgeschlossen am</th>
                                <td><?= Helpers::formatDate($case['date_concluded']) ?></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($case['amount_disputed']): ?>
                            <tr>
                                <th>Streitwert</th>
                                <td><strong style="font-size: 1.2em; color: var(--primary-color);"><?= Helpers::formatCurrency($case['amount_disputed'], $case['currency']) ?></strong></td>
                            </tr>
                            <?php endif; ?>

                            <?php if ($case['penalty_paid']): ?>
                            <tr>
                                <th>Gezahlte Strafe</th>
                                <td>
                                    <?= Helpers::formatCurrency($case['penalty_paid'], $case['currency']) ?>
                                    <?php if ($case['penalty_confirmed']): ?>
                                        <span class="tag is-success is-small">Bestätigt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Beteiligte -->
                <?php if (!empty($parties)): ?>
                <div class="box">
                    <h2 class="title is-5">👥 Beteiligte</h2>

                    <?php foreach ($groupedParties as $role => $roleParties):
                        if (empty($roleParties)) continue;

                        $roleLabels = [
                            'plaintiff' => 'Kläger',
                            'defendant' => 'Beklagter',
                            'intervenor' => 'Streithelfer',
                            'amicus' => 'Amicus Curiae'
                        ];
                    ?>
                    <div style="margin-bottom: 1.5rem;">
                        <h3 class="subtitle is-6"><?= $roleLabels[$role] ?></h3>
                        <?php foreach ($roleParties as $party): ?>
                        <div class="box" style="padding: 1rem; margin-bottom: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?= Helpers::e($party['name']) ?></strong>
                                    <?php if ($party['is_big_tech']): ?>
                                        <span class="tag is-danger is-small">Big Tech</span>
                                    <?php endif; ?>
                                    <br>
                                    <?php if ($party['law_firm']): ?>
                                        <span class="is-size-7 has-text-muted">Vertreten durch: <?= Helpers::e($party['law_firm']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($party['website']): ?>
                                    <a href="<?= Helpers::e($party['website']) ?>" target="_blank" rel="noopener" class="button is-small is-light">
                                        Website
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Beschreibung -->
                <?php if ($case['subject_matter']): ?>
                <div class="box">
                    <h2 class="title is-5">📝 Beschreibung</h2>
                    <div class="content">
                        <?= $parsedown->text($case['subject_matter']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Timeline / Updates -->
                <?php if (!empty($updates)): ?>
                <div class="box">
                    <h2 class="title is-5">📅 Timeline & Updates</h2>

                    <div class="timeline">
                        <?php foreach ($updates as $update): ?>
                        <div class="timeline-item <?= $update['is_major'] ? 'is-major' : '' ?>">
                            <div class="box" style="padding: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <div>
                                        <span class="tag is-light"><?= Helpers::updateTypeLabel($update['update_type']) ?></span>
                                        <strong style="margin-left: 0.5rem;"><?= Helpers::formatDate($update['update_date']) ?></strong>
                                        <?php if ($update['is_major']): ?>
                                            <span class="tag is-warning is-small">Wichtig</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($update['title']): ?>
                                    <h3 class="subtitle is-6" style="margin-bottom: 0.5rem;"><?= Helpers::e($update['title']) ?></h3>
                                <?php endif; ?>

                                <?php if ($update['description']): ?>
                                    <div class="content is-small">
                                        <?= $parsedown->text($update['description']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($update['source_url']): ?>
                                    <p class="is-size-7">
                                        <a href="<?= Helpers::e($update['source_url']) ?>" target="_blank" rel="noopener">
                                            🔗 Quelle
                                        </a>
                                    </p>
                                <?php endif; ?>

                                <?php if ($update['created_by_name']): ?>
                                    <p class="is-size-7 has-text-muted" style="margin-top: 0.5rem;">
                                        Eingetragen von <?= Helpers::e($update['created_by_name']) ?> am <?= Helpers::formatDate($update['created_at'], 'd.m.Y H:i') ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="column is-4">
                <!-- Rechtsgrundlagen -->
                <?php if (!empty($legalBases)): ?>
                <div class="box">
                    <h2 class="title is-6">⚖️ Rechtsgrundlagen</h2>
                    <?php
                    $grouped = [];
                    foreach ($legalBases as $lb) {
                        $grouped[$lb['category']][] = $lb;
                    }
                    ?>
                    <?php foreach ($grouped as $category => $bases): ?>
                        <div style="margin-bottom: 1rem;">
                            <?php if ($category): ?>
                                <p class="has-text-weight-bold is-size-7"><?= Helpers::e($category) ?></p>
                            <?php endif; ?>
                            <?php foreach ($bases as $base): ?>
                                <div class="tag is-link is-light" style="margin: 0.25rem; display: inline-block;">
                                    <?= Helpers::e($base['code']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Tags -->
                <?php if (!empty($tags)): ?>
                <div class="box">
                    <h2 class="title is-6">#️⃣ Tags</h2>
                    <div class="tag-cloud">
                        <?php foreach ($tags as $tag): ?>
                            <a href="/cases.php?tag=<?= urlencode($tag['name']) ?>" class="tag is-link">
                                #<?= Helpers::e($tag['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quellen -->
                <?php if (!empty($sources) || $case['source_url']): ?>
                <div class="box">
                    <h2 class="title is-6">🔗 Quellen</h2>

                    <?php if ($case['source_url']): ?>
                        <p style="margin-bottom: 1rem;">
                            <a href="<?= Helpers::e($case['source_url']) ?>" target="_blank" rel="noopener" class="button is-small is-fullwidth is-light">
                                🔗 Hauptquelle
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php foreach ($sources as $source): ?>
                        <div style="margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-color);">
                            <p class="is-size-7">
                                <span class="tag is-light is-small"><?= Helpers::updateTypeLabel($source['source_type']) ?></span>
                                <?php if ($source['date_published']): ?>
                                    <?= Helpers::formatDate($source['date_published']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($source['title']): ?>
                                <p style="margin-top: 0.25rem;">
                                    <a href="<?= Helpers::e($source['url']) ?>" target="_blank" rel="noopener">
                                        <?= Helpers::e(Helpers::truncate($source['title'], 60)) ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p style="margin-top: 0.25rem;">
                                    <a href="<?= Helpers::e($source['url']) ?>" target="_blank" rel="noopener">
                                        <?= Helpers::truncate($source['url'], 50) ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Metadaten -->
                <?php if ($case['internal_notes'] && $auth->isLoggedIn()): ?>
                <div class="box">
                    <h2 class="title is-6">📝 Interne Notizen</h2>
                    <div class="content is-small">
                        <?= nl2br(Helpers::e($case['internal_notes'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="box">
                    <h2 class="title is-6">ℹ️ Metadaten</h2>
                    <table class="table is-fullwidth is-narrow">
                        <tbody>
                            <tr>
                                <td class="is-size-7 has-text-muted">Erstellt am</td>
                                <td class="is-size-7"><?= Helpers::formatDate($case['created_at'], 'd.m.Y H:i') ?></td>
                            </tr>
                            <?php if ($case['created_by_name']): ?>
                            <tr>
                                <td class="is-size-7 has-text-muted">Erstellt von</td>
                                <td class="is-size-7"><?= Helpers::e($case['created_by_name']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="is-size-7 has-text-muted">Zuletzt geändert</td>
                                <td class="is-size-7"><?= Helpers::timeAgo($case['updated_at']) ?></td>
                            </tr>
                            <?php if ($case['updated_by_name'] && $case['updated_by_name'] !== $case['created_by_name']): ?>
                            <tr>
                                <td class="is-size-7 has-text-muted">Geändert von</td>
                                <td class="is-size-7"><?= Helpers::e($case['updated_by_name']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
