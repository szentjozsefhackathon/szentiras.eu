/**
 * Text size stepper for the scripture text.
 *
 * Scales `--book-font-size` only, so the reading text grows while the navigation, buttons
 * and page furniture keep their layout. Readers who want everything larger have the
 * browser's own zoom for that.
 *
 * The scale is expressed in `rem`, and the root font size is `100%`, so the result stacks on
 * top of whatever default font size the reader configured in the browser.
 */

export const FONT_SCALE_STORAGE_KEY = 'fontScale';

/** Custom property carrying the scripture text size, see app.less. */
export const BOOK_FONT_SIZE_PROPERTY = '--book-font-size';

/** @type {number[]} Selectable multipliers, smallest (= browser default) first. */
export const FONT_SCALES = [1, 1.15, 1.3, 1.5, 1.75, 2];

/**
 * Snaps an arbitrary stored value to the closest selectable scale.
 */
export function normalizeFontScale(value) {
    const parsed = typeof value === 'number' ? value : parseFloat(value);
    if (!isFinite(parsed)) {
        return FONT_SCALES[0];
    }

    return FONT_SCALES.reduce((closest, scale) => (
        Math.abs(scale - parsed) < Math.abs(closest - parsed) ? scale : closest
    ), FONT_SCALES[0]);
}

/**
 * Returns the neighbouring scale in the given direction (-1 smaller, +1 larger),
 * clamped at both ends of the range.
 */
export function stepFontScale(current, direction) {
    const index = FONT_SCALES.indexOf(normalizeFontScale(current));
    const nextIndex = Math.min(FONT_SCALES.length - 1, Math.max(0, index + direction));

    return FONT_SCALES[nextIndex];
}

/**
 * Human readable label of a scale, e.g. `130%`.
 */
export function formatFontScale(scale) {
    return `${Math.round(normalizeFontScale(scale) * 100)}%`;
}

/**
 * Writes the scale onto the root element as the scripture text size. The default scale
 * removes the inline property so the stylesheet's own `1rem` takes over again.
 */
export function applyFontScale(root, scale) {
    const normalized = normalizeFontScale(scale);

    if (normalized === FONT_SCALES[0]) {
        root.style.removeProperty(BOOK_FONT_SIZE_PROPERTY);
    } else {
        root.style.setProperty(BOOK_FONT_SIZE_PROPERTY, `${normalized}rem`);
    }

    return normalized;
}
