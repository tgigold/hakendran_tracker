<?php
/**
 * Generischer Page-Handler für statische Seiten
 *
 * Sicherheit:
 * - Whitelist-basierter Ansatz: nur definierte Seiten werden geladen
 * - Kein direkter Dateipfad-Zugriff durch User-Input
 * - Path Traversal verhindert durch array key lookup
 *
 * Verwendung: page.php?p=about|impressum|datenschutz
 */

require_once __DIR__ . '/libraries/Helpers.php';

// Whitelist: Erlaubte Seiten mit ihren Metadaten
$allowedPages = [
    'about' => [
        'title' => 'Über uns',
        'template' => 'about.php',
        'description' => 'Über Haken Dran Verfahrenstracker'
    ],
    'impressum' => [
        'title' => 'Impressum',
        'template' => 'impressum.php',
        'description' => 'Rechtliche Informationen und Kontakt'
    ],
    'datenschutz' => [
        'title' => 'Datenschutzerklärung',
        'template' => 'datenschutz.php',
        'description' => 'Informationen zum Datenschutz'
    ]
];

// GET-Parameter auslesen (sanitized)
$requestedPage = isset($_GET['p']) ? Helpers::sanitize($_GET['p']) : '';

// Standardseite: about
if (empty($requestedPage) || !array_key_exists($requestedPage, $allowedPages)) {
    $requestedPage = 'about';
}

// Seiten-Metadaten laden
$pageData = $allowedPages[$requestedPage];
$pageTitle = $pageData['title'];
$templateFile = __DIR__ . '/templates/pages/' . $pageData['template'];

// Sicherheitscheck: Template muss existieren
if (!file_exists($templateFile)) {
    http_response_code(404);
    die('Seite nicht gefunden.');
}

// Sicherheitscheck: Template muss im korrekten Verzeichnis sein
$realPath = realpath($templateFile);
$expectedPath = realpath(__DIR__ . '/templates/pages/');
if ($realPath === false || strpos($realPath, $expectedPath) !== 0) {
    http_response_code(403);
    die('Zugriff verweigert.');
}

// Header einbinden
require_once __DIR__ . '/templates/header.php';

// Template-Inhalt laden
require $templateFile;

// Footer einbinden
require_once __DIR__ . '/templates/footer.php';
