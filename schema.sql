-- ============================================
-- Hakendran Big Tech Verfahrenstracker
-- Datenbank-Schema mit track_ Präfix
-- Version 2.0 - MySQL-basierte Benutzerverwaltung
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Tabelle: track_users (Benutzerverwaltung & Audit-Logging)
-- ============================================
CREATE TABLE IF NOT EXISTS track_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(200),
    email VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,

    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_parties (Beteiligte)
-- ============================================
CREATE TABLE IF NOT EXISTS track_parties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(300) NOT NULL,
    type ENUM('corporation', 'government', 'authority', 'individual', 'ngo', 'other') DEFAULT 'corporation',
    country_code VARCHAR(2),
    is_big_tech BOOLEAN DEFAULT FALSE,
    logo_url VARCHAR(500),
    website VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_name (name),
    INDEX idx_type (type),
    INDEX idx_big_tech (is_big_tech)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_cases (Haupttabelle)
-- ============================================
CREATE TABLE IF NOT EXISTS track_cases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(500) NOT NULL,
    case_number VARCHAR(200),
    court_file VARCHAR(200),

    legal_action_type ENUM('civil', 'administrative', 'criminal', 'regulatory') DEFAULT 'civil',
    cause_of_action VARCHAR(100),
    subject_matter TEXT,

    status ENUM('ongoing', 'settled', 'dismissed', 'won_plaintiff', 'won_defendant', 'appeal', 'suspended') DEFAULT 'ongoing',
    date_filed DATE,
    next_hearing_date DATE,
    date_concluded DATE,

    amount_disputed DECIMAL(20,2),
    currency VARCHAR(3) DEFAULT 'EUR',
    penalty_paid DECIMAL(20,2),
    penalty_confirmed BOOLEAN DEFAULT FALSE,

    country_code VARCHAR(2),
    court_name VARCHAR(300),
    court_level ENUM('district', 'regional', 'federal', 'supreme', 'eu', 'administrative'),

    source_url TEXT,
    internal_notes TEXT,
    public_visibility BOOLEAN DEFAULT TRUE,

    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES track_users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES track_users(id) ON DELETE SET NULL,

    INDEX idx_status (status),
    INDEX idx_country (country_code),
    INDEX idx_cause (cause_of_action),
    INDEX idx_date_filed (date_filed),
    INDEX idx_created_by (created_by),
    FULLTEXT INDEX ft_search (title, subject_matter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_case_parties (Verknüpfung)
-- ============================================
CREATE TABLE IF NOT EXISTS track_case_parties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    party_id INT NOT NULL,
    role ENUM('plaintiff', 'defendant', 'intervenor', 'amicus') NOT NULL,
    law_firm VARCHAR(300),

    FOREIGN KEY (case_id) REFERENCES track_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (party_id) REFERENCES track_parties(id) ON DELETE CASCADE,
    UNIQUE KEY unique_case_party (case_id, party_id, role),
    INDEX idx_case (case_id),
    INDEX idx_party (party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_case_updates (Timeline)
-- ============================================
CREATE TABLE IF NOT EXISTS track_case_updates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    update_date DATE NOT NULL,
    update_type ENUM('filing', 'hearing', 'ruling', 'settlement', 'appeal', 'press_release', 'other') DEFAULT 'other',
    title VARCHAR(300),
    description TEXT,
    source_url TEXT,
    is_major BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (case_id) REFERENCES track_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES track_users(id) ON DELETE SET NULL,
    INDEX idx_case (case_id),
    INDEX idx_date (update_date),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_legal_bases (Rechtsgrundlagen)
-- ============================================
CREATE TABLE IF NOT EXISTS track_legal_bases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    description TEXT,

    UNIQUE KEY unique_code (code),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_case_legal_bases (Verknüpfung)
-- ============================================
CREATE TABLE IF NOT EXISTS track_case_legal_bases (
    case_id INT NOT NULL,
    legal_basis_id INT NOT NULL,

    PRIMARY KEY (case_id, legal_basis_id),
    FOREIGN KEY (case_id) REFERENCES track_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (legal_basis_id) REFERENCES track_legal_bases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_sources (Externe Quellen)
-- ============================================
CREATE TABLE IF NOT EXISTS track_sources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_id INT NOT NULL,
    url TEXT NOT NULL,
    title VARCHAR(500),
    source_type ENUM('court_document', 'press_release', 'news_article', 'company_statement', 'other') DEFAULT 'other',
    date_published DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (case_id) REFERENCES track_cases(id) ON DELETE CASCADE,
    INDEX idx_case (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_tags (Flexible Kategorisierung)
-- ============================================
CREATE TABLE IF NOT EXISTS track_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50),

    UNIQUE KEY unique_tag (name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS track_case_tags (
    case_id INT NOT NULL,
    tag_id INT NOT NULL,

    PRIMARY KEY (case_id, tag_id),
    FOREIGN KEY (case_id) REFERENCES track_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES track_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabelle: track_audit_log (System-Logging)
-- ============================================
CREATE TABLE IF NOT EXISTS track_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES track_users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Basis-Daten für Rechtsgrundlagen
-- ============================================
INSERT INTO track_legal_bases (code, category, description) VALUES
('DSGVO Art. 6', 'Datenschutz', 'Rechtmäßigkeit der Verarbeitung'),
('DSGVO Art. 9', 'Datenschutz', 'Verarbeitung besonderer Kategorien personenbezogener Daten'),
('DSGVO Art. 17', 'Datenschutz', 'Recht auf Löschung'),
('DSGVO Art. 82', 'Datenschutz', 'Haftung und Schadensersatz'),
('DSA Art. 14', 'Digital Services Act', 'Meldung von Inhalten'),
('DSA Art. 16', 'Digital Services Act', 'Interne Beschwerdemanagement'),
('DSA Art. 24', 'Digital Services Act', 'Transparenzberichte'),
('DMA Art. 5', 'Digital Markets Act', 'Verpflichtungen für Gatekeeper'),
('DMA Art. 6', 'Digital Markets Act', 'Weitere Verpflichtungen für Gatekeeper'),
('GWB § 19', 'Kartellrecht', 'Missbrauch einer marktbeherrschenden Stellung'),
('GWB § 20', 'Kartellrecht', 'Diskriminierungsverbot'),
('AEUV Art. 101', 'EU-Kartellrecht', 'Verbot wettbewerbsbeschränkender Vereinbarungen'),
('AEUV Art. 102', 'EU-Kartellrecht', 'Missbrauch einer beherrschenden Stellung'),
('CCPA Section 1798.150', 'Datenschutz (USA)', 'Private Right of Action'),
('Sherman Act Section 2', 'Kartellrecht (USA)', 'Monopolization'),
('Clayton Act Section 7', 'Kartellrecht (USA)', 'Mergers');

-- ============================================
-- Basis-Tags
-- ============================================
INSERT INTO track_tags (name, category) VALUES
('Datenschutz', 'Rechtsgebiet'),
('Kartellrecht', 'Rechtsgebiet'),
('Wettbewerbsrecht', 'Rechtsgebiet'),
('Verbraucherschutz', 'Rechtsgebiet'),
('Arbeitnehmerrechte', 'Rechtsgebiet'),
('Urheberrecht', 'Rechtsgebiet'),
('Sammelklage', 'Verfahrensart'),
('Behördenverfahren', 'Verfahrensart'),
('Schadenersatz', 'Klageart'),
('Unterlassung', 'Klageart'),
('Marktmissbrauch', 'Vorwurf'),
('Datenleck', 'Vorwurf'),
('Tracking', 'Thema'),
('Algorithmen', 'Thema'),
('Plattformregulierung', 'Thema');

SET FOREIGN_KEY_CHECKS = 1;
