import assert from 'node:assert/strict';
import test from 'node:test';
import { setVerseAnalysisVisibility } from '../../resources/assets/js/verseAnalysis.js';

function createClassList(initialClasses = []) {
    const classes = new Set(initialClasses);

    return {
        contains(className) {
            return classes.has(className);
        },
        toggle(className, force) {
            if (force) {
                classes.add(className);
            } else {
                classes.delete(className);
            }
        },
    };
}

function createElement(initialClasses = []) {
    const attributes = new Map();

    return {
        classList: createClassList(initialClasses),
        dataset: {},
        title: '',
        getAttribute(name) {
            return attributes.get(name);
        },
        removeAttribute(name) {
            attributes.delete(name);
        },
        setAttribute(name, value) {
            attributes.set(name, value);
        },
    };
}

test('the analysis replaces the verse text and restores it when collapsed', () => {
    const button = createElement();
    const target = createElement(['d-none']);
    const verseText = createElement();
    const documentRoot = {
        getElementById(id) {
            return id === 'greek-verse-text-MAT_1_1' ? verseText : null;
        },
    };
    button.dataset.replaces = 'greek-verse-text-MAT_1_1';

    setVerseAnalysisVisibility(button, target, true, documentRoot);

    assert.equal(target.classList.contains('d-none'), false);
    assert.equal(verseText.classList.contains('d-none'), true);
    assert.equal(verseText.getAttribute('aria-hidden'), 'true');
    assert.equal(button.getAttribute('aria-expanded'), 'true');
    assert.equal(button.title, 'Elemzés elrejtése');

    setVerseAnalysisVisibility(button, target, false, documentRoot);

    assert.equal(target.classList.contains('d-none'), true);
    assert.equal(verseText.classList.contains('d-none'), false);
    assert.equal(verseText.getAttribute('aria-hidden'), undefined);
    assert.equal(button.getAttribute('aria-expanded'), 'false');
    assert.equal(button.title, 'Vers elemzése');
});

test('the analysis remains usable when the original verse element is unavailable', () => {
    const button = createElement();
    const target = createElement(['d-none']);
    const documentRoot = {
        getElementById() {
            return null;
        },
    };

    setVerseAnalysisVisibility(button, target, true, documentRoot);

    assert.equal(target.classList.contains('d-none'), false);
    assert.equal(button.getAttribute('aria-expanded'), 'true');
});
