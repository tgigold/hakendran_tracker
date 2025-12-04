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
                "INSERT INTO track_parties (name, type, country_code, is_big_tech, website) VALUES (?, ?, ?, ?, ?)",
                [$name, $type, $country, $isBigTech, $website]
            );
            $success = "Beteiligter '{$name}' wurde erfolgreich erstellt.";
            $auth->logAction('party_created', 'party', null, "Created party: {$name}");
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Erstellen: ' . $e->getMessage();
        }
    }
}

// Alias hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_alias'])) {
    $partyId = intval($_POST['party_id']);
    $aliasName = Helpers::sanitize($_POST['alias_name'] ?? '');

    if (empty($aliasName)) {
        $errors[] = 'Alias-Name ist erforderlich.';
    } else {
        try {
            $db->insert(
                "INSERT INTO track_party_aliases (party_id, alias_name) VALUES (?, ?)",
                [$partyId, $aliasName]
            );
            $success = "Alias '{$aliasName}' wurde hinzugefügt.";
            $auth->logAction('alias_created', 'party', $partyId, "Added alias: {$aliasName}");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $errors[] = "Alias '{$aliasName}' existiert bereits für diesen Beteiligten.";
            } else {
                $errors[] = 'Fehler beim Hinzufügen: ' . $e->getMessage();
            }
        }
    }
}

// Alias löschen
if (isset($_GET['delete_alias']) && is_numeric($_GET['delete_alias'])) {
    $aliasId = intval($_GET['delete_alias']);
    $alias = $db->queryOne("SELECT alias_name FROM track_party_aliases WHERE id = ?", [$aliasId]);

    if ($alias) {
        $db->execute("DELETE FROM track_party_aliases WHERE id = ?", [$aliasId]);
        $success = "Alias '{$alias['alias_name']}' wurde gelöscht.";
        $auth->logAction('alias_deleted', 'party', null, "Deleted alias: {$alias['alias_name']}");
    }
}

// Party löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $party = $db->queryOne("SELECT name FROM track_parties WHERE id = ?", [$deleteId]);

    if ($party) {
        // Prüfen ob in Cases verwendet
        $usageCount = $db->count('track_case_parties', 'party_id = ?', [$deleteId]);

        if ($usageCount > 0) {
            $errors[] = "Beteiligter kann nicht gelöscht werden, da er in {$usageCount} Verfahren verwendet wird.";
        } else {
            // Aliase werden automatisch durch CASCADE gelöscht
            $db->execute("DELETE FROM track_parties WHERE id = ?", [$deleteId]);
            $success = "Beteiligter '{$party['name']}' wurde gelöscht.";
            $auth->logAction('party_deleted', 'party', $deleteId, "Deleted party: {$party['name']}");
        }
    }
}

// Alle Parties laden (mit Alias-Unterstützung)
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where = [];
$params = [];

if (!empty($search)) {
    // Suche in Name UND Aliasen
    $where[] = "(p.name LIKE ? OR EXISTS (SELECT 1 FROM track_party_aliases a WHERE a.party_id = p.id AND a.alias_name LIKE ?))";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filter === 'big_tech') {
    $where[] = "p.is_big_tech = 1";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$parties = $db->query("
    SELECT p.*,
           COUNT(DISTINCT cp.case_id) as case_count
    FROM track_parties p
    LEFT JOIN track_case_parties cp ON p.id = cp.party_id
    {$whereClause}
    GROUP BY p.id
    ORDER BY p.name
", $params);

// Aliase für alle Parties laden
foreach ($parties as &$party) {
    $party['aliases'] = $db->query(
        "SELECT id, alias_name FROM track_party_aliases WHERE party_id = ? ORDER BY alias_name",
        [$party['id']]
    );
}
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
            <h1 class="title">Beteiligte verwalten</h1>
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
                                <?php if (!empty($party['aliases'])): ?>
                                    <br><span class="is-size-7 has-text-grey">
                                        Aliase: <?php
                                        $aliasNames = array_map(function($a) { return Helpers::e($a['alias_name']); }, $party['aliases']);
                                        echo implode(', ', $aliasNames);
                                        ?>
                                    </span>
                                <?php endif; ?>
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
                                    <button class="button is-info is-light" onclick="showAliasModal(<?= $party['id'] ?>, '<?= Helpers::e($party['name']) ?>')">Aliase</button>
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
                                <?php foreach (Helpers::getCountries() as $country): ?>
                                    <option value="<?= $country['code'] ?>">
                                        <?= $country['flag'] ?> <?= Helpers::e($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
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

<!-- Alias Modal -->
<div class="modal" id="aliasModal">
    <div class="modal-background" onclick="document.getElementById('aliasModal').classList.remove('is-active')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Aliase verwalten: <span id="aliasPartyName"></span></p>
            <button class="delete" aria-label="close" onclick="document.getElementById('aliasModal').classList.remove('is-active')"></button>
        </header>
        <section class="modal-card-body">
            <div id="aliasListContainer" style="margin-bottom: 1.5rem;">
                <p class="has-text-grey">Lade Aliase...</p>
            </div>

            <form method="POST">
                <input type="hidden" name="party_id" id="aliasPartyId">
                <div class="field has-addons">
                    <div class="control is-expanded">
                        <input class="input" type="text" name="alias_name" id="aliasNameInput" placeholder="Neuer Alias-Name...">
                    </div>
                    <div class="control">
                        <button type="submit" name="add_alias" class="button is-primary">Hinzufügen</button>
                    </div>
                </div>
            </form>
        </section>
        <footer class="modal-card-foot">
            <button class="button" onclick="document.getElementById('aliasModal').classList.remove('is-active')">Schließen</button>
        </footer>
    </div>
</div>

<script>
function showAliasModal(partyId, partyName) {
    document.getElementById('aliasPartyId').value = partyId;
    document.getElementById('aliasPartyName').textContent = partyName;
    document.getElementById('aliasNameInput').value = '';

    // Aliase laden und anzeigen
    const party = <?= json_encode($parties) ?>.find(p => p.id == partyId);
    const aliasContainer = document.getElementById('aliasListContainer');

    if (party && party.aliases && party.aliases.length > 0) {
        let html = '<div class="tags">';
        party.aliases.forEach(alias => {
            html += `<span class="tag is-medium">
                ${alias.alias_name}
                <a href="?delete_alias=${alias.id}" onclick="return confirm('Alias löschen?')" class="delete is-small"></a>
            </span>`;
        });
        html += '</div>';
        aliasContainer.innerHTML = html;
    } else {
        aliasContainer.innerHTML = '<p class="has-text-grey">Keine Aliase vorhanden</p>';
    }

    document.getElementById('aliasModal').classList.add('is-active');
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
