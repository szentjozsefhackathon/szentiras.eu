export function setVerseAnalysisVisibility(button, target, shouldExpand, documentRoot = document) {
    const verseText = documentRoot.getElementById(button.dataset.replaces);

    target.classList.toggle('d-none', !shouldExpand);

    if (verseText) {
        verseText.classList.toggle('d-none', shouldExpand);

        if (shouldExpand) {
            verseText.setAttribute('aria-hidden', 'true');
        } else {
            verseText.removeAttribute('aria-hidden');
        }
    }

    button.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
    button.title = shouldExpand ? 'Elemzés elrejtése' : 'Vers elemzése';
}
