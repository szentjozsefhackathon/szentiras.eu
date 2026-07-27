import initPdfModal from '../pdfDialog.js';
import { VerseCardDialog } from '../verseCardDialog.js';
import { initGreekWordPanel } from '../greekWordPanel.js';
import { setVerseAnalysisVisibility } from '../verseAnalysis.js';

/**
 * Toggles the inline word-by-word Hungarian translation for a single Greek verse
 * displayed on the reading page. When turned on, the first meaning of every Greek
 * word is inserted right after the word in the normal text flow, in a smaller font.
 */
function toggleVerseWordTranslation(usx, chapter, verse) {
    const selector = `.parsedVerses .greekWord.clickable-greek[data-usx="${usx}"][data-chapter="${chapter}"][data-verse="${verse}"]`;
    const words = document.querySelectorAll(selector);
    if (words.length === 0) {
        return;
    }

    const alreadyOn = [...words].some(word => {
        const next = word.nextElementSibling;
        return next && next.classList.contains('word-translation');
    });

    if (alreadyOn) {
        words.forEach(word => {
            const next = word.nextElementSibling;
            if (next && next.classList.contains('word-translation')) {
                next.remove();
            }
        });
        return;
    }

    fetch(`/ai-greek-verse/${usx}/${chapter}/${verse}`)
        .then(response => response.json())
        .then(data => {
            words.forEach(word => {
                const meaning = data[word.dataset.i];
                if (meaning && !(word.nextElementSibling && word.nextElementSibling.classList.contains('word-translation'))) {
                    const span = document.createElement('span');
                    span.className = 'word-translation';
                    span.textContent = meaning;
                    word.insertAdjacentElement('afterend', span);
                }
            });
        })
        .catch(e => {
            console.log('Error loading word translations', e);
        });
}

function initVerseAnalysisToggles() {
    document.addEventListener('click', event => {
        const button = event.target.closest('.verse-analysis-toggle');

        if (!button) {
            return;
        }

        event.preventDefault();

        const target = document.getElementById(button.getAttribute('aria-controls'));

        if (!target || button.getAttribute('aria-busy') === 'true') {
            return;
        }

        if (target.dataset.loaded === 'true') {
            const shouldExpand = target.classList.contains('d-none');
            setVerseAnalysisVisibility(button, target, shouldExpand);

            return;
        }

        const originalContent = button.innerHTML;
        button.setAttribute('aria-busy', 'true');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';

        fetch(button.dataset.url, {
            cache: 'no-cache',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Verse analysis request failed with status ${response.status}`);
                }

                return response.text();
            })
            .then(html => {
                target.innerHTML = html;
                target.dataset.loaded = 'true';
                setVerseAnalysisVisibility(button, target, true);
            })
            .catch(error => {
                console.log('Error loading verse analysis', error);
                target.innerHTML = '<span class="text-danger ms-1" role="alert">Az elemzés betöltése nem sikerült. Kérjük, próbálja újra.</span>';
                target.classList.remove('d-none');
                button.setAttribute('aria-expanded', 'true');
            })
            .finally(() => {
                button.removeAttribute('aria-busy');
                button.disabled = false;
                button.innerHTML = originalContent;
            });
    });
}

const initToggler = function () {
    var delay = 400;
    var toggles = [
        {
            storageKey: 'hideHeadings',
            selector: '.heading',
            toggleButton: '#toggleHeadings'
        },
        {
            storageKey: 'hideNumbers',
            selector: '.numv, .numchapter',
            toggleButton: '#toggleNumv'
        },
        {
            storageKey: 'hideXrefs',
            selector: '.xref',
            toggleButton: '#toggleXrefs'
        }
    ];

    toggles.forEach(function (toggle) {
        var state = localStorage.getItem(toggle.storageKey);
        if (state === 'true') {
            $(toggle.selector).addClass('hidden');
            $(toggle.toggleButton).removeClass('active').addClass('inactive');
            $(toggle.toggleButton).css({ 'background-color': 'transparent', 'color': '#0d6efd', 'border': '1px solid #0d6efd' });
        } else {
            $(toggle.selector).removeClass('hidden');
            $(toggle.toggleButton).addClass('active').removeClass('inactive');
            $(toggle.toggleButton).css({ 'background-color': '#0d6efd', 'color': 'white', 'border': '1px solid #0d6efd' });
        }

        $(toggle.toggleButton).click(function (e) {
            e.preventDefault();
            if ($(toggle.toggleButton).hasClass('active')) {
                $(toggle.selector).fadeOut(delay);
                $(toggle.selector).addClass('hidden');
                $(toggle.toggleButton).removeClass('active').addClass('inactive');
                $(toggle.toggleButton).css({ 'background-color': 'transparent', 'color': '#0d6efd', 'border': '1px solid #0d6efd' });
                localStorage.setItem(toggle.storageKey, 'true');
            } else {
                // special treatment for numv beacuse of the ai
                if (localStorage.getItem("aiToolsState") == 'true' && toggle.toggleButton == '#toggleNumv') {
                    $(".numchapter").removeClass('hidden');
                    $(".numchapter").fadeIn(delay);
                } else {
                    $(toggle.selector).removeClass('hidden');
                    $(toggle.selector).fadeIn(delay);
                }
                $(toggle.toggleButton).addClass('active').removeClass('inactive');
                $(toggle.toggleButton).css({ 'background-color': '#0d6efd', 'color': 'white', 'border': '1px solid #0d6efd' });
                localStorage.setItem(toggle.storageKey, 'false');
            }
        });
    });

    // Check if we're on the Greek New Testament page
    var isGreekPage = $('.parsedVerses.greek').length > 0;

    if (isGreekPage) {
        // Always enable AI tools for Greek New Testament page
        ai(true);
        localStorage.setItem('aiToolsState', 'true');

        // Disable the toggle functionality on Greek page only
        $('#toggleAiTools').click(function (e) {
            e.preventDefault();
            e.stopPropagation();
            // Keep AI tools always on - do nothing when clicked
            return false;
        });
    } else {
        // Normal behavior for other pages
        var aiState = localStorage.getItem('aiToolsState');
        if (aiState === 'true') {
            ai(true);
        } else {
            ai(false);
        }

        $('#toggleAiTools').click(function (e) {
            e.preventDefault();
            if ($('#toggleAiTools').hasClass('active')) {
                ai(false);
                $('#toggleAiTools').css({ 'background-color': 'transparent', 'color': '#0d6efd', 'border': '1px solid #0d6efd' });
            } else {
                ai(true);
                $('#toggleAiTools').css({ 'background-color': '#0d6efd', 'color': 'white', 'border': '1px solid #0d6efd' });
            }
        });
    }

    // Initialize place icons visibility based on AI tools state
    if (localStorage.getItem('aiToolsState') !== 'true') {
        $('button.ai-tool-element').addClass('hidden');
    }

    function ai(turnOn) {
        async function getPopoverContent(aiTrigger) {
            if (!aiTrigger.dataset.loaded) {
                aiTrigger.classList.add('loading');
                fetch(`/ai-tool/${aiTrigger.getAttribute("data-link")}`)
                    .then(response => response.json())
                    .then(data => {
                        const popover = new bootstrap.Popover(aiTrigger, {
                            trigger: 'manual',
                            html: true,
                            placement: "bottom",
                            content: data,
                            sanitize: false
                        });
                        aiTrigger.classList.remove('loading');
                        aiTrigger.dataset.loaded = true;
                        aiTrigger.addEventListener("shown.bs.popover", () => {
                            popover.tip.querySelector('.btn-close').addEventListener("click", () => {
                                popover.hide();
                            });
                            const wordTranslationButton = popover.tip.querySelector('.toggle-word-translation');
                            if (wordTranslationButton) {
                                wordTranslationButton.addEventListener("click", () => {
                                    popover.hide();
                                    toggleVerseWordTranslation(
                                        wordTranslationButton.dataset.usx,
                                        wordTranslationButton.dataset.chapter,
                                        wordTranslationButton.dataset.verse
                                    );
                                });
                            }
                            const filterRadios = popover.tip.querySelectorAll('.btn-check[name^="similarFilter"]');
                            filterRadios.forEach(radio => {
                                radio.addEventListener('change', function () {
                                    const filterValue = this.value;
                                    popover.tip.querySelectorAll('.similars-container').forEach(container => {
                                        container.style.display = container.dataset.filter === filterValue ? 'block' : 'none';
                                    });
                                });
                            });
                            const tooltipTriggerList = popover.tip.querySelectorAll(".quality[data-bs-toggle='tooltip']");
                            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
                            const greekWords = popover.tip.querySelectorAll('.greekWord');
                            [...greekWords].map(greekWord => {
                                greekWord.addEventListener("click", (event) => {
                                    const span = event.target;
                                    $(span).parent().find('.greekWord').removeClass('mark');
                                    $(span).addClass('mark');
                                    $(span).parent().find('.explanation').html('<span class="spinner-border spinner-border-sm"></span>');
                                    fetch(`/ai-greek/${span.getAttribute("data-usx")}/${span.getAttribute("data-chapter")}/${span.getAttribute("data-verse")}/${span.getAttribute("data-i")}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            $(span).parent().find('.explanation').html(data);
                                            const tooltipTriggerList = $(span).parent().find('.explanation')[0].querySelectorAll("[data-bs-toggle='tooltip']");
                                            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
                                            $(span).parent().find('a.find-all').on('click', (event) => {
                                                $('#interstitial').show();
                                            });
                                        })
                                        .catch((e) => {
                                            $(span).parent().find('.explanation').html('');
                                        });

                                });
                            });
                        });
                        popover.show();
                    })
                    .catch((e) => {
                        console.log("Error loading content", e);

                        setTimeout(() => { }, 1000);
                        aiTrigger.dataset.loaded = true;
                    });
            } else {
                const popover = bootstrap.Popover.getInstance(aiTrigger);
                popover.show();
            }
        }

        if (turnOn) {
            $('.parsedVerses span.numvai').each(function () {
                if (this.textContent.length === 0) {
                    this.textContent = this.dataset.numv + ' ';
                }
            });
            $('.parsedVerses span.numv').addClass('hidden');
            $('.parsedVerses span.numvai').removeClass('hidden');
            $('button.ai-tool-element').removeClass('hidden');
            $('#toggleAiTools').addClass('active').removeClass('inactive');
            $('#toggleAiTools').css({ 'background-color': '#0d6efd', 'color': 'white', 'border': '1px solid #0d6efd' });
            localStorage.setItem('aiToolsState', 'true');
            const aiTriggers = document.querySelectorAll("a.numvai");
            [...aiTriggers].map(aiTrigger => {
                let popover = bootstrap.Popover.getInstance(aiTrigger);
                if (!popover) {
                    $(aiTrigger).off();
                    $(aiTrigger).on("click", () => {
                        getPopoverContent(aiTrigger);
                    });
                }
            });
        } else {
            if (localStorage.getItem("hideNumbers") != 'true') {
                $('.parsedVerses span.numv').removeClass('hidden');
            }
            $('.parsedVerses span.numvai').addClass('hidden');
            $('button.ai-tool-element').addClass('hidden');
            $('#toggleAiTools').removeClass('active').addClass('inactive');
            $('#toggleAiTools').css({ 'background-color': 'transparent', 'color': '#0d6efd', 'border': '1px solid #0d6efd' });
            localStorage.setItem('aiToolsState', 'false');
        }
    }

}

function xrefPopovers() {

    async function getXrefPopoverContent(element, loadingPopover, popover) {
        if (!element.dataset.loaded) {
            loadingPopover.show();
            fetch(`/xref/${element.getAttribute("data-link")}`)
                .then(response => response.json())
                .then(data => {
                    loadingPopover.hide();
                    popover.setContent({ '.popover-body': data });
                    popover.show();
                    element.dataset.loaded = true;
                    element.addEventListener("shown.bs.popover", () => {
                        popover.tip.querySelector('.btn-close').addEventListener("click", () => {
                            popover.hide();
                        });
                    });
                })
                .catch((e) => {
                    loadingPopover.hide();
                    console.log("Error loading content", e);
                    popover.setContent({ '.popover-body': ":( Hiba a betöltés során" });
                    setTimeout(() => { popover.hide() }, 1000);
                    element.dataset.loaded = true;
                });
        } else {
            popover.show();
            element.addEventListener("shown.bs.popover", () => {
                popover.tip.querySelector('.btn-close').addEventListener("click", () => {
                    popover.hide();
                });
            });

        }
    }

    const triggers = document.querySelectorAll("a.xref");
    [...triggers].map(trigger => {
        const loadingPopover = new bootstrap.Popover(trigger,
            {
                trigger: 'click',
                placement: "auto",
                content: "Betöltés....",
            }
        );
        const popover = new bootstrap.Popover(trigger,
            {
                trigger: 'manual',
                html: true,
                placement: "auto",
                content: "Betöltés....",
                sanitize: false
            }
        );
        trigger.addEventListener("click", () => {
            getXrefPopoverContent(trigger, loadingPopover, popover);
        });
    });
}

function initQrModal() {
    const qrModal = document.getElementById('qrModal');
    if (qrModal) {
        qrModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const recipient = button.getAttribute('data-bs-view');
            fetch(`${recipient}`)
                .then(response => response.text())
                .then(data => {
                    const qrModalContent = qrModal.querySelector('.modal-content');
                    qrModalContent.innerHTML = `${data}`;
                })
                .catch((e) => {
                    console.log("Error loading content", e);
                });
        });
    }
}

function footnotePopovers() {
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));
}

function scrollToVerse() {
    const scrollTo = $('#data').data('scroll-to');
    if (scrollTo) {
        const element = document.getElementById('v_' + scrollTo);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }
}

function initPlaceMaps() {
    // Handle single map with multiple places
    const mapContainer = document.getElementById('placeMapContainer');
    const mapDataScript = document.getElementById('placeMapData');

    if (mapContainer && mapDataScript) {
        try {
            const placesData = JSON.parse(mapDataScript.textContent);

            if (placesData.length > 0) {
                // Create map centered on first place
                const map = L.map(mapContainer).setView([placesData[0].lat, placesData[0].lon], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Add scale bar (léptékjelzősáv)
                L.control.scale({
                    imperial: false,
                    metric: true,
                    position: 'bottomleft'
                }).addTo(map);

                // Add markers for all places
                const markers = [];
                placesData.forEach((place) => {
                    const marker = L.marker([place.lat, place.lon], {
                        title: place.name
                    })
                        .bindPopup(place.name)
                        .bindTooltip(place.name, {
                            permanent: true,
                            direction: 'top',
                            offset: [0, -10]
                        })
                        .addTo(map);
                    markers.push(marker);
                });

                // Fit map bounds to all markers if multiple places
                if (markers.length > 1) {
                    const group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds(), { padding: [50, 50] });
                }
            }
        } catch (e) {
            console.log("Error initializing place map", e);
        }
    }

    // Handle legacy individual place maps (if any)
    document.querySelectorAll('.place-map:not(#placeMapContainer)').forEach((mapEl) => {
        const lat = parseFloat(mapEl.dataset.lat);
        const lon = parseFloat(mapEl.dataset.lon);
        if (!isNaN(lat) && !isNaN(lon)) {
            const map = L.map(mapEl).setView([lat, lon], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Add scale bar (léptékjelzősáv) to individual maps
            L.control.scale({
                imperial: false,
                metric: true,
                position: 'bottomleft'
            }).addTo(map);
            
            L.marker([lat, lon]).addTo(map);
        }
    });
}

function initPlaceModal() {
    const placeModal = document.getElementById('placeModal');
    if (placeModal) {
        placeModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const placeIds = button.getAttribute('data-place-ids');
            const placeModalBody = document.getElementById('placeModalBody');

            if (placeIds) {
                fetch(`/place/${placeIds}`)
                    .then(response => response.json())
                    .then(data => {
                        placeModalBody.innerHTML = data;
                        // Wait for modal to be fully shown before initializing map
                        setTimeout(() => {
                            initPlaceMaps();
                            // Invalidate map size to ensure proper centering
                            const mapContainer = document.getElementById('placeMapContainer');
                            if (mapContainer && mapContainer._leaflet_map) {
                                mapContainer._leaflet_map.invalidateSize();
                            }
                        }, 100);
                    })
                    .catch((e) => {
                        console.log("Error loading place content", e);
                        placeModalBody.innerHTML = '<p class="text-danger">Hiba a hely adatainak betöltésekor</p>';
                    });
            }
        });
    }
}
function initVerseCardModal() {
    const verseCardModal = document.getElementById('verseCardModal');
    let opener = null;
    if (verseCardModal) {
        verseCardModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            opener = button;
            const recipient = button.getAttribute('data-bs-view');
            fetch(`${recipient}`)
                .then(response => response.text())
                .then(data => {
                    const verseCardModalContent = verseCardModal.querySelector('.modal-content');
                    verseCardModalContent.innerHTML = `${data}`;
                    // Reinitialize VerseCardDialog after content is loaded
                    new VerseCardDialog();
                })
                .catch((e) => {
                    console.log("Error loading verse card dialog", e);
                });
        });
        verseCardModal.addEventListener('hide.bs.modal', () => {
            const active = document.activeElement;
            if (active && verseCardModal.contains(active)) {
                active.blur(); // simplest + very effective
            }
        });
        verseCardModal.addEventListener('hidden.bs.modal', () => {
            (opener || document.getElementById('openVerseCardButton'))?.focus();
        });

    }
}

initToggler();
footnotePopovers();
xrefPopovers();
initQrModal();
initPdfModal();
initPlaceModal();
initVerseCardModal();

// Initialize media button styling
function initMediaButtonStyling() {
    const mediaButton = document.getElementById('mediaButton');
    if (mediaButton) {
        const isMediaEnabled = mediaButton.getAttribute('href').includes('?media') || mediaButton.getAttribute('href').includes('&media');
        if (isMediaEnabled) {
            $(mediaButton).addClass('inactive').removeClass('active');
            // Media is currently enabled, so button should show "on" state
            $(mediaButton).css({ 'background-color': 'transparent', 'color': '#0d6efd', 'border': '1px solid #0d6efd' });
        } else {
            $(mediaButton).addClass('active').removeClass('inactive');
            // Media is currently disabled, so button should show "off" state
            $(mediaButton).css({ 'background-color': '#0d6efd', 'color': 'white', 'border': '1px solid #0d6efd' });
        }
    }
}

initMediaButtonStyling();
initGreekWordPanel();
initVerseAnalysisToggles();

scrollToVerse();
