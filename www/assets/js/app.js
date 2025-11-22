/**
 * Hakendran Gerichtstracker - Main JavaScript
 */

// Globale Utility-Funktionen
const App = {
    /**
     * Debounce-Funktion für Suche
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * AJAX-Request senden
     */
    ajax: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const config = { ...defaults, ...options };

        if (config.data && config.method !== 'GET') {
            config.body = JSON.stringify(config.data);
        }

        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .catch(error => {
                console.error('Ajax error:', error);
                throw error;
            });
    },

    /**
     * Notification anzeigen
     */
    notify: function(message, type = 'info') {
        const colors = {
            success: 'is-success',
            error: 'is-danger',
            warning: 'is-warning',
            info: 'is-info'
        };

        const notification = document.createElement('div');
        notification.className = `notification ${colors[type] || colors.info}`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.style.animation = 'slideInRight 0.3s ease-out';

        notification.innerHTML = `
            <button class="delete"></button>
            ${message}
        `;

        document.body.appendChild(notification);

        // Delete-Button
        notification.querySelector('.delete').addEventListener('click', () => {
            notification.remove();
        });

        // Auto-Remove nach 5 Sekunden
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    },

    /**
     * Formular-Daten als Object
     */
    formData: function(form) {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        return data;
    },

    /**
     * URL-Parameter erstellen
     */
    buildQueryString: function(params) {
        return Object.keys(params)
            .filter(key => params[key] !== '' && params[key] !== null && params[key] !== undefined)
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(params[key]))
            .join('&');
    },

    /**
     * Datum formatieren
     */
    formatDate: function(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    },

    /**
     * Confirm-Dialog
     */
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
};

// Animation für Notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Delete-Buttons für Notifications
document.addEventListener('DOMContentLoaded', () => {
    // Alle existierenden Delete-Buttons
    (document.querySelectorAll('.notification .delete') || []).forEach(($delete) => {
        const $notification = $delete.parentNode;
        $delete.addEventListener('click', () => {
            $notification.parentNode.removeChild($notification);
        });
    });

    // Modal-Schließen
    (document.querySelectorAll('.modal-background, .modal-close, .modal-card-head .delete') || []).forEach(($close) => {
        const $target = $close.closest('.modal');
        $close.addEventListener('click', () => {
            $target.classList.remove('is-active');
        });
    });

    // Tabs
    (document.querySelectorAll('.tabs li') || []).forEach(($tab) => {
        $tab.addEventListener('click', () => {
            const target = $tab.dataset.target;
            if (!target) return;

            // Remove active from all tabs
            $tab.parentNode.querySelectorAll('li').forEach(li => li.classList.remove('is-active'));
            $tab.classList.add('is-active');

            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });

            // Show target content
            const targetContent = document.getElementById(target);
            if (targetContent) {
                targetContent.style.display = 'block';
            }
        });
    });

    // Navbar Burger Toggle
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
    $navbarBurgers.forEach(el => {
        el.addEventListener('click', () => {
            const target = el.dataset.target;
            const $target = document.getElementById(target);
            el.classList.toggle('is-active');
            $target.classList.toggle('is-active');
        });
    });

    // Dark Mode initialisieren
    initDarkMode();
});

/**
 * Dark Mode Funktionalität
 */
function initDarkMode() {
    const html = document.documentElement;
    const toggle = document.getElementById('darkModeToggle');
    const icon = document.getElementById('darkModeIcon');

    if (!toggle || !icon) return;

    // Icons
    const moonIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
    const sunIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';

    // Theme aus localStorage oder System-Präferenz laden
    let theme = localStorage.getItem('theme');

    if (!theme) {
        // System-Präferenz prüfen
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
        } else {
            theme = 'light';
        }
    }

    // Theme anwenden
    function applyTheme(newTheme) {
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        if (newTheme === 'dark') {
            icon.innerHTML = sunIcon;
        } else {
            icon.innerHTML = moonIcon;
        }
    }

    // Initialisierung
    applyTheme(theme);

    // Toggle-Event
    toggle.addEventListener('click', function() {
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(newTheme);
    });

    // System-Präferenz-Änderung beobachten
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
}

// Export für globale Verwendung
window.App = App;
