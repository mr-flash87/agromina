/* AgroMina – service worker de l'application admin (même stratégie que la version Lovable).
   - Pages : réseau d'abord (4 s max), sinon dernière version enregistrée → l'admin s'ouvre hors ligne
   - Styles, scripts, images : cache d'abord
   - Polices Google : cache puis mise à jour en arrière-plan
   Les commandes, connexions et envois de formulaires (POST) ne passent jamais par le cache. */
const VERSION = "am-v5";
const PAGES = "am-pages-" + VERSION;
const ASSETS = "am-assets-" + VERSION;
const FONTS = "am-fonts-" + VERSION;
const scope = self.registration.scope; // ex. https://agromina.pixelpub.online/
const PRECACHE = ["assets/css/style.css", "assets/js/app.js", "assets/img/logo.jpeg", "assets/img/admin-icon-192.png", "assets/img/favicon.png"];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(ASSETS)
      .then((c) => c.addAll(PRECACHE.map((p) => new URL(p, scope).href)))
      .catch(() => {})
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith("am-") && ![PAGES, ASSETS, FONTS].includes(k)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function trim(cacheName, max) {
  caches.open(cacheName).then((c) => c.keys().then((keys) => {
    if (keys.length > max) Promise.all(keys.slice(0, keys.length - max).map((k) => c.delete(k)));
  }));
}

function networkFirst(request) {
  return new Promise((resolve) => {
    let settled = false;
    const fromCache = () => caches.match(request).then((hit) => hit || caches.match(new URL("admin/", scope).href));
    const timer = setTimeout(() => {
      fromCache().then((hit) => { if (hit && !settled) { settled = true; resolve(hit); } });
    }, 4000);
    fetch(request).then((res) => {
      clearTimeout(timer);
      if (res.ok && res.type === "basic" && !res.redirected) {
        const copy = res.clone();
        caches.open(PAGES).then((c) => c.put(request, copy)).then(() => trim(PAGES, 40));
      }
      if (!settled) { settled = true; resolve(res); }
    }).catch(() => {
      clearTimeout(timer);
      fromCache().then((hit) => {
        if (settled) return;
        settled = true;
        resolve(hit || new Response(
          '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hors ligne</title>' +
          '<body style="font-family:sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#fbfcf8;color:#1d3326;text-align:center;padding:1rem">' +
          "<div><h1 style=\"color:#126333\">Pas de connexion</h1><p>Cette page n'a pas encore été enregistrée sur l'appareil.<br>Reconnectez-vous à Internet puis réessayez.</p></div>",
          { status: 503, headers: { "Content-Type": "text/html; charset=utf-8" } }
        ));
      });
    });
  });
}

// L'admin demande d'enregistrer ses pages principales pour pouvoir les ouvrir hors ligne.
self.addEventListener("message", (event) => {
  const data = event.data || {};
  if (data.type !== "warm" || !Array.isArray(data.urls)) return;
  event.waitUntil(caches.open(PAGES).then((c) => Promise.all(data.urls.map((u) =>
    fetch(u, { credentials: "same-origin" }).then((res) => {
      if (res.ok && !res.redirected) return c.put(u, res);
    }).catch(() => {})
  ))));
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);

  if (url.hostname === "fonts.googleapis.com" || url.hostname === "fonts.gstatic.com") {
    event.respondWith(caches.open(FONTS).then((c) => c.match(req).then((hit) => {
      const net = fetch(req).then((res) => { if (res.ok) c.put(req, res.clone()); return res; }).catch(() => hit);
      return hit || net;
    })));
    return;
  }
  if (url.origin !== self.location.origin) return;

  const path = url.pathname;
  if (path.includes("/api/") || path.endsWith("/install.php") || path.endsWith("/deconnexion") || url.searchParams.has("export")) return;

  if (req.mode === "navigate") {
    // Seul l'espace admin est gardé hors ligne : la boutique reste toujours à jour.
    if (path.startsWith(new URL("admin/", scope).pathname)) event.respondWith(networkFirst(req));
    return;
  }

  if (path.startsWith(new URL("assets/", scope).pathname) || path.startsWith(new URL("uploads/", scope).pathname)) {
    // Les fichiers ont un numéro de version (?v=…) : la bonne version vient du cache, une nouvelle
    // version vient du réseau. Hors ligne seulement, on accepte une ancienne version.
    event.respondWith(caches.open(ASSETS).then((c) => c.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { c.put(req, res.clone()); trim(ASSETS, 200); }
      return res;
    }).catch(() => c.match(req, { ignoreSearch: true }).then((old) => old || Response.error())))));
  }
});

// Notifications de nouvelles commandes
self.addEventListener("push", (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data && event.data.text() }; }
  const title = data.title || "AgroMina Admin";
  event.waitUntil(self.registration.showNotification(title, {
    body: data.body || "Nouvelle activité sur la boutique",
    icon: new URL("assets/img/admin-icon-192.png", scope).href,
    badge: new URL("assets/img/admin-icon-192.png", scope).href,
    tag: data.tag || undefined,
    renotify: true,
    vibrate: [120, 60, 120],
    data: { url: data.url || new URL("admin/commandes", scope).href },
  }));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const target = new URL(event.notification.data && event.notification.data.url || "admin/commandes", scope).href;
  event.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((list) => {
    for (const c of list) {
      if (c.url.startsWith(new URL("admin/", scope).href) && "focus" in c) { c.navigate(target); return c.focus(); }
    }
    return self.clients.openWindow(target);
  }));
});
