// Service worker that makes Wordhole installable as an app.
// The scores live in SQLite on the server, so the app still needs a connection: pages and data always
// come from the network. Static files (css, js, charts library, icons) are kept in a cache as well, so they
// are there if the network drops, and a simple "you are offline" page is shown if a page cannot be loaded.
// Everything is fetched network first, so changes to the files show at once and nothing goes stale.
// Bump VERSION when the list in PRECACHE changes.
'use strict'

const VERSION = 'wordhole-v1'

const PRECACHE = [
    './css/wordhole.css',
    './js/load_xlsx.js',
    './js/entry.js',
    './js/share.js',
    './js/mycharts.js',
    './frameworks/highcharts_12_4_0/highcharts.js',
    './frameworks/highcharts_12_4_0/series-label.js',
    './frameworks/highcharts_12_4_0/exporting.js',
    './frameworks/highcharts_12_4_0/export-data.js',
    './frameworks/highcharts_12_4_0/accessibility.js',
    './icons/icon-192.png',
    './icons/favicon-32.png'
]

// The CDN files the page loads (bootstrap, bootstrap-icons, jquery) are cached as they are used
const CDN_HOSTS = ['cdn.jsdelivr.net', 'code.jquery.com']

const OFFLINE_PAGE = `<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Wordhole - offline</title>
<style>body{font-family:system-ui,sans-serif;text-align:center;padding:3rem 1rem;color:#212529}
button{font-size:1rem;padding:.5rem 1.2rem;border:0;border-radius:.4rem;background:#80aa61;color:#fff}</style>
</head><body><h2>Wordhole</h2><p>You are offline. Wordhole needs a connection to load the scores.</p>
<button onclick="location.reload()">Try again</button></body></html>`

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    )
})

// Remove caches from older versions
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    )
})

function isStatic(url) {
    if (CDN_HOSTS.includes(url.hostname)) return true
    return url.origin === self.location.origin && /\.(css|js|png|jpg|svg|woff2?)$/.test(url.pathname)
}

self.addEventListener('fetch', (event) => {
    const request = event.request
    if (request.method !== 'GET') return            // posts (saving scores, logging in) go straight through

    const url = new URL(request.url)

    // Pages: always from the server, with the offline page if it cannot be reached
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => new Response(OFFLINE_PAGE, {headers: {'Content-Type': 'text/html; charset=utf-8'}}))
        )
        return
    }

    // Static files: from the network, keeping a copy, or the copy if the network is down
    if (isStatic(url)) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok || response.type === 'opaque') {
                        const copy = response.clone()
                        caches.open(VERSION).then((cache) => cache.put(request, copy))
                    }
                    return response
                })
                .catch(() => caches.match(request).then((cached) => cached || Response.error()))
        )
    }
    // Anything else (the php data calls) is left to the browser as normal
})
