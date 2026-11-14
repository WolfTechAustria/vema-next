/*
 * VEMA Service Worker: macht die App installierbar und zeigt
 * Push-Benachrichtigungen an. Bewusst ohne Offline-Cache – die Daten
 * sollen immer aktuell vom Server kommen.
 */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Für die Installierbarkeit braucht es einen fetch-Handler; Anfragen gehen
// unverändert ans Netz.
self.addEventListener('fetch', () => {});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (exception) {
        payload = { title: 'VEMA', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'VEMA';
    const options = {
        body: payload.body || '',
        icon: payload.icon,
        badge: payload.badge,
        tag: payload.tag,
        data: payload.data || {},
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if (new URL(client.url).origin === new URL(url, self.location.origin).origin && 'focus' in client) {
                await client.focus();

                return client.navigate ? client.navigate(url) : undefined;
            }
        }

        return self.clients.openWindow(url);
    })());
});
