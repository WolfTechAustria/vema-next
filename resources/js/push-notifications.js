/*
 * PWA & Push-Benachrichtigungen:
 * - registriert den Service Worker (/sw.js), damit VEMA installierbar ist
 *   und Benachrichtigungen empfangen kann;
 * - Alpine-Baustein "pushDevice" für die Seite „Benachrichtigungen“:
 *   Gerät an- und abmelden, Abo an Livewire übergeben.
 */

export function registerServiceWorker() {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

function base64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
}

export function registerPushDevice(Alpine) {
    Alpine.data('pushDevice', (publicKey, knownEndpoints) => ({

        state: 'loading',
        busy: false,
        error: null,

        async init() {
            if (! publicKey) {
                this.state = 'unsupported';

                return;
            }

            if (isIos() && ! isStandalone()) {
                this.state = 'ios-install';

                return;
            }

            if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
                this.state = 'unsupported';

                return;
            }

            if (Notification.permission === 'denied') {
                this.state = 'denied';

                return;
            }

            const registration = await navigator.serviceWorker.register('/sw.js');
            const subscription = await registration.pushManager.getSubscription();

            // Abo im Browser vorhanden, aber nicht (mehr) bei uns gespeichert: neu übermitteln.
            if (subscription && ! knownEndpoints.includes(subscription.endpoint)) {
                await this.$wire.subscribe(subscription.toJSON());
            }

            this.state = subscription ? 'on' : 'off';
        },

        async enable() {
            this.busy = true;
            this.error = null;

            try {
                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    this.state = permission === 'denied' ? 'denied' : 'off';

                    return;
                }

                const registration = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64ToUint8Array(publicKey),
                });

                const json = subscription.toJSON();
                json.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];

                await this.$wire.subscribe(json);

                this.state = 'on';
            } catch (exception) {
                this.error = 'Benachrichtigungen konnten nicht aktiviert werden.';
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;
            this.error = null;

            try {
                const registration = await navigator.serviceWorker.getRegistration('/sw.js');
                const subscription = registration ? await registration.pushManager.getSubscription() : null;

                if (subscription) {
                    await this.$wire.unsubscribe(subscription.endpoint);
                    await subscription.unsubscribe();
                }

                this.state = 'off';
            } catch (exception) {
                this.error = 'Benachrichtigungen konnten nicht deaktiviert werden.';
            } finally {
                this.busy = false;
            }
        },
    }));
}
