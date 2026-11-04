/**
 * The meaning search of /ai-search submits its form by fetch and reads the answer as a stream of
 * newline-delimited JSON events, so the visitor sees which step the search is at (and how long it
 * has taken) instead of a bare "Kis türelmet". The form's own action is only for visitors without
 * scripts.
 */

const STEP_ORDER = ['send', 'search', 'verses'];

const ICONS = {
    pending: '<i class="bi bi-circle"></i>',
    running: '<span class="spinner-border spinner-border-sm" role="status"></span>',
    done: '<i class="bi bi-check-circle-fill text-success"></i>',
    failed: '<i class="bi bi-x-circle-fill text-danger"></i>',
};

const MESSAGES = {
    network: 'Nem sikerült elküldeni a kérdést. Ellenőrizd az internetkapcsolatot, és próbáld újra!',
    expired: 'Lejárt a munkamenet. Töltsd újra az oldalt, és keress újra!',
    server: 'Hiba történt a keresés közben. Próbáld újra néhány perc múlva!',
};

function formatSeconds(milliseconds) {
    return `${(milliseconds / 1000).toFixed(1).replace('.', ',')} mp`;
}

class SearchProgress {
    constructor(panel) {
        this.panel = panel;
        this.current = null;
        this.startedAt = 0;
        this.timer = null;
        panel.querySelectorAll('[data-step]').forEach((item) => this.paint(item, 'pending', ''));
        panel.querySelector('.stepError').classList.add('d-none');
        panel.classList.remove('d-none');
    }

    item(step) {
        return this.panel.querySelector(`[data-step="${step}"]`);
    }

    paint(item, state, time) {
        item.querySelector('.stepIcon').innerHTML = ICONS[state];
        item.querySelector('.stepTime').textContent = time ? `(${time})` : '';
        item.classList.toggle('text-muted', state === 'pending');
        item.classList.toggle('fw-semibold', state === 'running');
    }

    /** Starts a step; the steps before it are done. */
    start(step) {
        this.finishCurrent('done');
        STEP_ORDER.slice(0, STEP_ORDER.indexOf(step)).forEach((earlier) => {
            const item = this.item(earlier);
            if (item.querySelector('.bi-circle')) {
                this.paint(item, 'done', '');
            }
        });
        this.current = step;
        this.startedAt = performance.now();
        this.paint(this.item(step), 'running', '');
        this.timer = setInterval(() => {
            const elapsed = performance.now() - this.startedAt;
            if (elapsed >= 1000) {
                this.item(step).querySelector('.stepTime').textContent = `(${Math.floor(elapsed / 1000)} mp)`;
            }
        }, 250);
    }

    finishCurrent(state) {
        clearInterval(this.timer);
        if (this.current !== null) {
            this.paint(this.item(this.current), state, formatSeconds(performance.now() - this.startedAt));
        }
        this.current = null;
    }

    fail(message) {
        this.finishCurrent('failed');
        const error = this.panel.querySelector('.stepError');
        error.textContent = message;
        error.classList.remove('d-none');
    }
}

function initTooltips(container) {
    container.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => new bootstrap.Tooltip(element));
}

/**
 * @returns {Promise<boolean>} whether a result (or a redirect) arrived
 */
async function readEvents(response, progress) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let answered = false;
    const handle = (line) => {
        if (line.trim() === '') {
            return;
        }
        const event = JSON.parse(line);
        if (event.event === 'step') {
            progress.start(event.step);
        } else if (event.event === 'redirect') {
            answered = true;
            progress.finishCurrent('done');
            window.location.href = event.url;
        } else if (event.event === 'result') {
            answered = true;
            progress.finishCurrent('done');
            const main = document.getElementById('semanticSearchMain');
            main.innerHTML = event.html;
            initTooltips(main);
        }
    };
    for (;;) {
        const { value, done } = await reader.read();
        if (done) {
            break;
        }
        buffer += decoder.decode(value, { stream: true });
        let newline;
        while ((newline = buffer.indexOf('\n')) >= 0) {
            handle(buffer.slice(0, newline));
            buffer = buffer.slice(newline + 1);
        }
    }
    handle(buffer + decoder.decode());

    return answered;
}

async function errorMessageOf(response) {
    if (response.status === 419) {
        return MESSAGES.expired;
    }
    if (response.status === 422) {
        try {
            const body = await response.json();
            const firstErrors = Object.values(body.errors ?? {})[0];
            return firstErrors?.[0] ?? body.message ?? MESSAGES.server;
        } catch {
            return MESSAGES.server;
        }
    }

    return MESSAGES.server;
}

async function search(form) {
    const submitButton = form.querySelector('[type="submit"]');
    const results = document.getElementById('semanticSearchResults');
    const progress = new SearchProgress(document.getElementById('semanticSearchProgress'));
    submitButton.disabled = true;
    results?.classList.add('d-none');
    progress.start('send');

    try {
        const response = await fetch(form.dataset.streamAction, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            progress.fail(await errorMessageOf(response));
            window.turnstile?.reset();
        } else if (!(await readEvents(response, progress))) {
            progress.fail(MESSAGES.server);
        }
    } catch {
        progress.fail(MESSAGES.network);
    } finally {
        submitButton.disabled = false;
    }
}

$(document).on('submit', '#semanticSearchForm', function (event) {
    event.preventDefault();
    search(this);
});
