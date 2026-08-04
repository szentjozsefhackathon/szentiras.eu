import assert from 'node:assert/strict';
import test from 'node:test';
import {
    HYPERLEGIBLE_FONT_HREF,
    READING_PREFERENCES,
    applyReadingPreferences,
    ensureHyperlegibleFont,
    normalizeReadingPreferences,
    parseReadingPreferences,
} from '../../resources/assets/js/readingPreferences.js';

function createRootStub(attributes = {}) {
    const values = { ...attributes };

    return {
        attributes: values,
        setAttribute(name, value) {
            values[name] = value;
        },
        removeAttribute(name) {
            delete values[name];
        },
    };
}

test('normalizeReadingPreferences keeps only known keys with known values', () => {
    const normalized = normalizeReadingPreferences({
        font: 'hyperlegible',
        weight: 'heavier',
        spacing: 'comfortable',
        colour: 'pink',
    });

    assert.deepEqual(normalized, { font: 'hyperlegible', spacing: 'comfortable' });
});

test('normalizeReadingPreferences tolerates non-objects', () => {
    assert.deepEqual(normalizeReadingPreferences(null), {});
    assert.deepEqual(normalizeReadingPreferences('serif'), {});
    assert.deepEqual(normalizeReadingPreferences(undefined), {});
});

test('parseReadingPreferences reads stored JSON and survives broken stores', () => {
    assert.deepEqual(parseReadingPreferences('{"align":"left"}'), { align: 'left' });
    assert.deepEqual(parseReadingPreferences('not json'), {});
    assert.deepEqual(parseReadingPreferences(''), {});
    assert.deepEqual(parseReadingPreferences(null), {});
});

test('applyReadingPreferences writes one data attribute per set preference', () => {
    const root = createRootStub();

    applyReadingPreferences(root, { font: 'sans', contrast: 'high' });

    assert.deepEqual(root.attributes, {
        'data-text-font': 'sans',
        'data-text-contrast': 'high',
    });
});

test('applyReadingPreferences removes attributes the reader has not set', () => {
    const root = createRootStub({ 'data-text-font': 'sans', 'data-text-align': 'left' });

    applyReadingPreferences(root, { font: 'serif' });

    assert.deepEqual(root.attributes, { 'data-text-font': 'serif' });
});

test('an explicit normal contrast is kept, so it can opt out of prefers-contrast', () => {
    const root = createRootStub();

    applyReadingPreferences(root, { contrast: 'normal' });

    assert.equal(root.attributes['data-text-contrast'], 'normal');
});

test('an untouched contrast preference leaves the attribute off, letting the system decide', () => {
    const root = createRootStub();

    applyReadingPreferences(root, { font: 'sans' });

    assert.equal('data-text-contrast' in root.attributes, false);
});

test('every preference maps to a distinct data attribute', () => {
    const attributes = Object.values(READING_PREFERENCES).map(preference => preference.attribute);

    assert.equal(new Set(attributes).size, attributes.length);
    attributes.forEach(attribute => assert.match(attribute, /^data-text-/));
});

test('the hyperlegible font request includes the weight the bolder-text switch applies', () => {
    const requested = decodeURIComponent(HYPERLEGIBLE_FONT_HREF);

    /* The original Atkinson Hyperlegible ships 400 and 700 only, so a 600 request would
       silently resolve back to 400 and the bolder-text switch would do nothing. */
    assert.match(requested, /Atkinson\+Hyperlegible\+Next/);
    assert.match(requested, /wght@[^&]*\b600\b/);
});

test('ensureHyperlegibleFont adds the stylesheet once', () => {
    const created = [];
    const doc = {
        head: { appendChild: link => created.push(link) },
        querySelector: () => (created.length > 0 ? created[0] : null),
        createElement: () => ({
            setAttribute(name, value) {
                this[name] = value;
            },
        }),
    };

    const link = ensureHyperlegibleFont(doc);

    assert.equal(link.href, HYPERLEGIBLE_FONT_HREF);
    assert.equal(link.rel, 'stylesheet');
    assert.equal(created.length, 1);

    assert.equal(ensureHyperlegibleFont(doc), null);
    assert.equal(created.length, 1);
});
