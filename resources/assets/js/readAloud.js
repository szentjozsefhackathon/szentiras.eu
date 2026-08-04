/**
 * Reads the displayed scripture text aloud with the browser's built in speech synthesis.
 *
 * The text is cut into one utterance per verse, using the invisible `.verse-anchor` links
 * that `parsedVerseContainer.twig` emits in front of every verse. Speaking verse by verse
 * keeps the currently read passage highlightable and lets pause/resume land on a sensible
 * boundary even in engines that cannot resume mid-utterance.
 */

const SPEAKING_CLASS = 'speaking';

/**
 * Elements whose subtree must not be spoken: verse and chapter numbers, cross references,
 * the Greek original, footnote blocks and the various tool affordances.
 *
 * @type {string[]}
 */
const IGNORED_CLASSES = [
    'numv',
    'numvai',
    'numchapter',
    'xref',
    'footnote',
    'footnotes',
    'place-icon',
    'word-translation',
    'greek',
    'greekWord',
    'verse-analysis',
    'commentary-container',
];

/** @type {string[]} */
const IGNORED_TAGS = ['SCRIPT', 'STYLE', 'BUTTON', 'FIGURE', 'IMG', 'SUP', 'DETAILS'];

const TEXT_NODE = 3;
const ELEMENT_NODE = 1;

function hasIgnoredClass(element) {
    if (!element.classList) {
        return false;
    }

    return IGNORED_CLASSES.some(className => element.classList.contains(className));
}

function isIgnored(element) {
    const tagName = (element.tagName || '').toUpperCase();

    return IGNORED_TAGS.indexOf(tagName) !== -1 || hasIgnoredClass(element);
}

function isVerseBoundary(element) {
    return Boolean(element.classList && element.classList.contains('verse-anchor'));
}

/**
 * Walks a rendered passage and collects the speakable segments in reading order.
 *
 * A new segment starts at every verse anchor; text that precedes the first anchor (section
 * headings, mostly) forms its own segment so headings are read before the verses they head.
 *
 * @param {object} root Element to walk.
 * @return {Array<{text: string, elements: object[]}>}
 */
export function collectSpeechSegments(root) {
    const segments = [];
    let current = null;

    const startSegment = () => {
        current = { text: '', elements: [] };
        segments.push(current);
    };

    const addText = (value, parent) => {
        const chunk = value.replace(/\s+/g, ' ');
        if (chunk === '') {
            return;
        }

        /* Whitespace between two verse parts keeps their words apart but is not content of
           its own, so it never opens a segment nor marks an element as highlightable. */
        if (chunk.trim() === '') {
            if (current && current.text !== '') {
                current.text += ' ';
            }
            return;
        }

        if (!current) {
            startSegment();
        }

        current.text += chunk;
        if (parent && current.elements.indexOf(parent) === -1) {
            current.elements.push(parent);
        }
    };

    const walk = node => {
        const children = node.childNodes || [];
        for (let i = 0; i < children.length; i++) {
            const child = children[i];

            if (child.nodeType === TEXT_NODE) {
                addText(child.nodeValue || '', node);
                continue;
            }

            if (child.nodeType !== ELEMENT_NODE) {
                continue;
            }

            if (isVerseBoundary(child)) {
                startSegment();
                continue;
            }

            if (isIgnored(child)) {
                continue;
            }

            walk(child);
        }
    };

    walk(root);

    return segments
        .map(segment => ({
            text: segment.text.replace(/\s+/g, ' ').trim(),
            elements: segment.elements,
        }))
        .filter(segment => segment.text !== '');
}

/**
 * Picks the most suitable Hungarian voice, or null when the platform offers none.
 */
export function pickVoice(voices, language = 'hu') {
    if (!voices || voices.length === 0) {
        return null;
    }

    const matching = voices.filter(voice => (voice.lang || '').toLowerCase().startsWith(language));
    if (matching.length === 0) {
        return null;
    }

    return matching.find(voice => voice.localService) || matching[0];
}

/**
 * Wires up the read aloud button. Does nothing when the browser has no speech synthesis or
 * when the page carries no readable passage.
 */
export function initReadAloud() {
    const button = document.getElementById('readAloudButton');
    if (!button) {
        return;
    }

    const synthesis = window.speechSynthesis;
    if (!synthesis || typeof window.SpeechSynthesisUtterance !== 'function') {
        button.remove();
        return;
    }

    /* Footnote blocks reuse the `.parsedVerses` class, and the Greek original has no
       Hungarian voice to read it with — neither belongs in the spoken text. */
    const passages = [...document.querySelectorAll('.parsedVerses')]
        .filter(passage => !passage.classList.contains('footnotes'))
        .filter(passage => !passage.classList.contains('greek'));

    const segments = passages.flatMap(passage => collectSpeechSegments(passage));
    if (segments.length === 0) {
        button.remove();
        return;
    }

    const label = button.querySelector('.read-aloud-label');
    const icon = button.querySelector('.read-aloud-icon');
    const stopButton = document.getElementById('readAloudStopButton');
    const status = document.getElementById('readAloudStatus');

    let index = 0;
    let speaking = false;

    /**
     * The button is icon only, so the accessible name lives in aria-label. The optional
     * label span is kept for callers that render a visible caption.
     */
    const setLabel = (text, iconClass) => {
        if (label) {
            label.textContent = text;
        }
        button.setAttribute('aria-label', text);
        button.setAttribute('title', text);
        if (icon) {
            icon.className = `read-aloud-icon bi ${iconClass}`;
        }
    };

    const announce = message => {
        if (status) {
            status.textContent = message;
        }
    };

    const clearHighlight = () => {
        document.querySelectorAll(`.${SPEAKING_CLASS}`).forEach(element => {
            element.classList.remove(SPEAKING_CLASS);
        });
    };

    const highlight = segment => {
        clearHighlight();
        segment.elements.forEach(element => {
            if (element.classList) {
                element.classList.add(SPEAKING_CLASS);
            }
        });

        const first = segment.elements[0];
        if (first && typeof first.scrollIntoView === 'function') {
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    };

    const idle = () => {
        speaking = false;
        index = 0;
        clearHighlight();
        setLabel('Felolvasás', 'bi-volume-up-fill');
        button.setAttribute('aria-pressed', 'false');
        if (stopButton) {
            stopButton.classList.add('d-none');
        }
    };

    const speakFrom = position => {
        if (position >= segments.length) {
            idle();
            announce('A felolvasás véget ért.');
            return;
        }

        index = position;
        const segment = segments[position];
        highlight(segment);

        const utterance = new window.SpeechSynthesisUtterance(segment.text);
        utterance.lang = 'hu-HU';
        const voice = pickVoice(synthesis.getVoices());
        if (voice) {
            utterance.voice = voice;
        }
        utterance.onend = () => {
            if (speaking) {
                speakFrom(position + 1);
            }
        };
        utterance.onerror = () => {
            idle();
            announce('A felolvasás megszakadt.');
        };

        synthesis.speak(utterance);
    };

    const start = () => {
        speaking = true;
        setLabel('Felolvasás szüneteltetése', 'bi-pause-fill');
        button.setAttribute('aria-pressed', 'true');
        if (stopButton) {
            stopButton.classList.remove('d-none');
        }
        announce('Felolvasás elindult.');
        speakFrom(index);
    };

    button.addEventListener('click', () => {
        if (!speaking) {
            start();
            return;
        }

        if (synthesis.paused) {
            synthesis.resume();
            setLabel('Felolvasás szüneteltetése', 'bi-pause-fill');
            announce('Felolvasás folytatódik.');
        } else {
            synthesis.pause();
            setLabel('Felolvasás folytatása', 'bi-volume-up-fill');
            announce('Felolvasás szüneteltetve.');
        }
    });

    if (stopButton) {
        stopButton.addEventListener('click', () => {
            synthesis.cancel();
            idle();
            announce('Felolvasás leállítva.');
        });
    }

    /* Leaving the page mid-sentence otherwise keeps the engine talking in some browsers. */
    window.addEventListener('pagehide', () => synthesis.cancel());

    idle();
}
