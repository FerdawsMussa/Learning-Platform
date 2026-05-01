// ─── JU Learn Service Worker v3 ────────────────────────────────────────────────
// Cache-first strategy for all file types. Handles course caching with progress.

const SW_VERSION  = 'ju-learn-v4';
const STATIC_CACHE = 'ju-static-v4';
const COURSE_CACHE = 'ju-course-v4';

const STATIC_ASSETS = [
    '/lastfyp/',
    '/lastfyp/index.php',
    '/lastfyp/login.php',
    '/lastfyp/dashboard.php',
    '/lastfyp/assets/css/style.css',
    '/lastfyp/assets/css/dashboard.css',
    '/lastfyp/assets/js/offline_manager.js',
];

// ─── INSTALL ──────────────────────────────────────────────────────────────────
self.addEventListener('install', event => {
    console.log('[SW] Installing', SW_VERSION);
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => Promise.allSettled(STATIC_ASSETS.map(url => cache.add(url))))
            .then(() => self.skipWaiting())
    );
});

// ─── ACTIVATE ────────────────────────────────────────────────────────────────
self.addEventListener('activate', event => {
    console.log('[SW] Activating', SW_VERSION);
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys
                    .filter(k => k !== STATIC_CACHE && k !== COURSE_CACHE)
                    .map(k => { console.log('[SW] Purging old cache:', k); return caches.delete(k); })
            ))
            .then(() => self.clients.claim())
    );
});

// ─── FETCH ────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Only handle GET
    if (request.method !== 'GET') return;
    // Skip non-http(s)
    if (!url.protocol.startsWith('http')) return;
    // Never cache admin/instructor pages
    if (url.pathname.includes('admin') || url.pathname.includes('instructor_dashboard')) return;
    // The sync endpoint must always hit the network
    if (url.pathname.includes('sync_progress')) return;

    // ── ALL MEDIA & DOCUMENT FILES → Cache-first ─────────────────────────────
    // Once stored by a CACHE_COURSE message, serve immediately without network.
    const isMedia = /\.(mp4|webm|ogg|avi|mkv|mp3|wav|aac|pdf|png|jpg|jpeg|gif|svg|webp|bmp|ico|doc|docx|xls|xlsx|ppt|pptx|zip|rar|txt)$/i.test(url.pathname);
    if (isMedia) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(res => {
                    if (res && res.ok) {
                        const clone = res.clone();
                        caches.open(COURSE_CACHE).then(c => c.put(request, clone));
                    }
                    return res;
                }).catch(() => new Response('Resource not available offline', { status: 503 }));
            })
        );
        return;
    }

    // ── LOCAL STATIC ASSETS (css/js/fonts) → Cache-first ─────────────────────
    const isStatic = /\.(css|js|woff2?|ttf|eot)$/i.test(url.pathname);
    if (isStatic && url.hostname === self.location.hostname) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(res => {
                    if (res && res.ok) {
                        const clone = res.clone();
                        caches.open(STATIC_CACHE).then(c => c.put(request, clone));
                    }
                    return res;
                });
            })
        );
        return;
    }

    // ── CDN RESOURCES (Bootstrap, fonts.googleapis.com, etc.) → Stale-while-revalidate
    if (url.hostname !== self.location.hostname) {
        event.respondWith(
            caches.match(request).then(cached => {
                const fetchPromise = fetch(request).then(res => {
                    if (res) {
                        const clone = res.clone();
                        caches.open(STATIC_CACHE).then(c => c.put(request, clone));
                    }
                    return res;
                }).catch(() => cached);
                return cached || fetchPromise;
            })
        );
        return;
    }

    // ── PHP PAGES (student_viewer.php, dashboard.php) → Network-first, fallback to cache
    event.respondWith(
        fetch(request)
            .then(res => {
                if (res && res.ok) {
                    const clone = res.clone();
                    caches.open(COURSE_CACHE).then(c => c.put(request, clone));
                }
                return res;
            })
            .catch(() =>
                caches.match(request).then(cached => cached ||
                    caches.match('/lastfyp/student_viewer.php')
                )
            )
    );
});

// ─── MESSAGE: CACHE_COURSE ────────────────────────────────────────────────────
// The JS layer sends this message with a list of URLs to pre-cache.
// We report back progress via postMessage to all clients.
self.addEventListener('message', async event => {
    if (!event.data || event.data.type !== 'CACHE_COURSE') return;

    const { urls } = event.data;
    const total = urls.length;
    let done = 0;
    let failed = 0;

    const broadcast = async () => {
        const clients = await self.clients.matchAll({ includeUncontrolled: true });
        const complete = (done + failed) >= total;
        clients.forEach(c => c.postMessage({ type: 'CACHE_PROGRESS', done, failed, total, complete }));
    };

    const cache = await caches.open(COURSE_CACHE);

    // Process in batches of 4 for performance
    const BATCH = 4;
    for (let i = 0; i < urls.length; i += BATCH) {
        const batch = urls.slice(i, i + BATCH);
        await Promise.allSettled(batch.map(async rawUrl => {
            try {
                const existing = await cache.match(rawUrl);
                if (existing) { done++; await broadcast(); return; }
                const res = await fetch(rawUrl, { mode: 'no-cors' });
                await cache.put(rawUrl, res);
                done++;
            } catch (e) {
                console.warn('[SW] Cache failed:', rawUrl, e.message);
                failed++;
            }
            await broadcast();
        }));
    }
    await broadcast(); // Ensure final state reported
});
