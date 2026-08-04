import assert from 'node:assert/strict';
import test from 'node:test';
import {
    FONT_SCALES,
    applyFontScale,
    formatFontScale,
    normalizeFontScale,
    stepFontScale,
} from '../../resources/assets/js/fontScale.js';

test('the smallest selectable scale is the untouched browser default', () => {
    assert.equal(FONT_SCALES[0], 1);
});

test('normalizeFontScale keeps valid stored values', () => {
    assert.equal(normalizeFontScale('1.3'), 1.3);
    assert.equal(normalizeFontScale(1.75), 1.75);
});

test('normalizeFontScale snaps unknown values to the closest selectable scale', () => {
    assert.equal(normalizeFontScale('1.2'), 1.15);
    assert.equal(normalizeFontScale('1.45'), 1.5);
    assert.equal(normalizeFontScale('5'), 2);
});

test('normalizeFontScale falls back to the default for garbage', () => {
    assert.equal(normalizeFontScale(null), 1);
    assert.equal(normalizeFontScale(''), 1);
    assert.equal(normalizeFontScale('nagyobb'), 1);
    assert.equal(normalizeFontScale(undefined), 1);
});

test('stepFontScale walks the scale in both directions', () => {
    assert.equal(stepFontScale(1, 1), 1.15);
    assert.equal(stepFontScale(1.15, 1), 1.3);
    assert.equal(stepFontScale(1.3, -1), 1.15);
});

test('stepFontScale clamps at both ends instead of wrapping around', () => {
    assert.equal(stepFontScale(1, -1), 1);
    assert.equal(stepFontScale(2, 1), 2);
});

test('formatFontScale renders a percentage without floating point noise', () => {
    assert.equal(formatFontScale(1), '100%');
    assert.equal(formatFontScale(1.15), '115%');
    assert.equal(formatFontScale(1.75), '175%');
});

function createRootStub(properties = {}) {
    const values = { ...properties };

    return {
        properties: values,
        style: {
            setProperty(name, value) {
                values[name] = value;
            },
            removeProperty(name) {
                delete values[name];
            },
        },
    };
}

test('applyFontScale scales only the scripture text, in rem so it stacks on the browser default', () => {
    const root = createRootStub();

    assert.equal(applyFontScale(root, 1.5), 1.5);
    assert.deepEqual(root.properties, { '--book-font-size': '1.5rem' });
});

test('applyFontScale removes the override at the default scale so the stylesheet wins', () => {
    const root = createRootStub({ '--book-font-size': '1.5rem' });

    assert.equal(applyFontScale(root, 1), 1);
    assert.deepEqual(root.properties, {});
});

test('applyFontScale never touches the root font size, which would resize the whole layout', () => {
    const root = createRootStub();
    root.style.fontSize = '';

    applyFontScale(root, 2);

    assert.equal(root.style.fontSize, '');
});
