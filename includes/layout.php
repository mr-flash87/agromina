<?php
declare(strict_types=1);

/** Signature du créateur du site (bas de page). Numéro WhatsApp au format international, sans + ni espaces. */
const AGENCY_WHATSAPP = '22668880672';

const NAV = [
    '' => 'Accueil',
    'boutique' => 'Boutique',
    'categories' => 'Catégories',
    'engagements' => 'Nos engagements',
    'a-propos' => 'À propos',
    'blog' => 'Blog',
    'contact' => 'Contact',
];

/**
 * En-tête commun du site.
 * @param array{title?:string, description?:string, active?:string, noindex?:bool, body_class?:string} $o
 */
function page_header(array $o = []): void
{
    $title = $o['title'] ?? 'AgroMina – Le meilleur du terroir local, transformé avec passion';
    $desc = $o['description'] ?? 'Céréales, jus naturels, produits halieutiques et artisanaux du Delta du Saloum. Commandez en ligne chez AgroMina.';
    $active = $o['active'] ?? null;
    $user = current_user();
    $q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= e($o['image'] ?? img('hero.jpg')) ?>">
  <meta name="theme-color" content="#126333">
  <?php if (!empty($o['noindex'])): ?><meta name="robots" content="noindex"><?php endif; ?>
  <link rel="icon" type="image/png" href="<?= e(img('favicon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="<?= e($o['body_class'] ?? '') ?>">
<header class="site-header">
  <div class="topbar">
    <div class="container topbar-row">
      <button class="menu-btn" type="button" data-menu-toggle aria-label="Menu" aria-expanded="false"><?= icon('menu') ?></button>
      <?php logo_link() ?>
      <div class="delivery-hint"><?= icon('map-pin') ?><div><small>Livraison</small><strong>Sénégal</strong></div></div>
      <form class="search search-desktop" action="<?= e(url('boutique')) ?>" method="get" role="search">
        <input name="q" value="<?= e($q) ?>" placeholder="Rechercher un produit, une catégorie…" aria-label="Rechercher">
        <button aria-label="Rechercher"><?= icon('search') ?></button>
      </form>
      <div class="header-actions">
        <a href="<?= e(url('favoris')) ?>" class="h-action" aria-label="Favoris"><?= icon('heart') ?><span class="badge" data-fav-count hidden>0</span><span class="h-label">Favoris</span></a>
        <a href="<?= e(url('panier')) ?>" class="h-action" aria-label="Panier"><?= icon('cart') ?><span class="badge" data-cart-count>0</span><span class="h-label">Panier</span></a>
      </div>
    </div>
    <form class="container search search-mobile" action="<?= e(url('boutique')) ?>" method="get" role="search">
      <input name="q" value="<?= e($q) ?>" placeholder="Rechercher…" aria-label="Rechercher">
      <button aria-label="Rechercher"><?= icon('search') ?></button>
    </form>
  </div>
  <nav class="navband pattern-band" data-menu>
    <div class="container nav-row">
      <?php foreach (NAV as $path => $label): ?>
        <a href="<?= e(url($path)) ?>" class="<?= $active === $path ? 'is-active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php $bestPct = best_discount_now(); if ($bestPct > 0): ?>
        <a class="promo-hint" href="<?= e(url('boutique', ['promo' => 1])) ?>"><b>PROMOS</b> jusqu'à −<?= $bestPct ?> % en ce moment</a>
      <?php endif; ?>
    </div>
  </nav>
</header>
<?php foreach (take_flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" data-flash><?= e($f['message']) ?></div>
<?php endforeach; ?>
<main>
<?php
}

function page_footer(array $extraScripts = []): void
{
    ?>
</main>
<?php $wa = shop_whatsapp(); ?>
<section class="reassure" aria-label="Nos garanties">
  <div class="container reassure-grid">
    <div><span><?= icon('truck') ?></span><p><b>Livraison partout au Sénégal</b><small>Rapide et suivie</small></p></div>
    <div><span><?= icon('banknote') ?></span><p><b>Paiement à la livraison</b><small>Ou Wave, Orange Money</small></p></div>
    <div><span><?= icon('shield-check') ?></span><p><b>Inspection libre</b><small>Vérifiez avant de payer</small></p></div>
    <div><span><?= icon('leaf') ?></span><p><b>100 % local</b><small>Transformé dans le Saloum</small></p></div>
  </div>
</section>
<footer class="site-footer">
  <svg class="footer-wave" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60V22c120 18 240 28 360 24S600 22 720 18s240 4 360 14 240 16 360 6v22Z"/></svg>
  <div class="footer-main pattern-band">
    <div class="container footer-grid">
      <div class="footer-about">
        <?php logo_link('logo-lg') ?>
        <p>AgroMina valorise et transforme les richesses agricoles, halieutiques et artisanales du Delta du Saloum, avec les producteurs et les femmes transformatrices de la région.</p>
        <?php if ($wa): ?>
          <a class="wa-btn" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Bonjour AgroMina, je souhaite passer une commande.') ?>" target="_blank" rel="noopener"><?= icon('message') ?> Commander sur WhatsApp</a>
        <?php endif; ?>
        <div class="socials">
          <a href="<?= e(cfg('facebook_url', '#')) ?>" aria-label="Facebook" target="_blank" rel="noopener"><?= icon('facebook') ?></a>
          <a href="<?= e(cfg('instagram_url', '#')) ?>" aria-label="Instagram" target="_blank" rel="noopener"><?= icon('instagram') ?></a>
          <?php if ($wa): ?><a href="https://wa.me/<?= e($wa) ?>" aria-label="WhatsApp" target="_blank" rel="noopener"><?= icon('message') ?></a><?php endif; ?>
          <a href="mailto:<?= e(cfg('contact_email', 'contact@agromina.sn')) ?>" aria-label="E-mail"><?= icon('mail') ?></a>
        </div>
      </div>
      <div class="footer-col">
        <h4>La boutique</h4>
        <ul>
          <li><a href="<?= e(url('boutique')) ?>">Tous les produits</a></li>
          <li><a href="<?= e(url('boutique', ['promo' => 1])) ?>">Promotions</a></li>
          <li><a href="<?= e(url('boutique', ['nouveau' => 1])) ?>">Nouveautés</a></li>
          <li><a href="<?= e(url('favoris')) ?>">Mes favoris</a></li>
          <li><a href="<?= e(url('suivi')) ?>">Suivre ma commande</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Catégories</h4>
        <ul>
          <?php foreach (categories() as $slug => $c): ?>
            <li><a href="<?= e(url('boutique', ['cat' => $slug])) ?>"><?= e($c['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Nous contacter</h4>
        <ul class="contact-list">
          <li><span><?= icon('map-pin') ?></span><?= e(cfg('contact_address', 'Delta du Saloum, Sénégal')) ?></li>
          <li><span><?= icon('phone') ?></span><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) cfg('contact_phone', ''))) ?>"><?= e(cfg('contact_phone', '+221 00 000 00 00')) ?></a></li>
          <li><span><?= icon('mail') ?></span><a href="mailto:<?= e(cfg('contact_email', 'contact@agromina.sn')) ?>"><?= e(cfg('contact_email', 'contact@agromina.sn')) ?></a></li>
        </ul>
        <h4 class="pay-title">Moyens de paiement</h4>
        <div class="pay-badges"><span>Espèces</span><span>Wave</span><span>Orange Money</span></div>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container footer-bottom-row">
      <span>© <?= date('Y') ?> AgroMina · Fait avec <?= icon('heart', 'ic heart') ?> par
        <?php $agencyWa = preg_replace('/\D/', '', AGENCY_WHATSAPP); ?>
        <?php if ($agencyWa): ?>
          <a class="agency" href="https://wa.me/<?= e($agencyWa) ?>?text=<?= rawurlencode("Bonjour Pixel Agency, j'ai vu le site AgroMina et je voudrais un site web.") ?>" target="_blank" rel="noopener">Pixel Agency</a>
        <?php else: ?><b class="agency">Pixel Agency</b><?php endif; ?>
      </span>
      <span class="legal">
        <a href="<?= e(url('a-propos')) ?>">À propos</a>
        <a href="<?= e(url('engagements')) ?>">Nos engagements</a>
        <a href="<?= e(url('contact')) ?>">Contact</a>
        <a href="<?= e(url('cgv')) ?>">CGV</a>
        <a href="<?= e(url('confidentialite')) ?>">Confidentialité</a>
      </span>
    </div>
  </div>
</footer>
<?php if (shop_whatsapp() !== ''): ?>
  <a class="wa-float" href="<?= e(shop_whatsapp_link('Bonjour AgroMina, je souhaite passer une commande.')) ?>" target="_blank" rel="noopener" aria-label="Commander sur WhatsApp" data-wa-float><?= icon('message') ?><span>Commander</span></a>
<?php endif; ?>
<div class="toasts" data-toasts aria-live="polite"></div>
<script>window.AM = <?= json_encode([
    'base' => rtrim((string) cfg('base_url', ''), '/'),
    'csrf' => csrf_token(),
    'deliveryFee' => (int) cfg('delivery_fee', 1500),
    'whatsapp' => shop_whatsapp(),
    'icons' => [
        'minus' => icon('minus'), 'plus' => icon('plus'), 'trash' => icon('trash'),
        'check' => icon('check'), 'banknote' => icon('banknote'), 'smartphone' => icon('smartphone'),
        'arrow' => icon('arrow-right'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<?php foreach ($extraScripts as $s): ?><?= $s ?><?php endforeach; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
<?php
}

function logo_link(string $class = ''): void
{
    echo '<a href="' . e(url('')) . '" class="logo ' . e($class) . '"><img src="' . e(img('logo.jpeg'))
        . '" alt="AgroMina – Transformation des produits locaux" width="153" height="45"></a>';
}

function page_hero(string $title, ?string $subtitle = null): void
{
    echo '<section class="page-hero"><div class="container"><h1>' . e($title) . '</h1>'
        . ($subtitle ? '<p>' . e($subtitle) . '</p>' : '') . '</div></section>';
}

function product_card(array $p, array $attrs = []): string
{
    $cat = category_of($p['category']);
    $out = $p['stock'] === 0;
    $low = !$out && $p['stock'] < 5;
    $href = e(url('produit/' . $p['slug']));
    $disc = discount_pct($p);
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . e($k) . '="' . e($v) . '"';
    }
    ob_start(); ?>
<article class="pcard<?= $out ? ' is-out' : '' ?>" data-slug="<?= e($p['slug']) ?>"<?= $extra ?>>
  <a class="pcard-media" href="<?= $href ?>" tabindex="-1" aria-hidden="true">
    <img src="<?= e(product_image($p)) ?>" alt="" loading="lazy">
    <?php if ($out): ?><span class="pcard-out">Rupture de stock</span><?php endif; ?>
  </a>
  <div class="pcard-tags">
    <?php if ($disc): ?><span class="tag tag-accent">−<?= $disc ?> %</span><?php endif; ?>
    <?php if ($p['is_new']): ?><span class="tag tag-primary">Nouveau</span><?php endif; ?>
    <?php if ($p['best_seller'] && !$disc && !$p['is_new']): ?><span class="tag tag-card">★ Top vente</span><?php endif; ?>
  </div>
  <button type="button" class="fav-btn" data-fav="<?= e($p['slug']) ?>" aria-label="Ajouter <?= e($p['name']) ?> aux favoris"><?= icon('heart') ?></button>
  <div class="pcard-body">
    <span class="pcard-cat"><?= e($cat['short'] ?? $cat['name']) ?><?= $p['weight'] ? ' · ' . e($p['weight']) : '' ?></span>
    <a href="<?= $href ?>" class="pcard-name"><?= e($p['name']) ?></a>
    <?php if ($low): ?><span class="pcard-low">Plus que <?= $p['stock'] ?> en stock</span><?php endif; ?>
    <div class="pcard-foot">
      <div class="pcard-price"><strong><?= fcfa($p['price']) ?></strong><?php if ($p['old_price']): ?><s><?= fcfa($p['old_price']) ?></s><?php endif; ?></div>
      <button type="button" class="add-btn" data-add="<?= e($p['slug']) ?>" data-name="<?= e($p['name']) ?>" aria-label="Ajouter <?= e($p['name']) ?> au panier" <?= $out ? 'disabled' : '' ?>><?= icon('plus') ?><span>Ajouter</span></button>
    </div>
  </div>
</article>
<?php
    return (string) ob_get_clean();
}

/** Balises <head> de l'espace admin (application installable). */
function admin_head(string $title): void
{
    ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?> – AgroMina Admin</title>
  <meta name="robots" content="noindex">
  <meta name="description" content="Gestion de la boutique AgroMina : commandes, produits, clients et équipe.">
  <meta name="theme-color" content="#126333">
  <meta name="application-name" content="AgroMina Admin">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="AgroMina Admin">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <link rel="manifest" href="<?= e(url('admin/manifest.php')) ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= e(img('admin-icon-192.png')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(img('admin-icon-180.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script>try { if (localStorage.getItem("am-admin-theme") === "dark") document.documentElement.classList.add("dark"); } catch (e) {}</script>
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<?php
}

/**
 * Page de texte légal : sommaire cliquable + sections.
 * @param array<int, array{0:string,1:string}> $sections [titre, HTML]
 */
function legal_page(array $sections, string $updated): void
{
    echo '<div class="container legal-layout"><nav class="card legal-toc" aria-label="Sommaire"><b>Sommaire</b><ol>';
    foreach ($sections as $i => [$title]) {
        echo '<li><a href="#s' . ($i + 1) . '">' . e($title) . '</a></li>';
    }
    echo '</ol></nav><div class="legal-body"><p class="legal-updated">Dernière mise à jour : ' . e($updated) . '</p>';
    foreach ($sections as $i => [$title, $html]) {
        echo '<section id="s' . ($i + 1) . '"><h2><span>' . ($i + 1) . '.</span> ' . e($title) . '</h2>' . $html . '</section>';
    }
    echo '</div></div>';
}

/** Bloc « Qui sommes-nous » pour les pages légales (seules les informations renseignées s'affichent). */
function legal_identity(): string
{
    $rows = [
        'Nom commercial' => (string) cfg('legal_name', 'AgroMina'),
        'Forme juridique' => (string) cfg('legal_form', ''),
        'NINEA' => (string) cfg('legal_ninea', ''),
        'RCCM' => (string) cfg('legal_rccm', ''),
        'Adresse' => (string) cfg('contact_address', 'Delta du Saloum, Sénégal'),
        'Téléphone' => (string) cfg('contact_phone', ''),
        'E-mail' => (string) cfg('contact_email', 'contact@agromina.sn'),
    ];
    $html = '<ul class="legal-id">';
    foreach ($rows as $k => $v) {
        if ($v !== '' && !preg_match('/^\+?221\s?0{2}/', $v)) {
            $html .= '<li><span>' . e($k) . '</span><b>' . e($v) . '</b></li>';
        }
    }
    return $html . '</ul>';
}
