<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$ADMIN = require_staff();

const ADMIN_TABS = [
    'index' => ['Accueil', 'compass', 'admin/'],
    'commandes' => ['Commandes', 'bag', 'admin/commandes'],
    'produits' => ['Produits', 'package', 'admin/produits'],
    'clients' => ['Clients', 'users', 'admin/clients'],
];

function admin_header(string $title, string $active): void
{
    global $ADMIN;
    $newOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'nouvelle'")->fetchColumn();
    $unread = (int) db()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    $initial = strtoupper(mb_substr($ADMIN['email'], 0, 1)) ?: 'A';
    ?>
<!doctype html>
<html lang="fr">
<head>
<?php admin_head($title); ?>
</head>
<body class="admin-body">
<div class="splash" data-splash aria-hidden="true">
  <div class="splash-inner"><img src="<?= e(img('logo.jpeg')) ?>" alt=""><div class="splash-bar"><i></i></div></div>
</div>
<script>try { if (sessionStorage.getItem("am-splash")) document.querySelector("[data-splash]").remove(); } catch (e) {}</script>
<header class="a-top">
  <div class="a-top-row">
    <a href="<?= e(url('admin/')) ?>" class="a-brand"><img src="<?= e(img('logo.jpeg')) ?>" alt="AgroMina"><span>Admin</span></a>
    <nav class="a-nav">
      <?php foreach (ADMIN_TABS as $k => [$label, , $path]): ?>
        <a href="<?= e(url($path)) ?>" class="<?= $active === $k ? 'is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('admin/promotions')) ?>" class="<?= $active === 'promotions' ? 'is-active' : '' ?>">Promos</a>
      <a href="<?= e(url('admin/blog')) ?>" class="<?= $active === 'blog' ? 'is-active' : '' ?>">Blog</a>
      <a href="<?= e(url('admin/messages')) ?>" class="<?= $active === 'messages' ? 'is-active' : '' ?>">Messages</a>
      <?php if (is_super($ADMIN)): ?><a href="<?= e(url('admin/equipe')) ?>" class="<?= $active === 'equipe' ? 'is-active' : '' ?>">Équipe</a><?php endif; ?>
    </nav>
    <div class="a-tools">
      <button type="button" class="a-round" data-theme-toggle aria-label="Changer de thème"><?= icon('moon') ?></button>
      <a href="<?= e(url('admin/commandes', ['statut' => 'nouvelle'])) ?>" class="a-round" aria-label="Nouvelles commandes"><?= icon('bell') ?><?php if ($newOrders): ?><span class="badge"><?= $newOrders ?></span><?php endif; ?></a>
      <button type="button" class="a-avatar" data-sheet-open aria-label="Mon compte"><?= e($initial) ?></button>
    </div>
  </div>
</header>
<div class="offline-bar" data-offline hidden><?= icon('alert') ?> Hors ligne : affichage des dernières données enregistrées. Les modifications reprendront au retour d'Internet.</div>
<?php foreach (take_flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" data-flash><?= e($f['message']) ?></div>
<?php endforeach; ?>
<main class="a-main">
<?php if ($active === 'index'): ?>
  <div class="push-cta" data-push-cta hidden>
    <span><?= icon('bell') ?></span>
    <p><b>Soyez prévenu à chaque commande</b><small>Activez les notifications sur ce téléphone ou cet ordinateur.</small></p>
    <button type="button" class="btn btn-accent btn-sm" data-push-enable>Activer</button>
    <button type="button" class="icon-btn" data-push-dismiss aria-label="Plus tard"><?= icon('x') ?></button>
  </div>
<?php endif; ?>
<?php
    $GLOBALS['__admin_active'] = $active;
    $GLOBALS['__admin_unread'] = $unread;
}

function admin_footer(): void
{
    global $ADMIN;
    $active = $GLOBALS['__admin_active'] ?? '';
    $unread = $GLOBALS['__admin_unread'] ?? 0;
    ?>
</main>
<nav class="a-bottom">
  <?php foreach (ADMIN_TABS as $k => [$label, $ic, $path]): ?>
    <a href="<?= e(url($path)) ?>" class="<?= $active === $k ? 'is-active' : '' ?>"><?= icon($ic) ?><?= e($label) ?></a>
  <?php endforeach; ?>
  <button type="button" data-sheet-open class="<?= in_array($active, ['equipe', 'messages', 'blog', 'promotions'], true) ? 'is-active' : '' ?>"><?= icon('more') ?>Plus</button>
</nav>

<div class="sheet-backdrop" data-sheet hidden>
  <div class="sheet" role="dialog" aria-label="Plus">
    <div class="sheet-head">
      <div><b>Plus</b><p><?= e($ADMIN['email']) ?></p></div>
      <button type="button" class="icon-btn" data-sheet-close aria-label="Fermer"><?= icon('x') ?></button>
    </div>
    <div class="sheet-grid">
      <a class="sheet-item" href="<?= e(url('admin/promotions')) ?>"><?= icon('tag') ?>Promotions</a>
      <a class="sheet-item" href="<?= e(url('admin/blog')) ?>"><?= icon('book') ?>Blog</a>
      <a class="sheet-item" href="<?= e(url('admin/messages')) ?>"><?= icon('inbox') ?>Messages<?= $unread ? ' (' . (int) $unread . ')' : '' ?></a>
      <?php if (is_super($ADMIN)): ?><a class="sheet-item" href="<?= e(url('admin/equipe')) ?>"><?= icon('shield-check') ?>Équipe</a><?php endif; ?>
      <button type="button" class="sheet-item" data-theme-toggle><?= icon('moon') ?>Mode sombre</button>
      <a class="sheet-item" href="<?= e(url('')) ?>" target="_blank"><?= icon('store') ?>Voir la boutique</a>
      <a class="sheet-item" href="<?= e(url('admin/compte')) ?>"><?= icon('user') ?>Mon compte</a>
    </div>
    <div class="push-box" data-push-box hidden>
      <div><b><?= icon('bell') ?> Notifications de commande</b><small data-push-status>Recevez une alerte à chaque nouvelle commande.</small></div>
      <div class="push-actions">
        <button type="button" class="btn btn-primary btn-sm" data-push-enable>Activer</button>
        <button type="button" class="btn btn-outline btn-sm" data-push-test hidden>Tester</button>
        <button type="button" class="btn btn-danger btn-sm" data-push-disable hidden>Désactiver</button>
      </div>
    </div>
    <button type="button" class="btn btn-install" data-install><?= icon('arrow-right') ?> Installer l'application</button>
    <p class="install-help" data-install-help hidden></p>
    <form method="post" action="<?= e(url('deconnexion')) ?>">
      <?= csrf_field() ?><input type="hidden" name="from" value="admin">
      <button class="btn btn-danger"><?= icon('log-out') ?> Se déconnecter</button>
    </form>
  </div>
</div>
<script>
(function () {
  var root = document.documentElement;
  document.querySelectorAll("[data-theme-toggle]").forEach(function (b) {
    b.addEventListener("click", function () {
      var dark = root.classList.toggle("dark");
      try { localStorage.setItem("am-admin-theme", dark ? "dark" : "light"); } catch (e) {}
    });
  });
  var sheet = document.querySelector("[data-sheet]");
  document.querySelectorAll("[data-sheet-open]").forEach(function (b) { b.addEventListener("click", function () { sheet.hidden = false; }); });
  sheet.addEventListener("click", function (e) { if (e.target === sheet || e.target.closest("[data-sheet-close]")) sheet.hidden = true; });
  document.querySelectorAll("[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) { if (!confirm(f.getAttribute("data-confirm"))) e.preventDefault(); });
  });
  document.querySelectorAll("[data-autosubmit]").forEach(function (el) { el.addEventListener("change", function () { el.form.submit(); }); });
  document.querySelectorAll("[data-flash]").forEach(function (f) { setTimeout(function () { f.remove(); }, 5000); });

  // Écran d'ouverture (une fois par session, comme sur la version Lovable)
  var splash = document.querySelector("[data-splash]");
  if (splash) {
    try { sessionStorage.setItem("am-splash", "1"); } catch (e) {}
    setTimeout(function () { splash.classList.add("is-hiding"); }, 1400);
    setTimeout(function () { splash.remove(); }, 1900);
  }

  // Bandeau hors ligne
  var off = document.querySelector("[data-offline]");
  function net() {
    if (!navigator.onLine) { off.hidden = false; return; }
    // Certains téléphones se disent « en ligne » sans l'être : on vérifie vraiment.
    fetch(<?= json_encode(url('admin/manifest.php')) ?>, { method: "HEAD", cache: "no-store" })
      .then(function () { off.hidden = true; }, function () { off.hidden = false; });
  }
  window.addEventListener("online", net); window.addEventListener("offline", net); net();

  // Service worker : l'admin s'installe et s'ouvre même sans réseau
  if ("serviceWorker" in navigator && window.self === window.top) {
    navigator.serviceWorker.register(<?= json_encode(url('sw.js')) ?>, { scope: <?= json_encode(url('')) ?> }).catch(function () {});
    var warm = function () {
      if (!navigator.onLine) return;
      navigator.serviceWorker.ready.then(function (reg) {
        if (reg.active) reg.active.postMessage({ type: "warm", urls: <?= json_encode(array_map(fn($k) => url(ADMIN_TABS[$k][2]), array_keys(ADMIN_TABS)), JSON_UNESCAPED_SLASHES) ?> });
      });
    };
    setTimeout(warm, 2500);
  }

  // Notifications push de nouvelles commandes
  (function () {
    var box = document.querySelector("[data-push-box]"), cta = document.querySelector("[data-push-cta]");
    var status = document.querySelector("[data-push-status]");
    var supported = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
    var ios = /iPhone|iPad|iPod/.test(navigator.userAgent);
    var standalone = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
    box.hidden = false;
    function api(data) {
      return fetch(<?= json_encode(url('admin/push')) ?>, { method: "POST", credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": <?= json_encode(csrf_token()) ?> }, body: JSON.stringify(data) })
        .then(function (r) { return r.json(); }).catch(function () { return { ok: false, error: "Pas de connexion." }; });
    }
    function toast(msg) { var t = document.createElement("div"); t.className = "flash flash-success"; t.textContent = msg; document.querySelector(".a-main").prepend(t); setTimeout(function () { t.remove(); }, 5000); window.scrollTo({ top: 0, behavior: "smooth" }); }
    function show(state, msg) {
      document.querySelectorAll("[data-push-enable]").forEach(function (b) { b.hidden = state === "on" || state === "na"; });
      document.querySelector("[data-push-test]").hidden = state !== "on";
      document.querySelector("[data-push-disable]").hidden = state !== "on";
      status.textContent = msg;
      var dismissed = false; try { dismissed = localStorage.getItem("am-push-dismiss") === "1"; } catch (e) {}
      if (cta) cta.hidden = state !== "off" || dismissed;
    }
    function b64ToBytes(s) { s = s.replace(/-/g, "+").replace(/_/g, "/"); s += "===".slice((s.length + 3) % 4); var r = atob(s), a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a; }
    if (!supported) {
      show("na", ios && !standalone ? "Sur iPhone : installez d'abord l'application (Partager → Sur l'écran d'accueil), puis ouvrez-la et activez ici." : "Ce navigateur ne gère pas les notifications.");
      return;
    }
    if (Notification.permission === "denied") { show("na", "Notifications bloquées : autorisez-les dans les réglages du navigateur pour ce site."); return; }
    navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
      if (sub) { api({ action: "subscribe", subscription: sub.toJSON() }); show("on", "Activées sur cet appareil."); }
      else show("off", "Recevez une alerte à chaque nouvelle commande.");
    });
    function enable() {
      Notification.requestPermission().then(function (perm) {
        if (perm !== "granted") { show(perm === "denied" ? "na" : "off", "Autorisation refusée."); return; }
        return api({ action: "key" }).then(function (r) {
          if (!r.ok) throw new Error(r.error || "Clé indisponible");
          return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(r.key) });
          });
        }).then(function (sub) { return api({ action: "subscribe", subscription: sub.toJSON() }); })
          .then(function (r) { if (!r.ok) throw new Error(r.error); show("on", "Activées sur cet appareil."); toast("Notifications activées : vous serez prévenu à chaque commande."); });
      }).catch(function (e) { show("off", "Activation impossible : " + (e && e.message ? e.message : "réessayez.")); });
    }
    document.querySelectorAll("[data-push-enable]").forEach(function (b) { b.addEventListener("click", enable); });
    document.querySelector("[data-push-test]").addEventListener("click", function () {
      navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
        if (!sub) return show("off", "Recevez une alerte à chaque nouvelle commande.");
        status.textContent = "Envoi de la notification de test…";
        api({ action: "test", endpoint: sub.endpoint }).then(function (r) { status.textContent = r.ok ? "Notification de test envoyée ✅" : (r.error || "Échec de l'envoi."); });
      });
    });
    document.querySelector("[data-push-disable]").addEventListener("click", function () {
      navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
        if (!sub) return;
        return api({ action: "unsubscribe", endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); });
      }).then(function () { show("off", "Désactivées sur cet appareil."); });
    });
    var dismiss = document.querySelector("[data-push-dismiss]");
    if (dismiss) dismiss.addEventListener("click", function () { try { localStorage.setItem("am-push-dismiss", "1"); } catch (e) {} cta.hidden = true; });
  })();

  // Installation de l'application
  var installBtn = document.querySelector("[data-install]");
  var help = document.querySelector("[data-install-help]");
  var deferred = null;
  var standalone = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
  if (standalone) installBtn.hidden = true;
  window.addEventListener("beforeinstallprompt", function (e) { e.preventDefault(); deferred = e; });
  window.addEventListener("appinstalled", function () { installBtn.hidden = true; help.hidden = true; deferred = null; });
  installBtn.addEventListener("click", function () {
    if (deferred) { deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; }); return; }
    var ua = navigator.userAgent;
    help.textContent = /iPhone|iPad|iPod/.test(ua)
      ? "Sur iPhone/iPad : ouvrez cette page dans Safari, touchez le bouton Partager puis « Sur l'écran d'accueil »."
      : /Android/.test(ua)
        ? "Sur Android : ouvrez le menu ⋮ de Chrome puis « Installer l'application » ou « Ajouter à l'écran d'accueil »."
        : "Sur ordinateur : dans Chrome ou Edge, cliquez sur l'icône d'installation à droite de la barre d'adresse, ou menu ⋮ → « Installer AgroMina Admin ».";
    help.hidden = false;
  });
})();
</script>
</body>
</html>
<?php
}

function admin_title(string $title, ?string $subtitle = null, string $action = ''): void
{
    echo '<div class="a-title"><div><h1>' . e($title) . '</h1>' . ($subtitle ? '<p>' . e($subtitle) . '</p>' : '') . '</div>' . $action . '</div>';
}

function status_pill(string $status): string
{
    return '<span class="pill pill-' . e($status) . '">' . e(ORDER_STATUSES[$status] ?? $status) . '</span>';
}

function delta(int|float $now, int|float $prev): string
{
    $pct = $prev == 0 ? ($now > 0 ? 100 : 0) : (int) round(($now - $prev) / $prev * 100);
    return '<span class="delta' . ($pct < 0 ? ' is-down' : '') . '">' . ($pct > 0 ? '+' : '') . $pct . '%</span>';
}
