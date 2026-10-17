/**
 * Bildergalerie der Vereinshomepage: Links mit data-lightbox="gruppe" öffnen
 * das Großbild im selben Fenster als Vollbild-Ansicht mit Vor/Zurück
 * (Buttons, Pfeiltasten, Wischen). Ohne JavaScript öffnet der Link das Bild.
 */

const ICONS = {
    prev: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>',
    next: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>',
    close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    fullscreen: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>',
};

let overlay = null;
let items = [];
let index = 0;
let trigger = null;
let touchStartX = null;

function button(className, label, icon) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = 'hp-lightbox__button ' + className;
    element.setAttribute('aria-label', label);
    element.title = label;
    element.innerHTML = icon;

    return element;
}

function build() {
    overlay = document.createElement('div');
    overlay.className = 'hp-lightbox';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Bildanzeige');
    overlay.hidden = true;

    overlay.innerHTML = `
        <div class="hp-lightbox__bar">
            <span class="hp-lightbox__counter" aria-live="polite"></span>
            <span class="hp-lightbox__actions"></span>
        </div>
        <div class="hp-lightbox__stage">
            <div class="hp-lightbox__spinner" aria-hidden="true"></div>
            <img class="hp-lightbox__image" alt="">
        </div>
        <p class="hp-lightbox__caption"></p>
    `;

    const actions = overlay.querySelector('.hp-lightbox__actions');

    if (document.fullscreenEnabled) {
        const fullscreenButton = button('hp-lightbox__fullscreen', 'Vollbild', ICONS.fullscreen);
        fullscreenButton.addEventListener('click', toggleFullscreen);
        actions.append(fullscreenButton);
    }

    const closeButton = button('hp-lightbox__close', 'Schließen (Esc)', ICONS.close);
    closeButton.addEventListener('click', close);
    actions.append(closeButton);

    const prevButton = button('hp-lightbox__nav hp-lightbox__nav--prev', 'Vorheriges Bild', ICONS.prev);
    prevButton.addEventListener('click', () => show(index - 1));

    const nextButton = button('hp-lightbox__nav hp-lightbox__nav--next', 'Nächstes Bild', ICONS.next);
    nextButton.addEventListener('click', () => show(index + 1));

    overlay.append(prevButton, nextButton);

    const image = overlay.querySelector('.hp-lightbox__image');
    image.addEventListener('load', () => overlay.classList.remove('is-loading'));
    image.addEventListener('error', () => overlay.classList.remove('is-loading'));

    // Klick neben das Bild schließt.
    overlay.querySelector('.hp-lightbox__stage').addEventListener('click', (event) => {
        if (event.target === event.currentTarget) {
            close();
        }
    });

    overlay.addEventListener('touchstart', (event) => {
        touchStartX = event.touches.length === 1 ? event.touches[0].clientX : null;
    }, { passive: true });

    overlay.addEventListener('touchend', (event) => {
        if (touchStartX === null) {
            return;
        }

        const distance = event.changedTouches[0].clientX - touchStartX;
        touchStartX = null;

        if (Math.abs(distance) > 50) {
            show(index + (distance < 0 ? 1 : -1));
        }
    });

    document.body.append(overlay);
}

function show(newIndex) {
    index = (newIndex + items.length) % items.length;

    const item = items[index];
    const image = overlay.querySelector('.hp-lightbox__image');
    const caption = overlay.querySelector('.hp-lightbox__caption');

    overlay.classList.add('is-loading');
    image.src = item.href;
    image.alt = item.caption || '';
    caption.textContent = item.caption || '';
    caption.hidden = !item.caption;
    overlay.querySelector('.hp-lightbox__counter').textContent = items.length > 1 ? `${index + 1} / ${items.length}` : '';

    // Nachbarbilder vorladen, damit das Weiterschalten sofort klappt.
    [index + 1, index - 1].forEach((neighbour) => {
        const next = items[(neighbour + items.length) % items.length];
        new Image().src = next.href;
    });
}

function open(link) {
    if (!overlay) {
        build();
    }

    const group = link.dataset.lightbox;
    const links = [...document.querySelectorAll(`a[data-lightbox="${CSS.escape(group)}"]`)];

    // Doppelte Bilder (z. B. Titelbild oben und in der Galerie) nur einmal zeigen.
    const seen = new Set();
    items = links
        .map((element) => ({ href: element.href, caption: element.dataset.caption || '' }))
        .filter((item) => !seen.has(item.href) && seen.add(item.href));

    trigger = link;
    overlay.classList.toggle('is-single', items.length < 2);
    overlay.hidden = false;
    document.documentElement.classList.add('hp-lightbox-open');
    document.addEventListener('keydown', onKeydown);

    show(Math.max(0, items.findIndex((item) => item.href === link.href)));
    overlay.querySelector('.hp-lightbox__close').focus({ preventScroll: true });
}

function close() {
    if (!overlay || overlay.hidden) {
        return;
    }

    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    }

    overlay.hidden = true;
    overlay.querySelector('.hp-lightbox__image').removeAttribute('src');
    document.documentElement.classList.remove('hp-lightbox-open');
    document.removeEventListener('keydown', onKeydown);
    trigger?.focus({ preventScroll: true });
}

function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen().catch(() => {});
    } else {
        overlay.requestFullscreen().catch(() => {});
    }
}

function onKeydown(event) {
    const actions = {
        Escape: close,
        ArrowLeft: () => show(index - 1),
        ArrowRight: () => show(index + 1),
        Home: () => show(0),
        End: () => show(items.length - 1),
    };

    if (actions[event.key]) {
        event.preventDefault();
        actions[event.key]();
    }
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-lightbox]');

    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    open(link);
});
