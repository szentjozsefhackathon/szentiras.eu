/**
 * Reading preferences for the scripture text.
 *
 * Every preference maps to a `data-*` attribute on the root element and is picked up by
 * `.parsedVerses` rules in app.less, so the settings change the flowing text only — the
 * navigation, buttons and page furniture keep their layout.
 *
 * A preference the reader has never touched is stored as *absent* rather than as its default
 * value. That distinction matters for contrast: while it is absent, `prefers-contrast: more`
 * may switch the text to high contrast on its own, and an explicit `normal` turns that off.
 */

export const READING_PREFERENCES_STORAGE_KEY = 'readingPreferences';

/**
 * Atkinson Hyperlegible was designed by the Braille Institute for low vision readers. It is
 * fetched only when actually selected, so the default page load carries no extra font.
 *
 * The `Next` release is used rather than the original family because the original ships only
 * weights 400 and 700: a request for the 600 that the bolder-text switch applies would fall
 * back to 400, leaving the switch with no visible effect.
 */
export const HYPERLEGIBLE_FONT_HREF = 'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:ital,wght@0,400;0,600;1,400;1,600&display=swap';

/**
 * @type {Object<string, {attribute: string, values: string[]}>}
 */
export const READING_PREFERENCES = {
    font: { attribute: 'data-text-font', values: ['serif', 'sans', 'hyperlegible'] },
    weight: { attribute: 'data-text-weight', values: ['normal', 'bold'] },
    spacing: { attribute: 'data-text-spacing', values: ['normal', 'comfortable'] },
    align: { attribute: 'data-text-align', values: ['justify', 'left'] },
    contrast: { attribute: 'data-text-contrast', values: ['normal', 'high'] },
};

/**
 * Drops unknown keys and unknown values, so a stale or hand edited store cannot put the
 * text into a state the stylesheet has no rule for.
 */
export function normalizeReadingPreferences(preferences) {
    const normalized = {};
    if (!preferences || typeof preferences !== 'object') {
        return normalized;
    }

    Object.keys(READING_PREFERENCES).forEach(key => {
        const value = preferences[key];
        if (READING_PREFERENCES[key].values.indexOf(value) !== -1) {
            normalized[key] = value;
        }
    });

    return normalized;
}

/**
 * Reads the stored preferences from a JSON string, tolerating anything unparseable.
 */
export function parseReadingPreferences(json) {
    if (typeof json !== 'string' || json === '') {
        return {};
    }

    try {
        return normalizeReadingPreferences(JSON.parse(json));
    } catch (e) {
        return {};
    }
}

/**
 * Writes the preferences onto the root element as `data-*` attributes, removing the ones the
 * reader has not set.
 */
export function applyReadingPreferences(root, preferences) {
    const normalized = normalizeReadingPreferences(preferences);

    Object.keys(READING_PREFERENCES).forEach(key => {
        const { attribute } = READING_PREFERENCES[key];
        if (normalized[key] === undefined) {
            root.removeAttribute(attribute);
        } else {
            root.setAttribute(attribute, normalized[key]);
        }
    });

    return normalized;
}

/**
 * Adds the Atkinson Hyperlegible stylesheet once, when that font is in use.
 */
export function ensureHyperlegibleFont(doc) {
    if (doc.querySelector('link[data-font="hyperlegible"]')) {
        return null;
    }

    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = HYPERLEGIBLE_FONT_HREF;
    link.setAttribute('crossorigin', '');
    link.setAttribute('data-font', 'hyperlegible');
    doc.head.appendChild(link);

    return link;
}

/**
 * Wires up the preference controls in the accessibility toolbar.
 */
export function initReadingPreferences() {
    const controls = document.querySelectorAll('[data-preference]');
    if (controls.length === 0) {
        return;
    }

    const read = () => {
        try {
            return parseReadingPreferences(localStorage.getItem(READING_PREFERENCES_STORAGE_KEY));
        } catch (e) {
            return {};
        }
    };

    const write = (preferences) => {
        try {
            localStorage.setItem(READING_PREFERENCES_STORAGE_KEY, JSON.stringify(preferences));
        } catch (e) { /* private mode: the setting still applies for this page view */ }
    };

    const apply = (preferences) => {
        const applied = applyReadingPreferences(document.documentElement, preferences);
        if (applied.font === 'hyperlegible') {
            ensureHyperlegibleFont(document);
        }

        return applied;
    };

    const syncControls = (preferences) => {
        controls.forEach((control) => {
            const key = control.dataset.preference;
            if (control.type === 'radio') {
                control.checked = (preferences[key] || READING_PREFERENCES[key].values[0]) === control.value;
            } else {
                control.checked = preferences[key] === control.dataset.on;
            }
        });
    };

    let preferences = apply(read());
    syncControls(preferences);

    controls.forEach((control) => {
        control.addEventListener('change', function() {
            const key = control.dataset.preference;
            const value = control.type === 'radio'
                ? control.value
                : (control.checked ? control.dataset.on : control.dataset.off);

            preferences = apply({ ...preferences, [key]: value });
            write(preferences);
            syncControls(preferences);
        });
    });
}
