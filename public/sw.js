const CACHE = 'notas-shell-v2';
const SHELL = ['/offline.html', '/offline-app.js', '/manifest.webmanifest'];
self.addEventListener('install', (evento) => { evento.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL))); self.skipWaiting(); });
self.addEventListener('activate', (evento) => { evento.waitUntil(caches.keys().then((chaves) => Promise.all(chaves.filter((chave) => chave !== CACHE).map((chave) => caches.delete(chave))))); self.clients.claim(); });
self.addEventListener('fetch', (evento) => { const requisicao = evento.request; if (requisicao.method !== 'GET') return; if (requisicao.mode === 'navigate') { evento.respondWith(fetch(requisicao).catch(() => caches.match('/offline.html'))); return; } const url = new URL(requisicao.url); if (url.origin === self.location.origin && SHELL.includes(url.pathname)) evento.respondWith(caches.match(requisicao).then((resposta) => resposta || fetch(requisicao))); });
