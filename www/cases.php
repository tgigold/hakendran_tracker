<?php
/**
 * Verfahrens-Liste mit Filter und Suche
 */

$pageTitle = 'Verfahren';
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/libraries/Database.php';
require_once __DIR__ . '/libraries/Helpers.php';

$db = Database::getInstance();

// Parameter aus URL
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$country = $_GET['country'] ?? '';
$party = $_GET['party'] ?? '';
$cause = $_GET['cause'] ?? '';
$tag = $_GET['tag'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'date_filed_desc';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = max(10, min(100, intval($_GET['per_page'] ?? 20)));

// Export-Funktion
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportData = getCases(1, 10000, false); // Alle Daten für Export
    $csvData = [];

    foreach ($exportData['cases'] as $case) {
        $parties = parseParties($case['parties']);
        $csvData[] = [
            'ID' => $case['id'],
            'Titel' => $case['title'],
            'Aktenzeichen' => $case['case_number'],
            'Kläger' => implode(', ', $parties['plaintiffs']),
            'Beklagter' => implode(', ', $parties['defendants']),
            'Status' => Helpers::statusBadge($case['status']),
            'Land' => Helpers::countryName($case['country_code']),
            'Gericht' => $case['court_name'],
            'Rechtsgebiet' => Helpers::legalActionTypeLabel($case['legal_action_type']),
            'Datum eingereicht' => Helpers::formatDate($case['date_filed']),
            'Nächste Anhörung' => Helpers::formatDate($case['next_hearing_date']),
            'Streitwert' => $case['amount_disputed'] ? Helpers::formatCurrency($case['amount_disputed'], $case['currency']) : '',
        ];
    }

    Helpers::generateCsv($csvData, 'verfahren_' . date('Y-m-d') . '.csv');
}

// Daten laden
$result = getCases($page, $perPage);
$cases = $result['cases'];
$totalCases = $result['total'];
$totalPages = ceil($totalCases / $perPage);

// Filter-Optionen laden
$bigTechParties = $db->query("SELECT id, name FROM track_parties WHERE is_big_tech = 1 ORDER BY name");
$countries = $db->query("SELECT DISTINCT country_code FROM track_cases WHERE country_code IS NOT NULL AND country_code != '' AND public_visibility = 1 ORDER BY country_code");
$causes = $db->query("SELECT DISTINCT cause_of_action FROM track_cases WHERE cause_of_action IS NOT NULL AND cause_of_action != '' AND public_visibility = 1 ORDER BY cause_of_action");
$tags = $db->query("SELECT DISTINCT t.id, t.name FROM track_tags t INNER JOIN track_case_tags ct ON t.id = ct.tag_id ORDER BY t.name");

/**
 * Cases laden mit Filter
 */
function getCases($page, $perPage, $paginate = true) {
    global $db, $search, $status, $country, $party, $cause, $tag, $dateFrom, $dateTo, $sort;

    $offset = ($page - 1) * $perPage;
    $where = ['c.public_visibility = 1'];
    $params = [];

    // Volltext-Suche
    if (!empty($search)) {
        $where[] = "(c.title LIKE ? OR c.subject_matter LIKE ? OR c.case_number LIKE ? OR c.court_name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Status-Filter
    if (!empty($status)) {
        $where[] = "c.status = ?";
        $params[] = $status;
    }

    // Land-Filter
    if (!empty($country)) {
        $where[] = "c.country_code = ?";
        $params[] = $country;
    }

    // Party-Filter (Big Tech)
    if (!empty($party)) {
        $where[] = "EXISTS (SELECT 1 FROM track_case_parties cp WHERE cp.case_id = c.id AND cp.party_id = ?)";
        $params[] = $party;
    }

    // Rechtsgebiet-Filter
    if (!empty($cause)) {
        $where[] = "c.cause_of_action = ?";
        $params[] = $cause;
    }

    // Tag-Filter
    if (!empty($tag)) {
        if (is_numeric($tag)) {
            $where[] = "EXISTS (SELECT 1 FROM track_case_tags ct WHERE ct.case_id = c.id AND ct.tag_id = ?)";
            $params[] = $tag;
        } else {
            $where[] = "EXISTS (SELECT 1 FROM track_case_tags ct INNER JOIN track_tags t ON ct.tag_id = t.id WHERE ct.case_id = c.id AND t.name = ?)";
            $params[] = $tag;
        }
    }

    // Datumsbereich
    if (!empty($dateFrom)) {
        $where[] = "c.date_filed >= ?";
        $params[] = $dateFrom;
    }
    if (!empty($dateTo)) {
        $where[] = "c.date_filed <= ?";
        $params[] = $dateTo;
    }

    $whereClause = implode(' AND ', $where);

    // Sortierung
    $orderBy = match($sort) {
        'date_filed_asc' => 'c.date_filed ASC',
        'date_filed_desc' => 'c.date_filed DESC',
        'amount_asc' => 'c.amount_disputed ASC',
        'amount_desc' => 'c.amount_disputed DESC',
        'title_asc' => 'c.title ASC',
        'title_desc' => 'c.title DESC',
        'updated_asc' => 'c.updated_at ASC',
        'updated_desc' => 'c.updated_at DESC',
        default => 'c.date_filed DESC'
    };

    // Total Count
    $totalSql = "SELECT COUNT(DISTINCT c.id) as total FROM track_cases c WHERE {$whereClause}";
    $totalResult = $db->queryOne($totalSql, $params);
    $total = $totalResult['total'];

    // Cases laden
    $sql = "
        SELECT c.*,
               GROUP_CONCAT(DISTINCT CONCAT(p.name, ':', cp.role) SEPARATOR '|') as parties,
               GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as tags
        FROM track_cases c
        LEFT JOIN track_case_parties cp ON c.id = cp.case_id
        LEFT JOIN track_parties p ON cp.party_id = p.id
        LEFT JOIN track_case_tags ct ON c.id = ct.case_id
        LEFT JOIN track_tags t ON ct.tag_id = t.id
        WHERE {$whereClause}
        GROUP BY c.id
        ORDER BY {$orderBy}
    ";

    if ($paginate) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
    }

    $cases = $db->query($sql, $params);

    return [
        'cases' => $cases,
        'total' => $total
    ];
}

/**
 * Parteien parsen
 */
function parseParties($partiesString) {
    if (empty($partiesString)) {
        return ['plaintiffs' => [], 'defendants' => []];
    }

    $parties = ['plaintiffs' => [], 'defendants' => []];
    $items = explode('|', $partiesString);

    foreach ($items as $item) {
        if (strpos($item, ':') === false) continue;
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

<div class="container">
    <section class="section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="title">Alle Verfahren</h1>
            <div class="buttons">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="button is-light">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </span>
                    <span>CSV Export</span>
                </a>
            </div>
        </div>

        <!-- Filter-Panel -->
        <div class="filter-panel">
            <form method="GET" action="cases.php" id="filterForm">
                <div class="columns is-multiline">
                    <!-- Suche -->
                    <div class="column is-12">
                        <div class="field">
                            <label class="label">Volltext-Suche</label>
                            <div class="control has-icons-left">
                                <input class="input" type="text" name="search" value="<?= Helpers::e($search) ?>" placeholder="Titel, Aktenzeichen, Gericht..." id="searchInput">
                                <span class="icon is-left">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.35-4.35"></path>
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Unternehmen -->
                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Unternehmen</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="party" onchange="this.form.submit()">
                                        <option value="">Alle</option>
                                        <?php foreach ($bigTechParties as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= $party == $p['id'] ? 'selected' : '' ?>>
                                                <?= Helpers::e($p['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Status</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="">Alle</option>
                                        <option value="ongoing" <?= $status === 'ongoing' ? 'selected' : '' ?>>Laufend</option>
                                        <option value="settled" <?= $status === 'settled' ? 'selected' : '' ?>>Vergleich</option>
                                        <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Abgewiesen</option>
                                        <option value="won_plaintiff" <?= $status === 'won_plaintiff' ? 'selected' : '' ?>>Kläger gewonnen</option>
                                        <option value="won_defendant" <?= $status === 'won_defendant' ? 'selected' : '' ?>>Beklagter gewonnen</option>
                                        <option value="appeal" <?= $status === 'appeal' ? 'selected' : '' ?>>Berufung</option>
                                        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Ausgesetzt</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Land -->
                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Land</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="country" onchange="this.form.submit()">
                                        <option value="">Alle</option>
                                        <?php foreach ($countries as $c): ?>
                                            <option value="<?= $c['country_code'] ?>" <?= $country === $c['country_code'] ? 'selected' : '' ?>>
                                                <?= Helpers::countryFlag($c['country_code']) ?> <?= Helpers::countryName($c['country_code']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rechtsgebiet -->
                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Rechtsgebiet</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="cause" onchange="this.form.submit()">
                                        <option value="">Alle</option>
                                        <?php foreach ($causes as $c): ?>
                                            <option value="<?= Helpers::e($c['cause_of_action']) ?>" <?= $cause === $c['cause_of_action'] ? 'selected' : '' ?>>
                                                <?= Helpers::e($c['cause_of_action']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Datumsbereich -->
                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Von Datum</label>
                            <div class="control">
                                <input class="input" type="date" name="date_from" value="<?= Helpers::e($dateFrom) ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                    </div>

                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Bis Datum</label>
                            <div class="control">
                                <input class="input" type="date" name="date_to" value="<?= Helpers::e($dateTo) ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                    </div>

                    <!-- Sortierung -->
                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Sortierung</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="sort" onchange="this.form.submit()">
                                        <option value="date_filed_desc" <?= $sort === 'date_filed_desc' ? 'selected' : '' ?>>Datum (neueste zuerst)</option>
                                        <option value="date_filed_asc" <?= $sort === 'date_filed_asc' ? 'selected' : '' ?>>Datum (älteste zuerst)</option>
                                        <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>Streitwert (höchste zuerst)</option>
                                        <option value="amount_asc" <?= $sort === 'amount_asc' ? 'selected' : '' ?>>Streitwert (niedrigste zuerst)</option>
                                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Titel (A-Z)</option>
                                        <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Titel (Z-A)</option>
                                        <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Zuletzt aktualisiert</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pro Seite -->
                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Pro Seite</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="per_page" onchange="this.form.submit()">
                                        <option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10</option>
                                        <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20</option>
                                        <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-grouped">
                    <div class="control">
                        <button type="submit" class="button is-primary">Filter anwenden</button>
                    </div>
                    <div class="control">
                        <a href="cases.php" class="button is-light">Filter zurücksetzen</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Ergebnis-Übersicht -->
        <div style="margin-bottom: 1rem;">
            <p class="has-text-grey">
                <strong><?= number_format($totalCases, 0, ',', '.') ?></strong> Verfahren gefunden
                <?php if ($totalPages > 1): ?>
                    (Seite <?= $page ?> von <?= $totalPages ?>)
                <?php endif; ?>
            </p>
        </div>

        <!-- Cases-Liste als Cards -->
        <?php if (empty($cases)): ?>
            <div class="notification is-warning">
                Keine Verfahren gefunden. Versuchen Sie, die Filter anzupassen.
            </div>
        <?php else: ?>
            <div class="columns is-multiline">
                <?php foreach ($cases as $case):
                    $parties = parseParties($case['parties']);
                    $vs = '';
                    if (!empty($parties['plaintiffs']) && !empty($parties['defendants'])) {
                        $vs = Helpers::truncate($parties['plaintiffs'][0], 30) . ' vs ' . Helpers::truncate($parties['defendants'][0], 30);
                    }
                ?>
                <div class="column is-6">
                    <div class="card case-card" style="height: 100%;">
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
                                    <?= Helpers::e($case['title']) ?>
                                </a>
                            </div>

                            <?php if ($vs): ?>
                            <div class="case-card-meta">
                                <?= Helpers::e($vs) ?>
                            </div>
                            <?php endif; ?>

                            <div class="case-card-meta" style="margin-top: 0.75rem;">
                                <?php if ($case['court_name']): ?>
                                    <?= Helpers::e(Helpers::truncate($case['court_name'], 50)) ?><br>
                                <?php endif; ?>
                                <?php if ($case['date_filed']): ?>
                                    📄 Eingereicht: <?= Helpers::formatDate($case['date_filed']) ?><br>
                                <?php endif; ?>
                                <?php if ($case['next_hearing_date']): ?>
                                    📅 Nächste Anhörung: <?= Helpers::formatDate($case['next_hearing_date']) ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($case['amount_disputed']): ?>
                            <div style="margin-top: 0.75rem;">
                                <span class="tag is-warning is-medium">
                                    <?= Helpers::formatCurrency($case['amount_disputed'], $case['currency']) ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if ($case['tags']): ?>
                            <div style="margin-top: 0.75rem;">
                                <?php foreach (explode(', ', $case['tags']) as $tagName): ?>
                                    <span class="tag is-light is-small">#<?= Helpers::e($tagName) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="pagination is-centered" role="navigation" aria-label="pagination" style="margin-top: 2rem;">
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $baseUrl = 'cases.php?' . http_build_query($queryParams);
                $separator = empty($queryParams) ? '' : '&';
                ?>

                <?php if ($page > 1): ?>
                    <a href="<?= $baseUrl . $separator ?>page=<?= $page - 1 ?>" class="pagination-previous">Vorherige</a>
                <?php else: ?>
                    <a class="pagination-previous" disabled>Vorherige</a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= $baseUrl . $separator ?>page=<?= $page + 1 ?>" class="pagination-next">Nächste</a>
                <?php else: ?>
                    <a class="pagination-next" disabled>Nächste</a>
                <?php endif; ?>

                <ul class="pagination-list">
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);

                    if ($start > 1): ?>
                        <li><a href="<?= $baseUrl . $separator ?>page=1" class="pagination-link">1</a></li>
                        <?php if ($start > 2): ?>
                            <li><span class="pagination-ellipsis">&hellip;</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li>
                            <a href="<?= $baseUrl . $separator ?>page=<?= $i ?>"
                               class="pagination-link <?= $i === $page ? 'is-current' : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li><span class="pagination-ellipsis">&hellip;</span></li>
                        <?php endif; ?>
                        <li><a href="<?= $baseUrl . $separator ?>page=<?= $totalPages ?>" class="pagination-link"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<!-- Live-Suche mit Debounce -->
<script>
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');

    if (searchInput && filterForm) {
        const debouncedSubmit = App.debounce(() => {
            filterForm.submit();
        }, 500);

        searchInput.addEventListener('input', debouncedSubmit);
    }
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
