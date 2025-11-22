<?php
/**
 * Parsedown
 * Einfacher Markdown-Parser (vereinfachte Version)
 * Basierend auf Parsedown by Emanuil Rusev
 * https://github.com/erusev/parsedown
 *
 * Für Produktion: Laden Sie die vollständige Parsedown.php herunter:
 * https://raw.githubusercontent.com/erusev/parsedown/master/Parsedown.php
 */

class Parsedown {

    /**
     * Konvertiert Markdown zu HTML
     */
    public function text($text) {
        $text = $this->sanitize($text);

        // Absätze
        $text = $this->parseParagraphs($text);

        // Headlines
        $text = $this->parseHeadlines($text);

        // Listen
        $text = $this->parseLists($text);

        // Code-Blöcke
        $text = $this->parseCodeBlocks($text);

        // Inline-Formatting
        $text = $this->parseInline($text);

        return $text;
    }

    /**
     * Sanitize Input
     */
    private function sanitize($text) {
        // Normalisiere Zeilenumbrüche
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return trim($text);
    }

    /**
     * Parst Überschriften (# bis ######)
     */
    private function parseHeadlines($text) {
        $lines = explode("\n", $text);
        $output = [];

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $content = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
                $output[] = "<h{$level}>{$content}</h{$level}>";
            } else {
                $output[] = $line;
            }
        }

        return implode("\n", $output);
    }

    /**
     * Parst Listen (- oder *)
     */
    private function parseLists($text) {
        // Ungeordnete Listen
        $text = preg_replace_callback('/^[-*]\s+(.+)$/m', function($matches) {
            return '<li>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</li>';
        }, $text);

        // Umschließe <li> mit <ul>
        $text = preg_replace('/(<li>.*?<\/li>\n?)+/s', '<ul>$0</ul>', $text);

        // Geordnete Listen
        $text = preg_replace_callback('/^\d+\.\s+(.+)$/m', function($matches) {
            return '<li>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</li>';
        }, $text);

        // Umschließe <li> mit <ol>
        $text = preg_replace('/(<li>.*?<\/li>\n?)+/s', '<ol>$0</ol>', $text);

        return $text;
    }

    /**
     * Parst Code-Blöcke (```)
     */
    private function parseCodeBlocks($text) {
        $text = preg_replace_callback('/```(.+?)\n(.*?)```/s', function($matches) {
            $lang = trim($matches[1]);
            $code = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return "<pre><code class=\"language-{$lang}\">{$code}</code></pre>";
        }, $text);

        return $text;
    }

    /**
     * Parst Absätze
     */
    private function parseParagraphs($text) {
        $blocks = explode("\n\n", $text);
        $output = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            // Nicht umschließen wenn bereits HTML-Tag
            if (preg_match('/^<(h\d|ul|ol|pre|blockquote)/', $block)) {
                $output[] = $block;
            } else {
                $output[] = "<p>{$block}</p>";
            }
        }

        return implode("\n\n", $output);
    }

    /**
     * Parst Inline-Formatierung
     */
    private function parseInline($text) {
        // Fett: **text** oder __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);

        // Kursiv: *text* oder _text_
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);

        // Inline-Code: `code`
        $text = preg_replace_callback('/`(.+?)`/', function($matches) {
            return '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>';
        }, $text);

        // Links: [text](url)
        $text = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function($matches) {
            $text = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            return "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$text}</a>";
        }, $text);

        // Zeilenumbrüche
        $text = nl2br($text);

        return $text;
    }

    /**
     * Konvertiert HTML zurück zu Text (für Vorschau)
     */
    public function toPlainText($html) {
        $text = strip_tags($html);
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }
}
