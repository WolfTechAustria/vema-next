/*
 * Barcode-/QR-Scan mit der Handykamera. Nutzt die BarcodeDetector-API des
 * Browsers, wo vorhanden, sonst ZXing als WebAssembly (z. B. iPhone). Die
 * .wasm-Datei wird aus dem eigenen Build geladen, nicht von einem CDN, und
 * erst beim ersten Öffnen der Kamera.
 *
 * Verwendung: x-data="barcodeScanner((code) => $wire.scan(code))", mit
 * { once: true } schließt sich die Kamera nach dem ersten Treffer.
 */
import wasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url';

const SCAN_INTERVAL_MS = 250;
const SAME_CODE_PAUSE_MS = 1500;

let detectorPromise = null;

function loadDetector() {
    detectorPromise ??= import('barcode-detector/ponyfill').then(({ BarcodeDetector, prepareZXingModule }) => {
        prepareZXingModule({
            overrides: {
                locateFile: (path, prefix) => (path.endsWith('.wasm') ? wasmUrl : prefix + path),
            },
        });

        return new BarcodeDetector();
    });

    return detectorPromise;
}

export function registerBarcodeScanner(Alpine) {
    Alpine.data('barcodeScanner', (onCode, options = {}) => ({

        open: false,
        starting: false,
        error: null,
        stream: null,
        timer: null,
        lastCode: null,
        lastCodeAt: 0,

        async start() {
            this.error = null;

            if (! window.isSecureContext || ! navigator.mediaDevices?.getUserMedia) {
                this.error = 'Die Kamera ist nur über eine sichere Verbindung (https) verfügbar.';

                return;
            }

            this.open = true;
            this.starting = true;

            try {
                const [detector, stream] = await Promise.all([
                    loadDetector(),
                    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }),
                ]);

                this.stream = stream;
                this.$refs.video.srcObject = stream;
                await this.$refs.video.play();

                this.timer = setInterval(() => this.detect(detector), SCAN_INTERVAL_MS);
            } catch (exception) {
                this.error = exception?.name === 'NotAllowedError'
                    ? 'Der Zugriff auf die Kamera wurde nicht erlaubt.'
                    : 'Die Kamera konnte nicht gestartet werden.';
                this.stop();
            } finally {
                this.starting = false;
            }
        },

        async detect(detector) {
            const video = this.$refs.video;

            if (! video || video.readyState < 2) {
                return;
            }

            let codes = [];

            try {
                codes = await detector.detect(video);
            } catch {
                return;
            }

            const code = codes[0]?.rawValue;

            if (! code) {
                return;
            }

            const now = Date.now();

            if (code === this.lastCode && now - this.lastCodeAt < SAME_CODE_PAUSE_MS) {
                return;
            }

            this.lastCode = code;
            this.lastCodeAt = now;

            onCode(code);

            if (options.once) {
                this.stop();
            }
        },

        stop() {
            clearInterval(this.timer);
            this.timer = null;
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
            this.open = false;
        },

        destroy() {
            this.stop();
        },
    }));

    /*
     * Rückmeldung nach einem Scan: kurzer Ton und Vibration (Handy).
     */
    Alpine.data('scanFeedback', () => ({
        audio: null,

        signal(detail) {
            const success = detail?.ok ?? detail?.[0]?.ok;

            navigator.vibrate?.(success ? 80 : [60, 60, 60]);

            try {
                this.audio ??= new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = this.audio.createOscillator();
                const gain = this.audio.createGain();

                oscillator.frequency.value = success ? 880 : 220;
                gain.gain.value = 0.08;
                oscillator.connect(gain).connect(this.audio.destination);
                oscillator.start();
                oscillator.stop(this.audio.currentTime + (success ? 0.08 : 0.25));
            } catch {
                // Ton ist nur eine Hilfe.
            }
        },
    }));
}
