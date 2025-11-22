<?php
/**
 * Statistik-Seite mit Visualisierungen
 */

$pageTitle = 'Statistiken';
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/libraries/Database.php';
require_once __DIR__ . '/libraries/Helpers.php';

$db = Database::getInstance();

// Übersichts-Statistiken
$stats = [
    'total_cases' => $db->count('track_cases', 'public_visibility = 1'),
    'ongoing' => $db->count('track_cases', 'status = ? AND public_visibility = 1', ['ongoing']),
    'settled' => $db->count('track_cases', 'status = ? AND public_visibility = 1', ['settled']),
    'dismissed' => $db->count('track_cases', 'status = ? AND public_visibility = 1', ['dismissed']),
    'total_parties' => $db->count('track_parties'),
    'big_tech' => $db->count('track_parties', 'is_big_tech = 1'),
];

// Streitwert-Summen
$amountStats = $db->queryOne("
    SELECT
        SUM(amount_disputed) as total_disputed,
        SUM(penalty_paid) as total_penalties,
        AVG(amount_disputed) as avg_disputed
    FROM track_cases
    WHERE public_visibility = 1
");

// Cases pro Status
$casesByStatus = $db->query("
    SELECT status, COUNT(*) as count
    FROM track_cases
    WHERE public_visibility = 1
    GROUP BY status
    ORDER BY count DESC
");

// Cases pro Land (Top 10)
$casesByCountry = $db->query("
    SELECT country_code, COUNT(*) as count
    FROM track_cases
    WHERE public_visibility = 1 AND country_code IS NOT NULL
    GROUP BY country_code
    ORDER BY count DESC
    LIMIT 10
");

// Cases pro Jahr
$casesByYear = $db->query("
    SELECT YEAR(date_filed) as year, COUNT(*) as count
    FROM track_cases
    WHERE public_visibility = 1 AND date_filed IS NOT NULL
    GROUP BY YEAR(date_filed)
    ORDER BY year ASC
");

// Top Big Tech Unternehmen (nach Anzahl Verfahren)
$topCompanies = $db->query("
    SELECT p.name, COUNT(DISTINCT cp.case_id) as case_count
    FROM track_parties p
    INNER JOIN track_case_parties cp ON p.id = cp.party_id
    WHERE p.is_big_tech = 1
    GROUP BY p.id
    ORDER BY case_count DESC
    LIMIT 10
");

// Top Rechtsgebiete
$topCauses = $db->query("
    SELECT cause_of_action, COUNT(*) as count
    FROM track_cases
    WHERE public_visibility = 1 AND cause_of_action IS NOT NULL AND cause_of_action != ''
    GROUP BY cause_of_action
    ORDER BY count DESC
    LIMIT 8
");

// Verfahrensarten
$actionTypes = $db->query("
    SELECT legal_action_type, COUNT(*) as count
    FROM track_cases
    WHERE public_visibility = 1
    GROUP BY legal_action_type
    ORDER BY count DESC
");

// Höchste Streitwerte
$topDisputes = $db->query("
    SELECT title, amount_disputed, currency, country_code, status
    FROM track_cases
    WHERE public_visibility = 1 AND amount_disputed > 0
    ORDER BY amount_disputed DESC
    LIMIT 5
");
?>

<div class="container">
    <section class="section">
        <h1 class="title">Statistiken & Analysen</h1>
        <p class="subtitle">Auswertungen aller erfassten Gerichtsverfahren gegen Big Tech</p>

        <!-- Übersichts-Cards -->
        <div class="columns is-multiline" style="margin-bottom: 2rem;">
            <div class="column is-3">
                <div class="box stat-card">
                    <div class="stat-value"><?= number_format($stats['total_cases'], 0, ',', '.') ?></div>
                    <div class="stat-label">Gesamt-Verfahren</div>
                </div>
            </div>
            <div class="column is-3">
                <div class="box stat-card">
                    <div class="stat-value" style="color: #3B82F6;"><?= number_format($stats['ongoing'], 0, ',', '.') ?></div>
                    <div class="stat-label">Laufend</div>
                </div>
            </div>
            <div class="column is-3">
                <div class="box stat-card">
                    <div class="stat-value" style="color: #10B981;"><?= number_format($stats['settled'], 0, ',', '.') ?></div>
                    <div class="stat-label">Vergleiche</div>
                </div>
            </div>
            <div class="column is-3">
                <div class="box stat-card">
                    <div class="stat-value" style="color: #EF4444;"><?= number_format($stats['big_tech'], 0, ',', '.') ?></div>
                    <div class="stat-label">Big Tech Unternehmen</div>
                </div>
            </div>
        </div>

        <!-- Streitwerte -->
        <div class="columns is-multiline" style="margin-bottom: 2rem;">
            <div class="column is-4">
                <div class="box stat-card">
                    <div class="stat-value" style="font-size: 1.8rem;">
                        <?= $amountStats['total_disputed'] ? Helpers::formatCurrency($amountStats['total_disputed'], 'EUR') : '-' ?>
                    </div>
                    <div class="stat-label">Gesamt-Streitwert</div>
                </div>
            </div>
            <div class="column is-4">
                <div class="box stat-card">
                    <div class="stat-value" style="font-size: 1.8rem;">
                        <?= $amountStats['total_penalties'] ? Helpers::formatCurrency($amountStats['total_penalties'], 'EUR') : '-' ?>
                    </div>
                    <div class="stat-label">Gezahlte Strafen</div>
                </div>
            </div>
            <div class="column is-4">
                <div class="box stat-card">
                    <div class="stat-value" style="font-size: 1.8rem;">
                        <?= $amountStats['avg_disputed'] ? Helpers::formatCurrency($amountStats['avg_disputed'], 'EUR') : '-' ?>
                    </div>
                    <div class="stat-label">Ø Streitwert</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="columns is-multiline">
            <!-- Cases pro Status -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Verfahren nach Status</h2>
                    <canvas id="statusChart" height="300"></canvas>
                </div>
            </div>

            <!-- Cases pro Land -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Verfahren nach Land (Top 10)</h2>
                    <canvas id="countryChart" height="300"></canvas>
                </div>
            </div>

            <!-- Cases pro Jahr -->
            <div class="column is-12">
                <div class="box">
                    <h2 class="title is-5">Verfahren nach Jahr</h2>
                    <canvas id="yearChart" height="150"></canvas>
                </div>
            </div>

            <!-- Top Unternehmen -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Top Big Tech Unternehmen</h2>
                    <canvas id="companiesChart" height="300"></canvas>
                </div>
            </div>

            <!-- Rechtsgebiete -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Top Rechtsgebiete</h2>
                    <canvas id="causesChart" height="300"></canvas>
                </div>
            </div>

            <!-- Verfahrensarten -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">Verfahrensarten</h2>
                    <canvas id="actionTypesChart" height="250"></canvas>
                </div>
            </div>

            <!-- Höchste Streitwerte -->
            <div class="column is-6">
                <div class="box">
                    <h2 class="title is-5">💰 Höchste Streitwerte</h2>
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>Verfahren</th>
                                <th>Betrag</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topDisputes as $dispute): ?>
                            <tr>
                                <td>
                                    <?= Helpers::countryFlag($dispute['country_code']) ?>
                                    <?= Helpers::e(Helpers::truncate($dispute['title'], 40)) ?>
                                </td>
                                <td><strong><?= Helpers::formatCurrency($dispute['amount_disputed'], $dispute['currency']) ?></strong></td>
                                <td><?= Helpers::statusBadge($dispute['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.51.0/dist/chart.umd.min.js"></script>
<script>
// Theme-aware Colors
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const textColor = isDark ? '#F9FAFB' : '#1F2937';
const gridColor = isDark ? '#374151' : '#E5E7EB';

const chartColors = {
    primary: '#8B5CF6',
    secondary: '#EC4899',
    success: '#10B981',
    warning: '#F59E0B',
    danger: '#EF4444',
    info: '#3B82F6',
    light: '#9CA3AF',
    purple: '#A78BFA',
    blue: '#60A5FA',
    green: '#34D399',
    yellow: '#FCD34D',
    red: '#F87171',
    pink: '#F472B6',
    indigo: '#818CF8',
    teal: '#2DD4BF'
};

const defaultOptions = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            labels: {
                color: textColor
            }
        }
    },
    scales: {
        y: {
            ticks: { color: textColor },
            grid: { color: gridColor }
        },
        x: {
            ticks: { color: textColor },
            grid: { color: gridColor }
        }
    }
};

// 1. Status Chart (Pie)
const statusChart = new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: [
            <?php foreach ($casesByStatus as $item): ?>
                <?php
                $labels = [
                    'ongoing' => 'Laufend',
                    'settled' => 'Vergleich',
                    'dismissed' => 'Abgewiesen',
                    'won_plaintiff' => 'Kläger gewonnen',
                    'won_defendant' => 'Beklagter gewonnen',
                    'appeal' => 'Berufung',
                    'suspended' => 'Ausgesetzt'
                ];
                ?>
                '<?= $labels[$item['status']] ?? $item['status'] ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [<?php echo implode(',', array_column($casesByStatus, 'count')); ?>],
            backgroundColor: [
                chartColors.info,
                chartColors.success,
                chartColors.light,
                chartColors.green,
                chartColors.warning,
                chartColors.purple,
                chartColors.yellow
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
                labels: { color: textColor }
            }
        }
    }
});

// 2. Country Chart (Bar)
const countryChart = new Chart(document.getElementById('countryChart'), {
    type: 'bar',
    data: {
        labels: [
            <?php foreach ($casesByCountry as $item): ?>
                '<?= Helpers::countryName($item['country_code']) ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Anzahl Verfahren',
            data: [<?php echo implode(',', array_column($casesByCountry, 'count')); ?>],
            backgroundColor: chartColors.primary
        }]
    },
    options: defaultOptions
});

// 3. Year Chart (Line)
const yearChart = new Chart(document.getElementById('yearChart'), {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_column($casesByYear, 'year')); ?>],
        datasets: [{
            label: 'Verfahren pro Jahr',
            data: [<?php echo implode(',', array_column($casesByYear, 'count')); ?>],
            borderColor: chartColors.info,
            backgroundColor: chartColors.info + '30',
            fill: true,
            tension: 0.4
        }]
    },
    options: defaultOptions
});

// 4. Companies Chart (Horizontal Bar)
const companiesChart = new Chart(document.getElementById('companiesChart'), {
    type: 'bar',
    data: {
        labels: [
            <?php foreach ($topCompanies as $item): ?>
                '<?= addslashes(Helpers::truncate($item['name'], 25)) ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Anzahl Verfahren',
            data: [<?php echo implode(',', array_column($topCompanies, 'case_count')); ?>],
            backgroundColor: chartColors.danger
        }]
    },
    options: {
        ...defaultOptions,
        indexAxis: 'y'
    }
});

// 5. Causes Chart (Bar)
const causesChart = new Chart(document.getElementById('causesChart'), {
    type: 'bar',
    data: {
        labels: [
            <?php foreach ($topCauses as $item): ?>
                '<?= addslashes(Helpers::truncate($item['cause_of_action'], 20)) ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Anzahl Verfahren',
            data: [<?php echo implode(',', array_column($topCauses, 'count')); ?>],
            backgroundColor: chartColors.secondary
        }]
    },
    options: defaultOptions
});

// 6. Action Types Chart (Pie)
const actionTypesChart = new Chart(document.getElementById('actionTypesChart'), {
    type: 'pie',
    data: {
        labels: [
            <?php foreach ($actionTypes as $item): ?>
                '<?= Helpers::legalActionTypeLabel($item['legal_action_type']) ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [<?php echo implode(',', array_column($actionTypes, 'count')); ?>],
            backgroundColor: [
                chartColors.purple,
                chartColors.blue,
                chartColors.green,
                chartColors.yellow
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: textColor }
            }
        }
    }
});

// Theme-Wechsel: Charts neu rendern
document.getElementById('darkModeToggle')?.addEventListener('click', () => {
    setTimeout(() => location.reload(), 100);
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
