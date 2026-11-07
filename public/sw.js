// Minimal service worker: makes the site installable and shows a friendly page when offline.
// It never stores pages, marks, papers or anything else from the signed-in site.
const OFFLINE = '/offline.html';
const CACHE = 'dems-shell-v1';
self.addEventListener('install', (e) => { e.waitUntil(caches.open(CACHE).then((c) => c.add(OFFLINE)).then(() => self.skipWaiting())); });
self.addEventListener('activate', (e) => { e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', (e) => {
    if (e.request.mode !== 'navigate') return; // only page loads; everything else goes straight to the network
    e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE)));
});
