<?php
/**
 * Helper Functions
 * Sammlung nützlicher Hilfsfunktionen
 */

class Helpers {

    /**
     * HTML-Escaping zur XSS-Prevention
     */
    public static function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * URL-Escaping
     */
    public static function url($string) {
        return urlencode($string ?? '');
    }

    /**
     * Datum formatieren (Deutsch)
     */
    public static function formatDate($date, $format = 'd.m.Y') {
        if (empty($date) || $date === '0000-00-00') {
            return '-';
        }
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        return date($format, $timestamp);
    }

    /**
     * Relative Zeitangabe (z.B. "vor 2 Tagen")
     */
    public static function timeAgo($datetime) {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'gerade eben';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return "vor {$mins} " . ($mins == 1 ? 'Minute' : 'Minuten');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "vor {$hours} " . ($hours == 1 ? 'Stunde' : 'Stunden');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return "vor {$days} " . ($days == 1 ? 'Tag' : 'Tagen');
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return "vor {$weeks} " . ($weeks == 1 ? 'Woche' : 'Wochen');
        } else {
            return self::formatDate($datetime);
        }
    }

    /**
     * Währung formatieren
     */
    public static function formatCurrency($amount, $currency = 'EUR') {
        if (empty($amount)) {
            return '-';
        }

        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF'
        ];

        $symbol = $symbols[$currency] ?? $currency;
        $formatted = number_format($amount, 2, ',', '.');

        return $currency === 'USD' ? $symbol . ' ' . $formatted : $formatted . ' ' . $symbol;
    }

    /**
     * Status-Badge HTML generieren
     */
    public static function statusBadge($status) {
        $labels = [
            'ongoing' => 'Laufend',
            'settled' => 'Vergleich',
            'dismissed' => 'Abgewiesen',
            'discontinued' => 'Eingestellt',
            'withdrawn' => 'Zurückgezogen',
            'won_plaintiff' => 'Kläger gewonnen',
            'won_defendant' => 'Beklagter gewonnen',
            'appeal' => 'Berufung',
            'suspended' => 'Ausgesetzt'
        ];

        $colors = [
            'ongoing' => 'info',
            'settled' => 'success',
            'dismissed' => 'light',
            'discontinued' => 'light',
            'withdrawn' => 'light',
            'won_plaintiff' => 'success',
            'won_defendant' => 'warning',
            'appeal' => 'link',
            'suspended' => 'warning'
        ];

        $label = $labels[$status] ?? $status;
        $color = $colors[$status] ?? 'light';

        return "<span class=\"tag is-{$color}\">{$label}</span>";
    }

    /**
     * Rechtsgebiet-Label
     */
    public static function legalActionTypeLabel($type) {
        $labels = [
            'civil' => 'Zivilrecht',
            'administrative' => 'Verwaltungsrecht',
            'criminal' => 'Strafrecht',
            'regulatory' => 'Regulierungsverfahren'
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Gerichtsebene-Label
     */
    public static function courtLevelLabel($level) {
        $labels = [
            'district' => 'Amtsgericht',
            'regional' => 'Landgericht',
            'federal' => 'Bundesgericht',
            'supreme' => 'Höchstgericht',
            'eu' => 'EU-Gericht',
            'administrative' => 'Verwaltungsgericht'
        ];

        return $labels[$level] ?? $level;
    }

    /**
     * Update-Typ-Label
     */
    public static function updateTypeLabel($type) {
        $labels = [
            'filing' => 'Einreichung',
            'hearing' => 'Anhörung',
            'ruling' => 'Urteil',
            'settlement' => 'Vergleich',
            'appeal' => 'Berufung',
            'press_release' => 'Pressemitteilung',
            'other' => 'Sonstiges'
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Update-Typ-Icon (SVG)
     */
    public static function updateTypeIcon($type) {
        $icons = [
            'filing' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
            'hearing' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M2 12h20"></path></svg>',
            'ruling' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 3v5h5M16 13H8M16 17H8M10 9H8"/></svg>',
            'settlement' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>',
            'appeal' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>',
            'press_release' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
            'other' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
        ];

        return $icons[$type] ?? $icons['other'];
    }

    /**
     * Ländername aus Code
     */
    public static function countryName($code) {
        $countries = [
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'CH' => 'Schweiz',
            'US' => 'USA',
            'GB' => 'Großbritannien',
            'FR' => 'Frankreich',
            'IT' => 'Italien',
            'ES' => 'Spanien',
            'NL' => 'Niederlande',
            'BE' => 'Belgien',
            'PL' => 'Polen',
            'SE' => 'Schweden',
            'DK' => 'Dänemark',
            'NO' => 'Norwegen',
            'FI' => 'Finnland',
            'IE' => 'Irland',
            'EU' => 'Europäische Union'
        ];

        return $countries[$code] ?? $code;
    }

    /**
     * Länderflagge-Emoji
     */
    public static function countryFlag($code) {
        if ($code === 'EU') {
            return '🇪🇺';
        }

        $code = strtoupper($code);
        if (strlen($code) !== 2) {
            return '';
        }

        // Unicode Regional Indicator Symbols
        $offset = 127397;
        return mb_chr($offset + ord($code[0])) . mb_chr($offset + ord($code[1]));
    }

    /**
     * Kürzt Text auf maximale Länge
     */
    public static function truncate($text, $maxLength = 100, $suffix = '...') {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . $suffix;
    }

    /**
     * CSV-Export generieren
     */
    public static function generateCsv($data, $filename = 'export.csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // BOM für Excel-Kompatibilität
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        if (!empty($data)) {
            // Header
            fputcsv($output, array_keys($data[0]), ';');

            // Daten
            foreach ($data as $row) {
                fputcsv($output, $row, ';');
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Aktuelle URL
     */
    public static function currentUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Redirect
     */
    public static function redirect($url) {
        header('Location: ' . $url);
        exit;
    }

    /**
     * JSON-Response
     */
    public static function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Sanitize Input
     */
    public static function sanitize($input) {
        return trim(strip_tags($input ?? ''));
    }

    /**
     * Array-Value sicher abrufen
     */
    public static function arrayGet($array, $key, $default = null) {
        return $array[$key] ?? $default;
    }
}
