-- ============================================
-- Haken Dran Gerichtstracker - Aliase Update
-- Version: 2.1
-- ============================================

-- Neue Tabelle: track_party_aliases
-- Erlaubt mehrere Namen/Aliase für eine Partei
CREATE TABLE IF NOT EXISTS track_party_aliases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    party_id INT NOT NULL,
    alias_name VARCHAR(300) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (party_id) REFERENCES track_parties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_alias (party_id, alias_name),
    INDEX idx_alias_name (alias_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beispiel-Daten für bekannte Unternehmen
-- Diese müssen nach dem Anlegen der Firmen in track_parties eingefügt werden

-- Facebook/Meta Aliase (party_id muss angepasst werden)
-- INSERT INTO track_party_aliases (party_id, alias_name) VALUES
-- (1, 'Facebook'),
-- (1, 'Instagram'),
-- (1, 'WhatsApp'),
-- (1, 'Threads');

-- Twitter/X Aliase (party_id muss angepasst werden)
-- INSERT INTO track_party_aliases (party_id, alias_name) VALUES
-- (2, 'Twitter'),
-- (2, 'xAI');
