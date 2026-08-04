import assert from 'node:assert/strict';
import test from 'node:test';
import { collectSpeechSegments, pickVoice } from '../../resources/assets/js/readAloud.js';

function textNode(value) {
    return { nodeType: 3, nodeValue: value };
}

function element(tagName, classes = [], children = []) {
    return {
        nodeType: 1,
        tagName,
        classList: { contains: className => classes.includes(className) },
        childNodes: children,
    };
}

/** The verse number markup emitted by parsedVerseContainer.twig. */
function verseNumber(number) {
    return element('SPAN', ['numv'], [element('SUP', [], [textNode(String(number))])]);
}

function verseAnchor() {
    return element('A', ['verse-anchor'], []);
}

test('a plain passage yields one segment per verse, without numbers or cross references', () => {
    const passage = element('DIV', ['parsedVerses'], [
        element('H4', [], [textNode('A teremtés')]),
        verseAnchor(),
        verseNumber(1),
        element('SPAN', [], [textNode('Kezdetben teremtette Isten a mennyet és a földet. ')]),
        element('SPAN', ['xref'], [element('SUP', [], [textNode('(Jn 1,1)')])]),
        verseAnchor(),
        verseNumber(2),
        element('SPAN', [], [textNode('A föld még kietlen és puszta volt. ')]),
    ]);

    const segments = collectSpeechSegments(passage);

    assert.deepEqual(segments.map(segment => segment.text), [
        'A teremtés',
        'Kezdetben teremtette Isten a mennyet és a földet.',
        'A föld még kietlen és puszta volt.',
    ]);
});

test('verse parts split across several spans keep the word boundary', () => {
    const passage = element('DIV', ['parsedVerses'], [
        verseAnchor(),
        verseNumber(1),
        element('SPAN', [], [textNode('Az Úr az én pásztorom, ')]),
        textNode('\n            '),
        element('SPAN', [], [textNode('nem szűkölködöm. ')]),
    ]);

    const segments = collectSpeechSegments(passage);

    assert.equal(segments.length, 1);
    assert.equal(segments[0].text, 'Az Úr az én pásztorom, nem szűkölködöm.');
});

test('poetry nested in a paragraph is still split per verse', () => {
    const passage = element('DIV', ['parsedVerses'], [
        element('P', ['poem'], [
            verseAnchor(),
            verseNumber(1),
            textNode('Boldog ember az, aki nem jár a bűnösök tanácsa szerint,'),
        ]),
        element('P', ['poem'], [
            verseAnchor(),
            verseNumber(2),
            textNode('hanem az Úr törvényében gyönyörködik.'),
        ]),
    ]);

    const segments = collectSpeechSegments(passage);

    assert.deepEqual(segments.map(segment => segment.text), [
        'Boldog ember az, aki nem jár a bűnösök tanácsa szerint,',
        'hanem az Úr törvényében gyönyörködik.',
    ]);
});

test('footnote markers, place buttons and the Greek text are not spoken', () => {
    const passage = element('DIV', ['parsedVerses'], [
        verseAnchor(),
        element('A', ['footnote'], [
            element('SPAN', ['numv', 'footnote'], [element('SUP', [], [textNode('3')])]),
        ]),
        element('SPAN', [], [textNode('Menj el Kánaán földjére. ')]),
        element('BUTTON', ['place-icon'], [textNode('Helyek: Kánaán')]),
        element('SPAN', ['greekWord'], [textNode('ἐν ἀρχῇ')]),
        element('SPAN', ['word-translation'], [textNode('kezdetben')]),
    ]);

    const segments = collectSpeechSegments(passage);

    assert.deepEqual(segments.map(segment => segment.text), ['Menj el Kánaán földjére.']);
});

test('chapter numbers are skipped so the reading does not start with a bare numeral', () => {
    const passage = element('DIV', ['parsedVerses'], [
        element('SPAN', ['numchapter'], [textNode('23')]),
        verseAnchor(),
        verseNumber(1),
        element('SPAN', [], [textNode('Az Úr az én pásztorom.')]),
    ]);

    assert.deepEqual(
        collectSpeechSegments(passage).map(segment => segment.text),
        ['Az Úr az én pásztorom.'],
    );
});

test('each segment records the elements to highlight while it is read', () => {
    const firstPart = element('SPAN', [], [textNode('Az Úr az én pásztorom, ')]);
    const secondPart = element('SPAN', [], [textNode('nem szűkölködöm. ')]);
    const passage = element('DIV', ['parsedVerses'], [
        verseAnchor(),
        verseNumber(1),
        firstPart,
        secondPart,
    ]);

    const segments = collectSpeechSegments(passage);

    assert.deepEqual(segments[0].elements, [firstPart, secondPart]);
});

test('a passage without any text yields no segments', () => {
    const passage = element('DIV', ['parsedVerses'], [
        element('SPAN', ['numchapter'], [textNode('1')]),
        verseAnchor(),
        verseNumber(1),
    ]);

    assert.deepEqual(collectSpeechSegments(passage), []);
});

test('pickVoice prefers a locally installed Hungarian voice', () => {
    const voices = [
        { lang: 'en-US', localService: true },
        { lang: 'hu-HU', localService: false },
        { lang: 'hu-HU', localService: true, name: 'Szonja' },
    ];

    assert.equal(pickVoice(voices).name, 'Szonja');
});

test('pickVoice falls back to any Hungarian voice', () => {
    const voices = [{ lang: 'en-GB', localService: true }, { lang: 'hu', localService: false }];

    assert.equal(pickVoice(voices).lang, 'hu');
});

test('pickVoice returns null when the platform has no Hungarian voice', () => {
    assert.equal(pickVoice([{ lang: 'en-US' }, { lang: 'de-DE' }]), null);
    assert.equal(pickVoice([]), null);
});
