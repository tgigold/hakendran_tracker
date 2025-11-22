<?php
/**
 * Startseite - Hakendran Gerichtstracker
 */

$pageTitle = 'Startseite';
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/libraries/Database.php';
require_once __DIR__ . '/libraries/Helpers.php';

$db = Database::getInstance();

// Anstehende Anhörungen (nächste 30 Tage)
$upcomingHearings = $db->query("
    SELECT c.*,
           GROUP_CONCAT(DISTINCT CONCAT(p.name, ':', cp.role) SEPARATOR '|') as parties
    FROM cases c
    LEFT JOIN case_parties cp ON c.id = cp.case_id
    LEFT JOIN parties p ON cp.party_id = p.id
    WHERE c.next_hearing_date >= CURDATE()
      AND c.next_hearing_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      AND c.public_visibility = 1
    GROUP BY c.id
    ORDER BY c.next_hearing_date ASC
    LIMIT 5
");

// Neueste Verfahren
$latestCases = $db->query("
    SELECT c.*,
           GROUP_CONCAT(DISTINCT CONCAT(p.name, ':', cp.role) SEPARATOR '|') as parties
    FROM cases c
    LEFT JOIN case_parties cp ON c.id = cp.case_id
    LEFT JOIN parties p ON cp.party_id = p.id
    WHERE c.public_visibility = 1
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 6
");

// Beliebte Tags
$popularTags = $db->query("
    SELECT t.*, COUNT(ct.case_id) as case_count
    FROM tags t
    INNER JOIN case_tags ct ON t.id = ct.tag_id
    GROUP BY t.id
    ORDER BY case_count DESC
    LIMIT 20
");

// Statistiken
$stats = [
    'total_cases' => $db->count('cases', 'public_visibility = 1'),
    'ongoing_cases' => $db->count('cases', 'status = ? AND public_visibility = 1', ['ongoing']),
    'total_parties' => $db->count('parties'),
    'big_tech_count' => $db->count('parties', 'is_big_tech = 1')
];

/**
 * Hilfsfunktion: Parteien parsen
 */
function parseParties($partiesString) {
    if (empty($partiesString)) {
        return ['plaintiffs' => [], 'defendants' => []];
    }

    $parties = ['plaintiffs' => [], 'defendants' => []];
    $items = explode('|', $partiesString);

    foreach ($items as $item) {
        list($name, $role) = explode(':', $item);
        if ($role === 'plaintiff') {
            $parties['plaintiffs'][] = $name;
        } elseif ($role === 'defendant') {
            $parties['defendants'][] = $name;
        }
    }

    return $parties;
}
?>

<!-- Hero mit Suchfeld -->
<div class="search-hero">
    <div class="container">
        <h1 class="title is-2 has-text-centered">
            🏛️ Big Tech Verfahrenstracker
        </h1>
        <p class="subtitle has-text-centered" style="color: rgba(255,255,255,0.9); margin-bottom: 2rem;">
            Durchsuchbare Datenbank aller Gerichtsverfahren gegen große Tech-Konzerne<br>
            Kartellrecht • DSA/DMA • Datenschutz • Behördenverfahren
        </p>

        <div class="field">
            <div class="control has-icons-left">
                <input class="input is-large" type="text" placeholder="Verfahren durchsuchen..." id="globalSearch">
                <span class="icon is-left">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </span>
            </div>
            <p class="help" style="color: rgba(255,255,255,0.8); margin-top: 0.5rem;">
                Suche nach Titel, Unternehmen, Gericht oder Rechtsgebiet
            </p>
        </div>
    </div>
</div>

<div class="container">
    <!-- Statistik-Übersicht -->
    <div class="columns is-multiline" style="margin-bottom: 2rem;">
        <div class="column is-3">
            <div class="box stat-card">
                <div class="stat-value"><?= $stats['total_cases'] ?></div>
                <div class="stat-label">Verfahren</div>
            </div>
        </div>
        <div class="column is-3">
            <div class="box stat-card">
                <div class="stat-value"><?= $stats['ongoing_cases'] ?></div>
                <div class="stat-label">Laufend</div>
            </div>
        </div>
        <div class="column is-3">
            <div class="box stat-card">
                <div class="stat-value"><?= count($upcomingHearings) ?></div>
                <div class="stat-label">Anstehend</div>
            </div>
        </div>
        <div class="column is-3">
            <div class="box stat-card">
                <div class="stat-value"><?= $stats['big_tech_count'] ?></div>
                <div class="stat-label">Big Tech</div>
            </div>
        </div>
    </div>

    <!-- Anstehende Anhörungen -->
    <?php if (!empty($upcomingHearings)): ?>
    <section class="section" style="padding-top: 0;">
        <h2 class="title is-4">📅 Anstehende Anhörungen (30 Tage)</h2>

        <div class="box">
            <div class="table-container">
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Verfahren</th>
                            <th>Beteiligte</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingHearings as $case):
                            $parties = parseParties($case['parties']);
                        ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <strong><?= Helpers::formatDate($case['next_hearing_date']) ?></strong>
                            </td>
                            <td>
                                <a href="/case.php?id=<?= $case['id'] ?>">
                                    <?= Helpers::e($case['title']) ?>
                                </a>
                                <br>
                                <span class="is-size-7 has-text-muted">
                                    <?= Helpers::countryFlag($case['country_code']) ?>
                                    <?= Helpers::e($case['court_name']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($parties['plaintiffs'])): ?>
                                    <div class="is-size-7">
                                        <strong>Kläger:</strong> <?= Helpers::e(implode(', ', $parties['plaintiffs'])) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($parties['defendants'])): ?>
                                    <div class="is-size-7">
                                        <strong>Beklagter:</strong> <?= Helpers::e(implode(', ', $parties['defendants'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= Helpers::statusBadge($case['status']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Neueste Verfahren -->
    <section class="section" style="padding-top: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 class="title is-4">⚖️ Neueste Verfahren</h2>
            <a href="/cases.php" class="button is-primary">Alle Verfahren</a>
        </div>

        <div class="columns is-multiline">
            <?php foreach ($latestCases as $case):
                $parties = parseParties($case['parties']);
                $vs = '';
                if (!empty($parties['plaintiffs']) && !empty($parties['defendants'])) {
                    $vs = Helpers::truncate($parties['plaintiffs'][0], 30) . ' vs ' . Helpers::truncate($parties['defendants'][0], 30);
                }
            ?>
            <div class="column is-4">
                <div class="card case-card">
                    <div class="card-content">
                        <div class="case-card-header">
                            <div>
                                <?= Helpers::statusBadge($case['status']) ?>
                            </div>
                            <div>
                                <?php if ($case['country_code']): ?>
                                    <span class="tag is-light"><?= Helpers::countryFlag($case['country_code']) ?> <?= Helpers::e($case['country_code']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="case-card-title">
                            <a href="/case.php?id=<?= $case['id'] ?>">
                                <?= Helpers::e(Helpers::truncate($case['title'], 80)) ?>
                            </a>
                        </div>

                        <?php if ($vs): ?>
                        <div class="case-card-meta">
                            <?= Helpers::e($vs) ?>
                        </div>
                        <?php endif; ?>

                        <div class="case-card-meta" style="margin-top: 0.5rem;">
                            <?php if ($case['date_filed']): ?>
                                📄 Eingereicht: <?= Helpers::formatDate($case['date_filed']) ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($case['amount_disputed']): ?>
                        <div style="margin-top: 0.5rem;">
                            <span class="tag is-warning">
                                💰 <?= Helpers::formatCurrency($case['amount_disputed'], $case['currency']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Tag-Wolke -->
    <?php if (!empty($popularTags)): ?>
    <section class="section" style="padding-top: 0; padding-bottom: 3rem;">
        <h2 class="title is-4">#️⃣ Themen & Tags</h2>

        <div class="box">
            <div class="tag-cloud">
                <?php foreach ($popularTags as $tag): ?>
                    <a href="/cases.php?tag=<?= urlencode($tag['name']) ?>" class="tag is-medium is-link">
                        #<?= Helpers::e($tag['name']) ?>
                        <span class="tag is-rounded" style="margin-left: 0.5rem;"><?= $tag['case_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Suche-JavaScript -->
<script>
    const searchInput = document.getElementById('globalSearch');

    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    window.location.href = '/cases.php?search=' + encodeURIComponent(query);
                }
            }
        });
    }
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
