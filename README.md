# 🏛️ Hakendran Big Tech Verfahrenstracker

Durchsuchbare Datenbank aller Gerichtsverfahren gegen große Tech-Konzerne (Meta, X, Google, etc.) in den Bereichen Kartellrecht, DSA/DMA-Verstöße, Datenschutz (DSGVO, CCPA), Behördliche Ermittlungsverfahren und Zivilklagen.

## 📋 Features

### Frontend (Öffentlich)
- **Startseite**
  - Prominentes Suchfeld
  - Anstehende Anhörungen (nächste 30 Tage)
  - Neueste Verfahren
  - Tag-Wolke
  - Statistik-Übersicht

- **Verfahrens-Liste**
  - Live-Filter (Unternehmen, Land, Status, Rechtsgebiet, Tags, Datumsbereich)
  - Volltextsuche mit Debounce (300ms)
  - Sortierung nach Datum, Streitwert, Titel
  - Pagination (10-100 pro Seite)
  - CSV-Export
  - Responsive Card-Grid-Layout

- **Verfahrens-Detailansicht**
  - Alle Case-Details (Beteiligte, Gericht, Termine, Streitwerte)
  - Chronologische Timeline mit Updates
  - Rechtsgrundlagen und Tags
  - Externe Quellen
  - Markdown-Rendering für Beschreibungen

- **Statistik-Seite**
  - Interaktive Charts (Chart.js)
  - Verfahren nach Status, Land, Jahr
  - Top Big Tech Unternehmen
  - Top Rechtsgebiete
  - Höchste Streitwerte

### Backend (Authentifiziert)
- **Login-System**
  - Session-basierte Authentifizierung
  - Passwort-Hashing (Argon2ID)
  - CSRF-Protection

- **Dashboard**
  - Übersichts-Statistiken
  - Neueste Verfahren
  - Kürzlich aktualisiert
  - Aktivitätslog

- **Verfahrens-Verwaltung**
  - Multi-Section-Formular
  - Basisdaten, Beteiligte, Zuständigkeit, Finanzielle Details, Termine
  - Markdown-Support für Beschreibungen
  - Öffentlich/Privat-Toggle

- **Beteiligte-Verwaltung**
  - CRUD-Operations für Parties
  - Big Tech Markierung
  - Verwendungs-Tracking

### Technische Features
- **Sicherheit**
  - SQL Injection Prevention (PDO Prepared Statements)
  - XSS Prevention (HTML-Escaping)
  - CSRF-Token für Formulare
  - Session-Sicherheit
  - Audit-Logging aller Aktionen

- **Performance**
  - Datenbank-Indizes
  - Fulltext-Index für Suche
  - Pagination
  - Eager Loading (JOINs statt N+1 Queries)

- **Design**
  - Bulma CSS Framework
  - Darkmode-Support (automatische Erkennung + Toggle)
  - Responsive Design (Mobile-First)
  - Farbschema inspiriert von linktr.ee/hakendran

## 🚀 Installation

### Voraussetzungen
- PHP 8.0 oder höher
- MySQL 8.0 oder höher
- Webserver (Apache/Nginx)
- Shared Hosting kompatibel (kein Node.js erforderlich)

### Schritt 1: Dateien hochladen
```bash
# Repository klonen oder ZIP herunterladen
git clone https://github.com/your-repo/hakendran-tracker.git
cd hakendran-tracker

# Dateien auf Server hochladen (z.B. via FTP)
# Struktur:
# /home/user/hakendran_tracker/  (außerhalb Webroot)
# ├── config.inc.php              (wird von install.php erstellt)
# ├── user.auth.php               (wird von install.php erstellt)
# ├── schema.sql
# └── www/                        (Webroot)
```

### Schritt 2: Externe Bibliotheken herunterladen
```bash
cd www/assets/vendor/

# Bulma CSS
curl -o bulma.min.css https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css

# Chart.js
curl -o chart.min.js https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js
```

**Alternativ:** Siehe `www/assets/vendor/VENDOR_INFO.txt` für manuelle Download-Links.

### Schritt 3: Installation durchführen
1. Öffnen Sie im Browser: `https://ihre-domain.de/install.php`
2. Folgen Sie den Schritten:
   - Datenbank-Verbindung konfigurieren
   - Schema importieren
   - Admin-User erstellen
   - Konfigurationsdateien erstellen

3. **WICHTIG:** Nach erfolgreicher Installation:
   ```bash
   # 1. Verschieben Sie config.inc.php und user.auth.php außerhalb des Webroots
   mv www/config.inc.php ../config.inc.php
   mv www/user.auth.php ../user.auth.php

   # 2. Löschen Sie install.php
   rm www/install.php
   ```

### Schritt 4: Erste Schritte
1. Login: `https://ihre-domain.de/backend/login.php`
2. Beteiligte anlegen: Backend → Beteiligte verwalten
3. Erstes Verfahren erfassen: Backend → Neues Verfahren

## 📁 Projektstruktur

```
hakendran_tracker/
├── config.inc.php                 # Konfiguration (außerhalb Webroot!)
├── user.auth.php                  # Benutzer-Daten (außerhalb Webroot!)
├── schema.sql                     # Datenbank-Schema
├── README.md
└── www/                           # Webroot
    ├── index.php                  # Startseite
    ├── cases.php                  # Verfahrens-Liste
    ├── case.php                   # Verfahrens-Detail
    ├── stats.php                  # Statistiken
    ├── assets/
    │   ├── css/
    │   │   └── style.css          # Custom Styles + Darkmode
    │   ├── js/
    │   │   └── app.js             # JavaScript Utilities
    │   └── vendor/
    │       ├── bulma.min.css      # Bulma CSS Framework
    │       ├── chart.min.js       # Chart.js
    │       └── VENDOR_INFO.txt
    ├── libraries/
    │   ├── Database.php           # PDO-Wrapper
    │   ├── Auth.php               # Authentifizierung
    │   ├── Helpers.php            # Hilfsfunktionen
    │   └── Parsedown.php          # Markdown-Parser
    ├── templates/
    │   ├── header.php             # Header + Navigation
    │   └── footer.php             # Footer + Darkmode-Script
    └── backend/
        ├── login.php              # Login-Formular
        ├── logout.php             # Logout-Handler
        ├── dashboard.php          # Backend-Dashboard
        ├── case-form.php          # Verfahrens-Formular
        └── parties.php            # Beteiligte-Verwaltung
```

## 🔐 Sicherheit

### Best Practices
1. **config.inc.php und user.auth.php außerhalb des Webroots** speichern
2. **install.php löschen** nach erfolgreicher Installation
3. **HTTPS verwenden** (setzen Sie `session.cookie_secure = 1` in config.inc.php)
4. **Regelmäßige Backups** der Datenbank
5. **PHP und MySQL aktuell halten**

### Neue Benutzer hinzufügen
Bearbeiten Sie `user.auth.php`:

```php
<?php
return [
    'admin' => [
        'password' => '$argon2id$v=19$m=65536,t=4,p=1$...',
        'display_name' => 'Administrator',
        'email' => 'admin@example.com',
        'is_active' => true
    ],
    'editor' => [
        'password' => '$argon2id$v=19$m=65536,t=4,p=1$...',
        'display_name' => 'Editor',
        'email' => 'editor@example.com',
        'is_active' => true
    ]
];
```

**Passwort-Hash generieren:**
```php
<?php
require_once 'www/libraries/Auth.php';
echo Auth::hashPassword('IhrPasswort123');
```

## 🎨 Anpassungen

### Farbschema ändern
Bearbeiten Sie `www/assets/css/style.css`:

```css
:root {
    --primary-color: #8B5CF6;      /* Ihre Hauptfarbe */
    --secondary-color: #EC4899;    /* Ihre Sekundärfarbe */
}
```

### Logo hinzufügen
Ersetzen Sie das Emoji in `www/templates/header.php`:
```php
<span style="font-size: 2rem;">🏛️</span>
<!-- durch -->
<img src="/assets/images/logo.png" alt="Logo">
```

## 📊 Datenbank-Schema

Siehe `schema.sql` für das vollständige Schema.

**Haupttabellen:**
- `cases` - Verfahren
- `parties` - Beteiligte (Unternehmen, Behörden, etc.)
- `case_parties` - Verknüpfung Cases ↔ Parties
- `case_updates` - Timeline/Updates
- `legal_bases` - Rechtsgrundlagen (DSGVO, DMA, etc.)
- `tags` - Flexible Kategorisierung
- `sources` - Externe Quellen
- `users` - Benutzer (für Audit-Logging)
- `audit_log` - System-Logging

## 🛠️ Tech-Stack

- **Backend:** PHP 8.x mit PDO
- **Datenbank:** MySQL 8.x
- **Frontend:** Bulma CSS 0.9.4
- **JavaScript:** Vanilla JS + Chart.js 4.4
- **Markdown:** Parsedown
- **Security:** Argon2ID, Prepared Statements, CSRF-Tokens

## 📝 Changelog

### Version 1.0.0 (2025-01-22)
- Initiale Version
- Vollständiges CRUD für Verfahren
- Parties-Verwaltung
- Statistik-Seite mit Charts
- Darkmode-Support
- CSV-Export
- Audit-Logging

## 🤝 Beitragen

Dieses Projekt wurde für [Hakendran](https://linktr.ee/hakendran) entwickelt.

## 📄 Lizenz

[Ihre Lizenz hier einfügen]

## 🔗 Links

- Website: https://linktr.ee/hakendran
- Repository: [GitHub-Link]
- Dokumentation: Siehe README.md

## 💡 Support

Bei Fragen oder Problemen:
1. Überprüfen Sie die Installationsanweisungen
2. Prüfen Sie die PHP-Error-Logs
3. Erstellen Sie ein Issue auf GitHub

## 🙏 Danksagungen

- Bulma CSS Team
- Chart.js Team
- Parsedown by Emanuil Rusev