import './quickSearch.js';
import {
    FONT_SCALES,
    FONT_SCALE_STORAGE_KEY,
    applyFontScale,
    formatFontScale,
    normalizeFontScale,
    stepFontScale,
} from './fontScale.js';
import { initReadingPreferences } from './readingPreferences.js';

document.addEventListener('DOMContentLoaded', initReadingPreferences);

// Text size stepper: multiplies the browser's own default font size, see fontScale.js
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.text-size-step');
    if (buttons.length === 0) return;

    const status = document.querySelector('.text-size-status');

    const getStoredScale = () => {
        try {
            return normalizeFontScale(localStorage.getItem(FONT_SCALE_STORAGE_KEY));
        } catch (e) {
            return FONT_SCALES[0];
        }
    };

    const update = (scale) => {
        const applied = applyFontScale(document.documentElement, scale);

        try {
            localStorage.setItem(FONT_SCALE_STORAGE_KEY, String(applied));
        } catch (e) { /* private mode: the size still applies for this page view */ }

        buttons.forEach((button) => {
            const direction = parseInt(button.dataset.step, 10);
            const name = direction > 0 ? 'Betűméret növelése' : 'Betűméret csökkentése';
            const title = `${name} (jelenleg ${formatFontScale(applied)})`;
            button.setAttribute('title', title);
            button.setAttribute('aria-label', title);
            button.setAttribute('aria-disabled', stepFontScale(applied, direction) === applied ? 'true' : 'false');
        });

        if (status) {
            status.textContent = `Betűméret: ${formatFontScale(applied)}`;
        }
    };

    update(getStoredScale());

    buttons.forEach((button) => {
        button.addEventListener('click', function() {
            const direction = parseInt(button.dataset.step, 10);
            update(stepFontScale(getStoredScale(), direction));
        });
    });
});

// Theme switching functionality with three states: light, dark, system
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.querySelector('.theme-toggle');
    const themeIcon = document.querySelector('.theme-icon');
    
    if (!themeToggle || !themeIcon) return;
    
    // Get stored theme from localStorage, default to 'system'
    const getStoredTheme = () => {
        const stored = localStorage.getItem('theme');
        // Accept only 'light', 'dark', or 'system'
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            return stored;
        }
        return 'system'; // default
    };
    
    // Get applied theme (light/dark) based on stored theme and system preference
    const getAppliedTheme = (storedTheme) => {
        if (storedTheme === 'light') return 'light';
        if (storedTheme === 'dark') return 'dark';
        // storedTheme is 'system' or invalid -> follow system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    };
    
    // Update UI to reflect the given stored theme
    const applyStoredTheme = (storedTheme) => {
        const applied = getAppliedTheme(storedTheme);
        
        // Update data-theme attribute
        if (applied === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        
        // Update icon
        themeIcon.classList.remove('bi-moon-stars', 'bi-sun', 'bi-laptop');
        if (storedTheme === 'system') {
            themeIcon.classList.add('bi-laptop');
        } else if (storedTheme === 'dark') {
            themeIcon.classList.add('bi-moon-stars');
        } else { // light
            themeIcon.classList.add('bi-sun');
        }
        
        // Update button title
        let title = 'Sötét/világos mód váltása';
        if (storedTheme === 'system') {
            title = 'Rendszer alapján (sötét/világos)';
        } else if (storedTheme === 'dark') {
            title = 'Sötét mód';
        } else {
            title = 'Világos mód';
        }
        themeToggle.setAttribute('title', title);
        themeToggle.setAttribute('aria-label', title);
    };
    
    // Determine next theme in cycle: light -> dark -> system -> light
    const getNextStoredTheme = (currentStoredTheme) => {
        if (currentStoredTheme === 'light') return 'dark';
        if (currentStoredTheme === 'dark') return 'system';
        return 'light'; // system -> light
    };
    
    const storedTheme = getStoredTheme();
    applyStoredTheme(storedTheme);
    
    // Toggle theme on button click
    themeToggle.addEventListener('click', function() {
        const current = getStoredTheme();
        const next = getNextStoredTheme(current);
        localStorage.setItem('theme', next);
        applyStoredTheme(next);
    });
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        const stored = getStoredTheme();
        // Only apply system theme if stored theme is 'system'
        if (stored === 'system') {
            applyStoredTheme('system');
        }
    });
});

$('#semanticSearchForm').on('submit', function (event) {
    event.preventDefault();
    $('#interstitial').show();
    event.target.submit();
});

$('.interstitial').on('click', () =>
    $('#interstitial').show()
);


window.addEventListener('pageshow', (event) => {
    $('#interstitial').hide()
});