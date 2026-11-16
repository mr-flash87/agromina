<?php
require __DIR__ . '/includes/bootstrap.php';

$q = str_input('q', 100, $_GET);
$cat = str_input('cat', 40, $_GET);
$cat = isset(categories()[$cat]) ? $cat : '';
$flag = static fn(string $k) => !empty($_GET[$k]) && $_GET[$k] !== '0';
$promo = $flag('promo');
$nouveau = $flag('nouveau');
$best = $flag('best');
$dispo = $flag('dispo');
$max = (int) ($_GET['max'] ?? 0);
$max = in_array($max, [1000, 2000, 3000, 5000], true) ? $max : 0;
$tri = in_array($_GET['tri'] ?? '', ['asc', 'desc', 'new'], true) ? $_GET['tri'] : '';

$all = get_products();
$needle = normalize_text($q);
$visible = static function (array $p) use ($needle, $cat, $promo, $nouveau, $best, $dispo, $max): bool {
    if ($needle !== '' && !str_contains(normalize_text($p['name'] . ' ' . category_of($p['category'])['name'] . ' ' . $p['description']), $needle)) return false;
    if ($cat && $p['category'] !== $cat) return false;
    if ($promo && !$p['old_price']) return false;
    if ($nouveau && !$p['is_new']) return false;
    if ($best && !$p['best_seller']) return false;
    if ($dispo && $p['stock'] === 0) return false;
    if ($max && $p['price'] > $max) return false;
    return true;
};
if ($tri === 'asc') usort($all, fn($a, $b) => $a['price'] <=> $b['price']);
if ($tri === 'desc') usort($all, fn($a, $b) => $b['price'] <=> $a['price']);
if ($tri === 'new') usort($all, fn($a, $b) => [$b['is_new'], $b['created_at']] <=> [$a['is_new'], $a['created_at']]);

$byCat = [];
$counts = [];
$shown = 0;
foreach ($all as $i => $p) {
    $p['_order'] = $i;
    $byCat[$p['category']][] = $p;
    $counts[$p['category']] = ($counts[$p['category']] ?? 0) + 1;
    if ($visible($p)) $shown++;
}
$total = count($all);
$hasFilter = $q !== '' || $cat || $promo || $nouveau || $best || $dispo || $max || $tri;

$catInfo = $cat ? category_of($cat) : null;
page_header([
    'title' => $catInfo ? seo_title($catInfo['name'] . ' du Sénégal – Achat en ligne | AgroMina', $catInfo['name'] . ' du Sénégal | AgroMina', $catInfo['name'] . ' | AgroMina')
        : 'Boutique en ligne de produits locaux sénégalais | AgroMina',
    'description' => $catInfo
        ? $catInfo['name'] . ' du Delta du Saloum à commander en ligne chez AgroMina. ' . ($catInfo['description'] ?? '') . ' Livraison partout au Sénégal, paiement à la livraison.'
        : 'Tous les produits AgroMina : céréales locales, jus naturels, fruits transformés, poisson fumé et produits artisanaux du Delta du Saloum. Livraison partout au Sénégal.',
    'canonical_keep' => ['cat'],
    'noindex' => $q !== '' || $promo || $nouveau || $best || $dispo || $max || $tri, // pages de recherche/filtres : pas de doublons dans Google
    'active' => 'boutique',
    'body_class' => 'page-shop',
]);
?>
<section class="shop-hero">
  <div class="container shop-hero-inner">
    <div>
      <p class="eyebrow">La boutique AgroMina</p>
      <h1>Le terroir du Saloum,<br>livré chez vous.</h1>
      <p class="shop-hero-sub"><?= $total ?> produits locaux transformés avec soin · Paiement à la livraison ou Mobile Money</p>
    </div>
    <form class="shop-search" method="get" action="<?= e(url('boutique')) ?>" role="search" data-shop-search>
      <?= icon('search') ?>
      <input name="q" value="<?= e($q) ?>" placeholder="Rechercher : bissap, fonio, miel…" aria-label="Rechercher un produit" autocomplete="off">
      <?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
      <button class="btn btn-accent">Rechercher</button>
    </form>
  </div>
</section>

<form class="shop-bar" method="get" action="<?= e(url('boutique')) ?>" data-shop>
  <input type="hidden" name="q" value="<?= e($q) ?>">
  <div class="container">
    <div class="cat-chips" role="radiogroup" aria-label="Catégories">
      <label class="cat-chip<?= $cat === '' ? ' is-active' : '' ?>"><input type="radio" name="cat" value="" <?= $cat === '' ? 'checked' : '' ?>><span class="cat-chip-all"><?= icon('store') ?></span>Tout <small><?= $total ?></small></label>
      <?php foreach (categories() as $slug => $c): ?>
        <label class="cat-chip<?= $cat === $slug ? ' is-active' : '' ?>"><input type="radio" name="cat" value="<?= e($slug) ?>" <?= $cat === $slug ? 'checked' : '' ?>><img src="<?= e(media_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy"><?= e($c['short']) ?> <small><?= $counts[$slug] ?? 0 ?></small></label>
      <?php endforeach; ?>
    </div>
    <div class="shop-tools">
      <div class="toggle-chips">
        <label class="tchip"><input type="checkbox" name="promo" value="1" <?= $promo ? 'checked' : '' ?>><span>🔥 Promos</span></label>
        <label class="tchip"><input type="checkbox" name="nouveau" value="1" <?= $nouveau ? 'checked' : '' ?>><span>✨ Nouveautés</span></label>
        <label class="tchip"><input type="checkbox" name="best" value="1" <?= $best ? 'checked' : '' ?>><span>⭐ Meilleures ventes</span></label>
        <label class="tchip"><input type="checkbox" name="dispo" value="1" <?= $dispo ? 'checked' : '' ?>><span>En stock</span></label>
      </div>
      <div class="shop-selects">
        <label class="sel"><span>Prix</span>
          <select name="max">
            <option value="">Tous</option>
            <?php foreach ([1000, 2000, 3000, 5000] as $m): ?><option value="<?= $m ?>" <?= $max === $m ? 'selected' : '' ?>>≤ <?= e(fcfa($m)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="sel"><span>Trier</span>
          <select name="tri">
            <option value="">Par catégorie</option>
            <option value="asc" <?= $tri === 'asc' ? 'selected' : '' ?>>Prix croissant</option>
            <option value="desc" <?= $tri === 'desc' ? 'selected' : '' ?>>Prix décroissant</option>
            <option value="new" <?= $tri === 'new' ? 'selected' : '' ?>>Nouveautés d'abord</option>
          </select>
        </label>
      </div>
    </div>
    <noscript><button class="btn btn-primary btn-sm" style="margin-top:.5rem">Appliquer</button></noscript>
  </div>
</form>

<div class="container shop-results">
  <div class="results-bar">
    <p><b data-count><?= $shown ?></b> produit<span data-plural><?= $shown > 1 ? 's' : '' ?></span><?php if ($q !== ''): ?> pour « <span data-q><?= e($q) ?></span> »<?php endif; ?></p>
    <a href="<?= e(url('boutique')) ?>" class="reset-link" data-reset <?= $hasFilter ? '' : 'hidden' ?>><?= icon('x') ?> Effacer les filtres</a>
  </div>

  <?php foreach (categories() as $slug => $c): $items = $byCat[$slug] ?? []; if (!$items) continue;
      $n = count(array_filter($items, $visible)); ?>
    <section class="shop-section" data-section="<?= e($slug) ?>" <?= $n ? '' : 'hidden' ?>>
      <header class="shop-section-head">
        <img src="<?= e(media_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
        <div><h2><?= e($c['name']) ?></h2><p><span data-section-count><?= $n ?></span> produit<?= $n > 1 ? 's' : '' ?></p></div>
      </header>
      <div class="grid-products shop-grid">
        <?php foreach ($items as $p): ?>
          <?= product_card($p, array_merge([
              'data-cat' => $p['category'],
              'data-price' => (string) $p['price'],
              'data-promo' => $p['old_price'] ? '1' : '0',
              'data-new' => $p['is_new'] ? '1' : '0',
              'data-best' => $p['best_seller'] ? '1' : '0',
              'data-stock' => (string) $p['stock'],
              'data-order' => (string) $p['_order'],
              'data-search' => normalize_text($p['name'] . ' ' . $c['name'] . ' ' . $p['description']),
          ], $visible($p) ? [] : ['hidden' => 'hidden'])) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="card shop-empty" data-empty <?= $shown ? 'hidden' : '' ?>>
    <span class="round-icon"><?= icon('search') ?></span>
    <h2>Aucun produit trouvé</h2>
    <p>Essayez un autre mot ou retirez quelques filtres.</p>
    <a href="<?= e(url('boutique')) ?>" class="btn btn-primary">Voir tous les produits</a>
  </div>
</div>
<?php page_footer(); ?>
