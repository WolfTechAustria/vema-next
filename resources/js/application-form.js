/*
 * Online-Mitgliedsanmeldung: Unterschriftsfeld (Canvas, Finger/Maus/Stift)
 * und Cloudflare-Turnstile-Captcha.
 */
const TURNSTILE_SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let turnstileLoader = null;

function loadTurnstile() {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }

    turnstileLoader ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = TURNSTILE_SCRIPT;
        script.async = true;
        script.onload = () => resolve(window.turnstile);
        script.onerror = reject;
        document.head.appendChild(script);
    });

    return turnstileLoader;
}

export function registerApplicationForm(Alpine) {

    /*
     * Unterschrift: zeichnet mit Pointer Events und übergibt das Bild als
     * PNG-Data-URL an die entangelte Livewire-Eigenschaft (leer = nicht
     * unterschrieben).
     */
    Alpine.data('signaturePad', (model) => ({

        value: model,
        drawing: false,
        hasInk: false,
        context: null,

        init() {
            const canvas = this.$refs.canvas;

            this.resize();
            this.value = '';

            canvas.addEventListener('pointerdown', (event) => this.start(event));
            canvas.addEventListener('pointermove', (event) => this.move(event));
            ['pointerup', 'pointerleave', 'pointercancel'].forEach((name) => {
                canvas.addEventListener(name, () => this.end());
            });

            window.addEventListener('resize', () => {
                if (! this.hasInk) {
                    this.resize();
                }
            });
        },

        resize() {
            const canvas = this.$refs.canvas;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);

            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;

            this.context = canvas.getContext('2d');
            this.context.scale(ratio, ratio);
            this.context.lineWidth = 2.2;
            this.context.lineCap = 'round';
            this.context.lineJoin = 'round';
            this.context.strokeStyle = '#0f172a';
        },

        point(event) {
            const rect = this.$refs.canvas.getBoundingClientRect();

            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        },

        start(event) {
            event.preventDefault();
            this.$refs.canvas.setPointerCapture?.(event.pointerId);
            this.drawing = true;

            const { x, y } = this.point(event);
            this.context.beginPath();
            this.context.moveTo(x, y);
            this.context.lineTo(x + 0.1, y + 0.1);
            this.context.stroke();
            this.hasInk = true;
        },

        move(event) {
            if (! this.drawing) {
                return;
            }

            event.preventDefault();

            const { x, y } = this.point(event);
            this.context.lineTo(x, y);
            this.context.stroke();
        },

        end() {
            if (! this.drawing) {
                return;
            }

            this.drawing = false;
            this.value = this.hasInk ? this.$refs.canvas.toDataURL('image/png') : '';
        },

        clear() {
            const canvas = this.$refs.canvas;

            this.context.save();
            this.context.setTransform(1, 0, 0, 1, 0, 0);
            this.context.clearRect(0, 0, canvas.width, canvas.height);
            this.context.restore();

            this.hasInk = false;
            this.value = '';
        },

    }));

    /*
     * Turnstile-Widget: lädt das Skript erst bei Bedarf und meldet das
     * Token an Livewire.
     */
    Alpine.data('turnstileWidget', (siteKey, onToken) => ({

        widgetId: null,

        init() {
            loadTurnstile().then((turnstile) => {
                this.widgetId = turnstile.render(this.$refs.box, {
                    sitekey: siteKey,
                    language: 'de',
                    callback: (token) => onToken(token),
                    'expired-callback': () => onToken(''),
                    'error-callback': () => onToken(''),
                });
            });
        },

        reset() {
            if (window.turnstile && this.widgetId !== null) {
                window.turnstile.reset(this.widgetId);
            }
        },

    }));
}
