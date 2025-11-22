<?php
/**
 * Case-Formular (Neu/Bearbeiten)
 */

$pageTitle = 'Verfahren bearbeiten';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../libraries/Database.php';
require_once __DIR__ . '/../libraries/Auth.php';
require_once __DIR__ . '/../libraries/Helpers.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$errors = [];
$success = '';

// Edit-Mode?
$caseId = intval($_GET['id'] ?? 0);
$isEdit = $caseId > 0;

// Case laden (Edit-Mode)
$case = null;
if ($isEdit) {
    $case = $db->queryOne("SELECT * FROM track_cases WHERE id = ?", [$caseId]);
    if (!$case) {
        Helpers::redirect('/backend/dashboard.php');
    }
    $pageTitle = 'Verfahren bearbeiten';
} else {
    $pageTitle = 'Neues Verfahren';
}

// Formular-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token validieren
    if (!$auth->validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ungültiges Formular-Token.';
    }

    // Validierung
    $title = Helpers::sanitize($_POST['title'] ?? '');
    if (empty($title)) {
        $errors[] = 'Titel ist erforderlich.';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Case-Daten vorbereiten
            $caseData = [
                'title' => $title,
                'case_number' => Helpers::sanitize($_POST['case_number'] ?? ''),
                'court_file' => Helpers::sanitize($_POST['court_file'] ?? ''),
                'legal_action_type' => $_POST['legal_action_type'] ?? 'civil',
                'cause_of_action' => Helpers::sanitize($_POST['cause_of_action'] ?? ''),
                'subject_matter' => $_POST['subject_matter'] ?? '',
                'status' => $_POST['status'] ?? 'ongoing',
                'date_filed' => $_POST['date_filed'] ?: null,
                'next_hearing_date' => $_POST['next_hearing_date'] ?: null,
                'date_concluded' => $_POST['date_concluded'] ?: null,
                'amount_disputed' => $_POST['amount_disputed'] ?: null,
                'currency' => $_POST['currency'] ?? 'EUR',
                'penalty_paid' => $_POST['penalty_paid'] ?: null,
                'penalty_confirmed' => isset($_POST['penalty_confirmed']) ? 1 : 0,
                'country_code' => $_POST['country_code'] ?? null,
                'court_name' => Helpers::sanitize($_POST['court_name'] ?? ''),
                'court_level' => $_POST['court_level'] ?? null,
                'source_url' => $_POST['source_url'] ?? '',
                'internal_notes' => $_POST['internal_notes'] ?? '',
                'public_visibility' => isset($_POST['public_visibility']) ? 1 : 0
            ];

            if ($isEdit) {
                // Update
                $caseData['updated_by'] = $auth->getUserId();
                $setClauses = [];
                $params = [];
                foreach ($caseData as $key => $value) {
                    $setClauses[] = "{$key} = ?";
                    $params[] = $value;
                }
                $params[] = $caseId;

                $sql = "UPDATE track_cases SET " . implode(', ', $setClauses) . " WHERE id = ?";
                $db->execute($sql, $params);

                $auth->logAction('case_updated', 'case', $caseId, "Updated case: {$title}");
                $success = 'Verfahren erfolgreich aktualisiert.';
            } else {
                // Insert
                $caseData['created_by'] = $auth->getUserId();
                $caseData['updated_by'] = $auth->getUserId();

                $fields = array_keys($caseData);
                $placeholders = array_fill(0, count($fields), '?');
                $sql = "INSERT INTO track_cases (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

                $caseId = $db->insert($sql, array_values($caseData));

                $auth->logAction('case_created', 'case', $caseId, "Created case: {$title}");
                $success = 'Verfahren erfolgreich erstellt.';
                $isEdit = true;

                // Reload case data
                $case = $db->queryOne("SELECT * FROM track_cases WHERE id = ?", [$caseId]);
            }

            // Beteiligte aktualisieren (vereinfacht - nur neue hinzufügen)
            if (!empty($_POST['new_plaintiff'])) {
                $partyId = intval($_POST['new_plaintiff']);
                $db->execute(
                    "INSERT IGNORE INTO case_parties (case_id, party_id, role) VALUES (?, ?, 'plaintiff')",
                    [$caseId, $partyId]
                );
            }

            if (!empty($_POST['new_defendant'])) {
                $partyId = intval($_POST['new_defendant']);
                $db->execute(
                    "INSERT IGNORE INTO case_parties (case_id, party_id, role) VALUES (?, ?, 'defendant')",
                    [$caseId, $partyId]
                );
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Parties für Dropdown laden
$allParties = $db->query("SELECT id, name, is_big_tech FROM track_parties ORDER BY name");

// Aktuelle Beteiligte laden (Edit-Mode)
$currentParties = [];
if ($isEdit) {
    $currentParties = $db->query("
        SELECT cp.*, p.name
        FROM track_case_parties cp
        INNER JOIN track_parties p ON cp.party_id = p.id
        WHERE cp.case_id = ?
        ORDER BY cp.role, p.name
    ", [$caseId]);
}

$csrfToken = $auth->generateCsrfToken();
?>

<div class="container">
    <section class="section">
        <nav class="breadcrumb" aria-label="breadcrumbs">
            <ul>
                <li><a href="/backend/dashboard.php">Dashboard</a></li>
                <li class="is-active"><a href="#" aria-current="page"><?= $isEdit ? 'Bearbeiten' : 'Neu' ?></a></li>
            </ul>
        </nav>

        <h1 class="title"><?= $isEdit ? '✏️ Verfahren bearbeiten' : '➕ Neues Verfahren' ?></h1>

        <?php if (!empty($errors)): ?>
            <div class="notification is-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= Helpers::e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="notification is-success">
                <?= Helpers::e($success) ?>
                <a href="/case.php?id=<?= $caseId ?>" class="button is-small is-light" style="margin-left: 1rem;">Verfahren ansehen</a>
            </div>
        <?php endif; ?>

        <form method="POST" id="caseForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <!-- Section 1: Basisdaten -->
            <div class="box">
                <h2 class="title is-5">📋 Basisdaten</h2>

                <div class="columns is-multiline">
                    <div class="column is-12">
                        <div class="field">
                            <label class="label">Titel <span style="color: red;">*</span></label>
                            <div class="control">
                                <input class="input" type="text" name="title" value="<?= Helpers::e($case['title'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Aktenzeichen</label>
                            <div class="control">
                                <input class="input" type="text" name="case_number" value="<?= Helpers::e($case['case_number'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Gerichtsakte</label>
                            <div class="control">
                                <input class="input" type="text" name="court_file" value="<?= Helpers::e($case['court_file'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Verfahrensart</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="legal_action_type">
                                        <option value="civil" <?= ($case['legal_action_type'] ?? 'civil') === 'civil' ? 'selected' : '' ?>>Zivilrecht</option>
                                        <option value="administrative" <?= ($case['legal_action_type'] ?? '') === 'administrative' ? 'selected' : '' ?>>Verwaltungsrecht</option>
                                        <option value="criminal" <?= ($case['legal_action_type'] ?? '') === 'criminal' ? 'selected' : '' ?>>Strafrecht</option>
                                        <option value="regulatory" <?= ($case['legal_action_type'] ?? '') === 'regulatory' ? 'selected' : '' ?>>Regulierungsverfahren</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Status</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="status">
                                        <option value="ongoing" <?= ($case['status'] ?? 'ongoing') === 'ongoing' ? 'selected' : '' ?>>Laufend</option>
                                        <option value="settled" <?= ($case['status'] ?? '') === 'settled' ? 'selected' : '' ?>>Vergleich</option>
                                        <option value="dismissed" <?= ($case['status'] ?? '') === 'dismissed' ? 'selected' : '' ?>>Abgewiesen</option>
                                        <option value="won_plaintiff" <?= ($case['status'] ?? '') === 'won_plaintiff' ? 'selected' : '' ?>>Kläger gewonnen</option>
                                        <option value="won_defendant" <?= ($case['status'] ?? '') === 'won_defendant' ? 'selected' : '' ?>>Beklagter gewonnen</option>
                                        <option value="appeal" <?= ($case['status'] ?? '') === 'appeal' ? 'selected' : '' ?>>Berufung</option>
                                        <option value="suspended" <?= ($case['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Ausgesetzt</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Rechtsgebiet</label>
                            <div class="control">
                                <input class="input" type="text" name="cause_of_action" value="<?= Helpers::e($case['cause_of_action'] ?? '') ?>" placeholder="z.B. Datenschutz, Kartellrecht">
                            </div>
                        </div>
                    </div>

                    <div class="column is-12">
                        <div class="field">
                            <label class="label">Kurzbeschreibung</label>
                            <div class="control">
                                <textarea class="textarea" name="subject_matter" rows="4" placeholder="Markdown wird unterstützt"><?= Helpers::e($case['subject_matter'] ?? '') ?></textarea>
                            </div>
                            <p class="help">Markdown-Formatierung wird unterstützt (**, *, Links, Listen, etc.)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Beteiligte -->
            <div class="box">
                <h2 class="title is-5">Beteiligte</h2>

                <?php if ($isEdit && !empty($currentParties)): ?>
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Rolle</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($currentParties as $party): ?>
                            <tr>
                                <td><?= Helpers::e($party['name']) ?></td>
                                <td><?= Helpers::e($party['role']) ?></td>
                                <td>
                                    <a href="?id=<?= $caseId ?>&remove_party=<?= $party['id'] ?>" class="button is-small is-danger" onclick="return confirm('Wirklich entfernen?')">Entfernen</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="columns">
                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Kläger hinzufügen</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="new_plaintiff">
                                        <option value="">-- Auswählen --</option>
                                        <?php foreach ($allParties as $party): ?>
                                            <option value="<?= $party['id'] ?>"><?= Helpers::e($party['name']) ?><?= $party['is_big_tech'] ? ' (Big Tech)' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="column is-6">
                        <div class="field">
                            <label class="label">Beklagter hinzufügen</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="new_defendant">
                                        <option value="">-- Auswählen --</option>
                                        <?php foreach ($allParties as $party): ?>
                                            <option value="<?= $party['id'] ?>"><?= Helpers::e($party['name']) ?><?= $party['is_big_tech'] ? ' (Big Tech)' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="help">Neue Beteiligte können unter <a href="/backend/parties.php" target="_blank">Beteiligte verwalten</a> angelegt werden.</p>
            </div>

            <!-- Section 3: Zuständigkeit -->
            <div class="box">
                <h2 class="title is-5">Zuständigkeit</h2>

                <div class="columns">
                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Land</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="country_code">
                                        <option value="">-- Auswählen --</option>
                                        <option value="DE" <?= ($case['country_code'] ?? '') === 'DE' ? 'selected' : '' ?>>🇩🇪 Deutschland</option>
                                        <option value="AT" <?= ($case['country_code'] ?? '') === 'AT' ? 'selected' : '' ?>>🇦🇹 Österreich</option>
                                        <option value="CH" <?= ($case['country_code'] ?? '') === 'CH' ? 'selected' : '' ?>>🇨🇭 Schweiz</option>
                                        <option value="US" <?= ($case['country_code'] ?? '') === 'US' ? 'selected' : '' ?>>🇺🇸 USA</option>
                                        <option value="GB" <?= ($case['country_code'] ?? '') === 'GB' ? 'selected' : '' ?>>🇬🇧 Großbritannien</option>
                                        <option value="FR" <?= ($case['country_code'] ?? '') === 'FR' ? 'selected' : '' ?>>🇫🇷 Frankreich</option>
                                        <option value="IT" <?= ($case['country_code'] ?? '') === 'IT' ? 'selected' : '' ?>>🇮🇹 Italien</option>
                                        <option value="ES" <?= ($case['country_code'] ?? '') === 'ES' ? 'selected' : '' ?>>🇪🇸 Spanien</option>
                                        <option value="NL" <?= ($case['country_code'] ?? '') === 'NL' ? 'selected' : '' ?>>🇳🇱 Niederlande</option>
                                        <option value="BE" <?= ($case['country_code'] ?? '') === 'BE' ? 'selected' : '' ?>>🇧🇪 Belgien</option>
                                        <option value="EU" <?= ($case['country_code'] ?? '') === 'EU' ? 'selected' : '' ?>>🇪🇺 EU</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="column is-5">
                        <div class="field">
                            <label class="label">Gericht</label>
                            <div class="control">
                                <input class="input" type="text" name="court_name" value="<?= Helpers::e($case['court_name'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="column is-3">
                        <div class="field">
                            <label class="label">Gerichtsebene</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="court_level">
                                        <option value="">-- Auswählen --</option>
                                        <option value="district" <?= ($case['court_level'] ?? '') === 'district' ? 'selected' : '' ?>>Amtsgericht</option>
                                        <option value="regional" <?= ($case['court_level'] ?? '') === 'regional' ? 'selected' : '' ?>>Landgericht</option>
                                        <option value="federal" <?= ($case['court_level'] ?? '') === 'federal' ? 'selected' : '' ?>>Bundesgericht</option>
                                        <option value="supreme" <?= ($case['court_level'] ?? '') === 'supreme' ? 'selected' : '' ?>>Höchstgericht</option>
                                        <option value="eu" <?= ($case['court_level'] ?? '') === 'eu' ? 'selected' : '' ?>>EU-Gericht</option>
                                        <option value="administrative" <?= ($case['court_level'] ?? '') === 'administrative' ? 'selected' : '' ?>>Verwaltungsgericht</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Finanzielle Details -->
            <div class="box">
                <h2 class="title is-5">💰 Finanzielle Details</h2>

                <div class="columns">
                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Streitwert</label>
                            <div class="control">
                                <input class="input" type="number" name="amount_disputed" step="0.01" value="<?= $case['amount_disputed'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="column is-2">
                        <div class="field">
                            <label class="label">Währung</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="currency">
                                        <option value="EUR" <?= ($case['currency'] ?? 'EUR') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                        <option value="USD" <?= ($case['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                                        <option value="GBP" <?= ($case['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP</option>
                                        <option value="CHF" <?= ($case['currency'] ?? '') === 'CHF' ? 'selected' : '' ?>>CHF</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Gezahlte Strafe</label>
                            <div class="control">
                                <input class="input" type="number" name="penalty_paid" step="0.01" value="<?= $case['penalty_paid'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="column is-2">
                        <div class="field">
                            <label class="label">&nbsp;</label>
                            <div class="control">
                                <label class="checkbox">
                                    <input type="checkbox" name="penalty_confirmed" <?= ($case['penalty_confirmed'] ?? false) ? 'checked' : '' ?>>
                                    Bestätigt
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 5: Termine -->
            <div class="box">
                <h2 class="title is-5">📅 Termine</h2>

                <div class="columns">
                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Klageeinreichung</label>
                            <div class="control">
                                <input class="input" type="date" name="date_filed" value="<?= $case['date_filed'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Nächster Anhörungstermin</label>
                            <div class="control">
                                <input class="input" type="date" name="next_hearing_date" value="<?= $case['next_hearing_date'] ?? '' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="column is-4">
                        <div class="field">
                            <label class="label">Abschlussdatum</label>
                            <div class="control">
                                <input class="input" type="date" name="date_concluded" value="<?= $case['date_concluded'] ?? '' ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 6: Kontext -->
            <div class="box">
                <h2 class="title is-5">🔗 Kontext & Quellen</h2>

                <div class="field">
                    <label class="label">Hauptquelle (URL)</label>
                    <div class="control">
                        <input class="input" type="url" name="source_url" value="<?= Helpers::e($case['source_url'] ?? '') ?>" placeholder="https://...">
                    </div>
                </div>

                <div class="field">
                    <label class="label">Interne Notizen</label>
                    <div class="control">
                        <textarea class="textarea" name="internal_notes" rows="3"><?= Helpers::e($case['internal_notes'] ?? '') ?></textarea>
                    </div>
                    <p class="help">Diese Notizen sind nur für eingeloggte Benutzer sichtbar</p>
                </div>

                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="public_visibility" <?= ($case['public_visibility'] ?? true) ? 'checked' : '' ?>>
                            Öffentlich sichtbar
                        </label>
                    </div>
                    <p class="help">Wenn deaktiviert, ist das Verfahren nur für eingeloggte Benutzer sichtbar</p>
                </div>
            </div>

            <!-- Buttons -->
            <div class="field is-grouped">
                <div class="control">
                    <button type="submit" class="button is-primary is-large">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                        </span>
                        <span>Speichern</span>
                    </button>
                </div>
                <div class="control">
                    <a href="/backend/dashboard.php" class="button is-light is-large">Abbrechen</a>
                </div>
                <?php if ($isEdit): ?>
                <div class="control">
                    <a href="/case.php?id=<?= $caseId ?>" class="button is-info is-large">Ansehen</a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
