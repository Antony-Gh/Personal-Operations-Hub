const CACHE_VERSION = 'ops-hub-static-v1';
const PRECACHE_ASSETS = [
    '/offline.html',
    '/favicon.svg',
    '/favicon-32x32.png',
    '/favicon-16x16.png',
    '/apple-touch-icon.png',
    '/icon-192.png',
    '/icon-512.png',
    '/icon-maskable-512.png',
    '/site.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key.startsWith('ops-hub-static-') && key !== CACHE_VERSION)
                        .map((oldKey) => caches.delete(oldKey))
                )
            )
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Never cache Inertia JSON responses, API polls, exports, or private feeds
    if (
        request.headers.get('X-Inertia') ||
        request.headers.get('Accept')?.includes('application/json') ||
        url.pathname.startsWith('/notifications') ||
        url.pathname.startsWith('/focus-sessions') ||
        url.pathname.startsWith('/command-palette') ||
        url.pathname.startsWith('/calendar/feed') ||
        url.pathname.startsWith('/calendar/export') ||
        url.pathname.startsWith('/settings/export') ||
        url.pathname.startsWith('/settings/backup')
    ) {
        return;
    }

    // Cache-first for fingerprinted Vite build assets & static icons
    const isImmutableStatic =
        url.pathname.startsWith('/build/') ||
        PRECACHE_ASSETS.includes(url.pathname);

    if (isImmutableStatic) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const copy = networkResponse.clone();
                        caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Network-first for HTML document navigations with offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match('/offline.html').then((fallback) => fallback || Response.error())
            )
        );
    }
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification?.data?.url || '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client) {
                        client.navigate(targetUrl);
                    }
                    return;
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
        })
    );
});

