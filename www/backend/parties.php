<?php
/**
 * Parties-Verwaltung (Beteiligte)
 */

$pageTitle = 'Beteiligte verwalten';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../libraries/Database.php';
require_once __DIR__ . '/../libraries/Auth.php';
require_once __DIR__ . '/../libraries/Helpers.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';

// Neuen Party erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_party'])) {
    $name = Helpers::sanitize($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'corporation';
    $country = $_POST['country_code'] ?? null;
    $isBigTech = isset($_POST['is_big_tech']) ? 1 : 0;
    $website = $_POST['website'] ?? '';

    if (empty($name)) {
        $errors[] = 'Name ist erforderlich.';
    }

    if (empty($errors)) {
        try {
            $db->insert(
                "INSERT INTO parties (name, type, country_code, is_big_tech, website) VALUES (?, ?, ?, ?, ?)",
                [$name, $type, $country, $isBigTech, $website]
            );
            $success = "Beteiligter '{$name}' wurde erfolgreich erstellt.";
            $auth->logAction('party_created', 'party', null, "Created party: {$name}");
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Erstellen: ' . $e->getMessage();
        }
    }
}

// Party löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $party = $db->queryOne("SELECT name FROM parties WHERE id = ?", [$deleteId]);

    if ($party) {
        // Prüfen ob in Cases verwendet
        $usageCount = $db->count('case_parties', 'party_id = ?', [$deleteId]);

        if ($usageCount > 0) {
            $errors[] = "Beteiligter kann nicht gelöscht werden, da er in {$usageCount} Verfahren verwendet wird.";
        } else {
            $db->execute("DELETE FROM parties WHERE id = ?", [$deleteId]);
            $success = "Beteiligter '{$party['name']}' wurde gelöscht.";
            $auth->logAction('party_deleted', 'party', $deleteId, "Deleted party: {$party['name']}");
        }
    }
}

// Alle Parties laden
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "name LIKE ?";
    $params[] = '%' . $search . '%';
}

if ($filter === 'big_tech') {
    $where[] = "is_big_tech = 1";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$parties = $db->query("
    SELECT p.*,
           COUNT(DISTINCT cp.case_id) as case_count
    FROM parties p
    LEFT JOIN case_parties cp ON p.id = cp.party_id
    {$whereClause}
    GROUP BY p.id
    ORDER BY p.name
", $params);
?>

<div class="container">
    <section class="section">
        <nav class="breadcrumb" aria-label="breadcrumbs">
            <ul>
                <li><a href="/backend/dashboard.php">Dashboard</a></li>
                <li class="is-active"><a href="#" aria-current="page">Beteiligte</a></li>
            </ul>
        </nav>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="title">👥 Beteiligte verwalten</h1>
            <button class="button is-primary" onclick="document.getElementById('createModal').classList.add('is-active')">
                <span class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </span>
                <span>Neuer Beteiligter</span>
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

        <!-- Filter -->
        <div class="box">
            <form method="GET">
                <div class="field has-addons">
                    <div class="control is-expanded">
                        <input class="input" type="text" name="search" value="<?= Helpers::e($search) ?>" placeholder="Suche nach Name...">
                    </div>
                    <div class="control">
                        <div class="select">
                            <select name="filter" onchange="this.form.submit()">
                                <option value="">Alle</option>
                                <option value="big_tech" <?= $filter === 'big_tech' ? 'selected' : '' ?>>Nur Big Tech</option>
                            </select>
                        </div>
                    </div>
                    <div class="control">
                        <button type="submit" class="button is-primary">Suchen</button>
                    </div>
                    <?php if ($search || $filter): ?>
                    <div class="control">
                        <a href="parties.php" class="button is-light">Zurücksetzen</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Parties-Liste -->
        <div class="box">
            <p class="has-text-grey" style="margin-bottom: 1rem;">
                <strong><?= count($parties) ?></strong> Beteiligte gefunden
            </p>

            <div class="table-container">
                <table class="table is-fullwidth is-hoverable is-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Typ</th>
                            <th>Land</th>
                            <th>Big Tech</th>
                            <th>Verfahren</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parties as $party): ?>
                        <tr>
                            <td>
                                <strong><?= Helpers::e($party['name']) ?></strong>
                                <?php if ($party['website']): ?>
                                    <br><a href="<?= Helpers::e($party['website']) ?>" target="_blank" class="is-size-7">🔗 Website</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $typeLabels = [
                                    'corporation' => 'Unternehmen',
                                    'government' => 'Regierung',
                                    'authority' => 'Behörde',
                                    'individual' => 'Einzelperson',
                                    'ngo' => 'NGO',
                                    'other' => 'Sonstiges'
                                ];
                                echo Helpers::e($typeLabels[$party['type']] ?? $party['type']);
                                ?>
                            </td>
                            <td>
                                <?php if ($party['country_code']): ?>
                                    <?= Helpers::countryFlag($party['country_code']) ?>
                                    <?= Helpers::e($party['country_code']) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($party['is_big_tech']): ?>
                                    <span class="tag is-danger">Ja</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($party['case_count'] > 0): ?>
                                    <a href="/cases.php?party=<?= $party['id'] ?>" class="tag is-info">
                                        <?= $party['case_count'] ?> Verfahren
                                    </a>
                                <?php else: ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="buttons are-small">
                                    <a href="?delete=<?= $party['id'] ?>" class="button is-danger is-light" onclick="return confirm('Wirklich löschen?')">Löschen</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- Create Modal -->
<div class="modal" id="createModal">
    <div class="modal-background" onclick="document.getElementById('createModal').classList.remove('is-active')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Neuer Beteiligter</p>
            <button class="delete" aria-label="close" onclick="document.getElementById('createModal').classList.remove('is-active')"></button>
        </header>
        <form method="POST">
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Name <span style="color: red;">*</span></label>
                    <div class="control">
                        <input class="input" type="text" name="name" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Typ</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="type">
                                <option value="corporation">Unternehmen</option>
                                <option value="government">Regierung</option>
                                <option value="authority">Behörde</option>
                                <option value="individual">Einzelperson</option>
                                <option value="ngo">NGO</option>
                                <option value="other">Sonstiges</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Land</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="country_code">
                                <option value="">-- Auswählen --</option>
                                <option value="DE">🇩🇪 Deutschland</option>
                                <option value="US">🇺🇸 USA</option>
                                <option value="GB">🇬🇧 Großbritannien</option>
                                <option value="FR">🇫🇷 Frankreich</option>
                                <option value="EU">🇪🇺 EU</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="label">Website</label>
                    <div class="control">
                        <input class="input" type="url" name="website" placeholder="https://...">
                    </div>
                </div>

                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="is_big_tech">
                            Als Big Tech markieren
                        </label>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button type="submit" name="create_party" class="button is-primary">Erstellen</button>
                <button type="button" class="button" onclick="document.getElementById('createModal').classList.remove('is-active')">Abbrechen</button>
            </footer>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
