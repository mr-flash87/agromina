<?php
require __DIR__ . '/_layout.php';

function find_product(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? normalize_product($r) : null;
}

$draft = null;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $pdo = db();

    $back = static fn(int $id) => url('admin/produits', array_filter(['q' => $_POST['f_q'] ?? '', 'vue' => $_POST['f_vue'] ?? ''])) . '#p' . $id;
    if (in_array($action, ['toggle', 'toggle_active'], true) && ($p = find_product($id))) {
        $pdo->prepare('UPDATE products SET is_active = ? WHERE id = ?')->execute([$p['is_active'] ? 0 : 1, $id]);
        flash('success', $p['is_active'] ? 'Page produit hors ligne : elle n\'est plus accessible' : 'Page produit en ligne');
        redirect($back($id));
    }
    if ($action === 'toggle_listed' && ($p = find_product($id))) {
        $pdo->prepare('UPDATE products SET is_listed = ? WHERE id = ?')->execute([$p['is_listed'] ? 0 : 1, $id]);
        flash('success', $p['is_listed'] ? 'Produit retiré de la boutique (sa page reste accessible par son lien)' : 'Produit visible dans la boutique');
        redirect($back($id));
    }

    if ($action === 'duplicate' && ($p = find_product($id))) {
        $slug = substr($p['slug'], 0, 64) . '-copie-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $pdo->prepare(
            'INSERT INTO products (slug, name, category, price, old_price, stock, weight, description, is_new, best_seller, is_active, is_listed, image_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)'
        )->execute([$slug, mb_substr($p['name'] . ' (copie)', 0, 120), $p['category'], $p['price'], $p['old_price'], $p['stock'], $p['weight'], $p['description'], (int) $p['is_new'], (int) $p['best_seller'], $p['image_url']]);
        flash('success', 'Produit dupliqué (masqué tant que vous ne le publiez pas)');
        redirect(url('admin/produits', ['edit' => $pdo->lastInsertId()]));
    }

    if ($action === 'delete' && ($p = find_product($id))) {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        delete_uploaded_image($p['image_url']);
        flash('success', 'Produit supprimé');
        redirect(url('admin/produits'));
    }

    if ($action === 'save') {
        $existing = $id ? find_product($id) : null;
        $name = str_input('name', 120);
        $slug = slugify(str_input('slug', 80) ?: $name);
        $category = str_input('category', 40);
        $price = (int) ($_POST['price'] ?? -1);
        $oldRaw = trim((string) ($_POST['old_price'] ?? ''));
        $oldPrice = $oldRaw === '' ? null : (int) $oldRaw;
        $stock = (int) ($_POST['stock'] ?? -1);
        $image = $existing['image_url'] ?? null;

        $draft = [
            'id' => $id ?: null, 'name' => $name, 'slug' => $slug, 'category' => $category, 'price' => $price,
            'old_price' => $oldPrice, 'stock' => $stock, 'weight' => str_input('weight', 40),
            'description' => str_input('description', 2000), 'is_new' => !empty($_POST['is_new']),
            'best_seller' => !empty($_POST['best_seller']), 'is_active' => !empty($_POST['is_active']),
            'is_listed' => !empty($_POST['is_listed']), 'image_url' => $image,
        ];

        if (mb_strlen($name) < 2) $formError = 'Nom trop court.';
        elseif (strlen($slug) < 2) $formError = 'Le slug est trop court.';
        elseif (!isset(categories()[$category])) $formError = 'Catégorie invalide.';
        elseif ($price < 0 || $price > 100_000_000) $formError = 'Prix invalide.';
        elseif ($oldPrice !== null && ($oldPrice < 0 || $oldPrice > 100_000_000)) $formError = 'Prix barré invalide.';
        elseif ($stock < 0 || $stock > 1_000_000) $formError = 'Stock invalide.';

        if (!$formError) {
            $dupe = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id <> ?');
            $dupe->execute([$slug, $id]);
            if ($dupe->fetchColumn()) $formError = 'Ce slug est déjà utilisé par un autre produit.';
        }

        if (!$formError) {
            try {
                $uploaded = store_image_upload($_FILES['image'] ?? [], 'products');
                if ($uploaded) {
                    $image = $uploaded;
                } elseif (!empty($_POST['remove_image'])) {
                    $image = null;
                }
            } catch (RuntimeException $e) {
                $formError = $e->getMessage();
            }
        }

        if (!$formError) {
            $vals = [$slug, $name, $category, $price, $oldPrice, $stock, $draft['weight'], $draft['description'],
                (int) $draft['is_new'], (int) $draft['best_seller'], (int) $draft['is_active'], (int) $draft['is_listed'], $image];
            if ($existing) {
                $pdo->prepare('UPDATE products SET slug=?, name=?, category=?, price=?, old_price=?, stock=?, weight=?, description=?, is_new=?, best_seller=?, is_active=?, is_listed=?, image_url=? WHERE id=?')
                    ->execute([...$vals, $id]);
                if ($existing['image_url'] !== $image) delete_uploaded_image($existing['image_url']);
            } else {
                $pdo->prepare('INSERT INTO products (slug, name, category, price, old_price, stock, weight, description, is_new, best_seller, is_active, is_listed, image_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute($vals);
                $id = (int) $pdo->lastInsertId();
            }
            flash('success', 'Produit enregistré');
            redirect(url('admin/produits') . '#p' . $id);
        }
    }
}

if (!$draft) {
    if (isset($_GET['edit']) && ($p = find_product((int) $_GET['edit']))) {
        $draft = $p;
    } elseif (isset($_GET['nouveau'])) {
        $firstCat = array_key_first(categories()) ?? '';
        $askCat = str_input('cat', 40, $_GET);
        $draft = ['id' => null, 'name' => '', 'slug' => '', 'category' => isset(categories()[$askCat]) ? $askCat : $firstCat, 'price' => '', 'old_price' => null, 'stock' => 0,
            'weight' => '', 'description' => '', 'is_new' => false, 'best_seller' => false, 'is_active' => true, 'is_listed' => true, 'image_url' => null];
    }
}

$all = get_products(false);
$q = str_input('q', 100, $_GET);
$vue = in_array($_GET['vue'] ?? '', ['en_ligne', 'masques', 'hors_ligne', 'stock'], true) ? $_GET['vue'] : 'tous';
$needle = normalize_text($q);
$list = array_values(array_filter($all, static function ($p) use ($needle, $vue) {
    if ($needle !== '' && !str_contains(normalize_text($p['name'] . ' ' . $p['slug'] . ' ' . category_of($p['category'])['name']), $needle)) return false;
    return match ($vue) {
        'en_ligne' => $p['is_active'] && $p['is_listed'],
        'masques' => $p['is_active'] && !$p['is_listed'],
        'hors_ligne' => !$p['is_active'],
        'stock' => $p['stock'] < 5,
        default => true,
    };
}));

// Statistiques : vues (fiche produit), commandes, quantités et chiffre d'affaires (hors commandes annulées)
$views = [];
foreach (db()->query('SELECT product_id, SUM(views) n FROM product_views GROUP BY product_id') as $r) {
    $views[(int) $r['product_id']] = (int) $r['n'];
}
$sales = [];
foreach (db()->query("SELECT oi.product_id, COUNT(DISTINCT o.id) orders, SUM(oi.quantity) qty, SUM(oi.quantity * oi.unit_price) amount
    FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'annulee' AND oi.product_id IS NOT NULL GROUP BY oi.product_id") as $r) {
    $sales[(int) $r['product_id']] = ['orders' => (int) $r['orders'], 'qty' => (int) $r['qty'], 'amount' => (int) $r['amount']];
}
$conversion = static function (int $orders, int $views): string {
    if ($views === 0) return $orders ? '—' : '0%';
    $pct = min(100, $orders / $views * 100); // commandes passées avant le suivi des vues
    return ($pct >= 10 || $pct == 0 ? (string) round($pct) : number_format($pct, 1, ',', '')) . '%';
};

// Vue « Statistiques » d'un produit
$statsProduct = isset($_GET['stats']) ? find_product((int) $_GET['stats']) : null;
$statsDays = [];
$statsOrders = [];
if ($statsProduct) {
    $pid = $statsProduct['id'];
    for ($i = 13; $i >= 0; $i--) {
        $statsDays[date('Y-m-d', strtotime("-$i days"))] = ['views' => 0, 'qty' => 0];
    }
    $st = db()->prepare('SELECT day, views FROM product_views WHERE product_id = ? AND day >= ?');
    $st->execute([$pid, array_key_first($statsDays)]);
    foreach ($st as $r) if (isset($statsDays[$r['day']])) $statsDays[$r['day']]['views'] = (int) $r['views'];
    $st = db()->prepare("SELECT DATE(o.created_at) d, SUM(oi.quantity) q FROM order_items oi JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_id = ? AND o.status <> 'annulee' AND o.created_at >= ? GROUP BY DATE(o.created_at)");
    $st->execute([$pid, array_key_first($statsDays) . ' 00:00:00']);
    foreach ($st as $r) if (isset($statsDays[$r['d']])) $statsDays[$r['d']]['qty'] = (int) $r['q'];
    $st = db()->prepare('SELECT o.ref, o.full_name, o.status, o.created_at, oi.quantity, oi.unit_price FROM order_items oi JOIN orders o ON o.id = oi.order_id
        WHERE oi.product_id = ? ORDER BY o.created_at DESC LIMIT 6');
    $st->execute([$pid]);
    $statsOrders = $st->fetchAll();
}

admin_header('Produits', 'produits');
?>
<div class="pp-head">
  <div><h1>Pages produits</h1><p><?= count($all) ?> produit<?= count($all) > 1 ? 's' : '' ?> au catalogue</p></div>
</div>
<div class="pp-tools">
  <button type="button" class="pp-tool<?= $q !== '' ? ' is-on' : '' ?>" data-toggle="#pp-search" aria-label="Rechercher"><?= icon('search') ?></button>
  <button type="button" class="pp-tool<?= $vue !== 'tous' ? ' is-on' : '' ?>" data-toggle="#pp-filters"><?= icon('sliders') ?><span>Filtrer</span></button>
  <a class="pp-add" href="<?= e(url('admin/produits', ['nouveau' => 1])) ?>"><?= icon('plus') ?><span>Ajouter un produit</span></a>
</div>
<form class="pp-search" id="pp-search" method="get" <?= $q === '' ? 'hidden' : '' ?>>
  <?= icon('search') ?><input name="q" value="<?= e($q) ?>" placeholder="Nom, catégorie…" autocomplete="off">
  <?php if ($vue !== 'tous'): ?><input type="hidden" name="vue" value="<?= e($vue) ?>"><?php endif; ?>
  <?php if ($q !== ''): ?><a href="<?= e(url('admin/produits', ['vue' => $vue === 'tous' ? '' : $vue])) ?>" aria-label="Effacer"><?= icon('x') ?></a><?php endif; ?>
</form>
<div class="chips pp-filters" id="pp-filters" <?= $vue === 'tous' ? 'hidden' : '' ?>>
  <?php foreach (['tous' => 'Tous', 'en_ligne' => 'Publiés', 'masques' => 'Masqués de la boutique', 'hors_ligne' => 'Hors ligne', 'stock' => 'Stock faible'] as $k => $l): ?>
    <a class="chip <?= $vue === $k ? 'is-active' : '' ?>" href="<?= e(url('admin/produits', ['vue' => $k === 'tous' ? '' : $k, 'q' => $q])) ?>"><?= e($l) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($draft): $isEdit = !empty($draft['id']); ?>
  <form class="panel pform" method="post" enctype="multipart/form-data" action="<?= e(url('admin/produits')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($draft['id'] ?? 0) ?>">
    <div class="pform-head">
      <h2><?= $isEdit ? 'Modifier le produit' : 'Nouveau produit' ?></h2>
      <a class="icon-btn" href="<?= e(url('admin/produits')) ?>" aria-label="Fermer"><?= icon('x') ?></a>
    </div>
    <?php if ($formError): ?><p class="alert-danger" style="margin:0 0 1rem"><?= e($formError) ?></p><?php endif; ?>
    <div class="pform-grid">
      <label class="label"><span>Nom</span><input class="field" name="name" required maxlength="120" value="<?= e($draft['name']) ?>" data-name></label>
      <label class="label"><span>Slug (adresse de la page)</span><input class="field" name="slug" maxlength="80" value="<?= e($draft['slug']) ?>" data-slug<?= $isEdit ? '' : ' data-auto' ?>><small>ex. thiakry-mil — laissé vide, il est créé à partir du nom</small></label>
      <label class="label"><span>Catégorie · <a href="<?= e(url('admin/categories', ['nouveau' => 1])) ?>" style="color:var(--primary);text-decoration:underline">créer une catégorie</a></span>
        <select class="select" name="category">
          <?php foreach (categories() as $slug => $c): ?><option value="<?= e($slug) ?>" <?= $draft['category'] === $slug ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="label"><span>Poids / conditionnement</span><input class="field" name="weight" maxlength="40" placeholder="500 g" value="<?= e($draft['weight']) ?>"></label>
      <label class="label"><span>Prix (FCFA)</span><input class="field" type="number" name="price" min="0" required value="<?= e($draft['price']) ?>"></label>
      <label class="label"><span>Prix barré (facultatif)</span><input class="field" type="number" name="old_price" min="0" value="<?= e($draft['old_price'] ?? '') ?>"></label>
      <label class="label"><span>Stock</span><input class="field" type="number" name="stock" min="0" required value="<?= e($draft['stock']) ?>"></label>
    </div>

    <div class="pform-image">
      <img src="<?= e(product_image(['category' => $draft['category'], 'image_url' => $draft['image_url']])) ?>" alt="" data-preview>
      <label class="label"><span>Photo du produit (JPG, PNG ou WebP, 5 Mo max)</span>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-file>
        <small><?= $draft['image_url'] ? 'Une photo est enregistrée. Choisissez un fichier pour la remplacer.' : "Sans photo, l'image de la catégorie est utilisée." ?></small>
        <?php if ($draft['image_url']): ?><label class="check" style="margin-top:.5rem"><input type="checkbox" name="remove_image" value="1">Retirer la photo actuelle</label><?php endif; ?>
      </label>
    </div>

    <label class="label" style="margin-top:.75rem"><span>Description</span><textarea class="field" name="description" rows="3" maxlength="2000"><?= e($draft['description']) ?></textarea></label>

    <div class="pform-checks">
      <label class="check"><input type="checkbox" name="is_new" value="1" <?= $draft['is_new'] ? 'checked' : '' ?>>Nouveau</label>
      <label class="check"><input type="checkbox" name="best_seller" value="1" <?= $draft['best_seller'] ? 'checked' : '' ?>>Meilleure vente</label>
      <label class="check"><input type="checkbox" name="is_listed" value="1" <?= ($draft['is_listed'] ?? true) ? 'checked' : '' ?>>Visible dans la boutique</label>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $draft['is_active'] ? 'checked' : '' ?>>Page produit en ligne</label>
    </div>
    <div class="pform-actions">
      <button class="btn btn-primary">Enregistrer</button>
      <a class="btn btn-outline" href="<?= e(url('admin/produits')) ?>">Annuler</a>
    </div>
  </form>
  <script>
  (function () {
    var name = document.querySelector("[data-name]"), slug = document.querySelector("[data-slug]");
    function slugify(s) { return s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 80); }
    if (slug.hasAttribute("data-auto")) {
      slug.addEventListener("input", function () { slug.removeAttribute("data-auto"); });
      name.addEventListener("input", function () { if (slug.hasAttribute("data-auto")) slug.value = slugify(name.value); });
    }
    document.querySelector("[data-file]").addEventListener("change", function (e) {
      var f = e.target.files[0]; if (f) document.querySelector("[data-preview]").src = URL.createObjectURL(f);
    });
  })();
  </script>
<?php endif; ?>

<?php if ($statsProduct): $sp = $statsProduct; $ss = $sales[$sp['id']] ?? ['orders' => 0, 'qty' => 0, 'amount' => 0]; $sv = $views[$sp['id']] ?? 0;
    $maxV = max(1, ...array_column($statsDays, 'views'), ...array_column($statsDays, 'qty')); ?>
  <section class="pp-stats" id="stats">
    <div class="pp-stats-head">
      <img src="<?= e(product_image($sp)) ?>" alt="">
      <div><p class="pp-stats-kicker">Statistiques</p><h2><?= e($sp['name']) ?></h2></div>
      <a class="pp-icon" href="<?= e(url('admin/produits')) ?>" aria-label="Fermer"><?= icon('x') ?></a>
    </div>
    <div class="pp-kpis">
      <div><b><?= $sv ?></b><span>Vues de la page</span></div>
      <div><b><?= $ss['orders'] ?></b><span>Commandes</span></div>
      <div><b><?= e($conversion($ss['orders'], $sv)) ?></b><span>Conversion</span></div>
      <div><b><?= $ss['qty'] ?></b><span>Unités vendues</span></div>
      <div><b><?= e(fcfa($ss['amount'])) ?></b><span>Chiffre d'affaires</span></div>
    </div>
    <div class="pp-chart">
      <div class="pp-chart-legend"><span><i class="lg-v"></i>Vues</span><span><i class="lg-q"></i>Unités vendues</span><em>14 derniers jours</em></div>
      <div class="pp-bars">
        <?php foreach ($statsDays as $d => $v): ?>
          <div class="pp-day" title="<?= e(fr_date($d . ' 12:00:00', 'day')) ?> : <?= $v['views'] ?> vue(s), <?= $v['qty'] ?> vendu(s)">
            <div class="pp-pair"><i class="b-v" style="height:<?= round($v['views'] / $maxV * 100, 1) ?>%"></i><i class="b-q" style="height:<?= round($v['qty'] / $maxV * 100, 1) ?>%"></i></div>
            <small><?= (int) date('j', strtotime($d)) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($statsOrders): ?>
      <p class="pp-sub">Dernières commandes de ce produit</p>
      <div class="pp-orders">
        <?php foreach ($statsOrders as $o): ?>
          <a href="<?= e(url('admin/commandes', ['q' => $o['ref']])) ?>"><span><b><?= e($o['full_name']) ?></b><small><?= e($o['ref']) ?> · <?= e(fr_date($o['created_at'])) ?></small></span><span class="r"><b>× <?= (int) $o['quantity'] ?></b><?= status_pill($o['status']) ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php else: ?><p class="pp-sub">Aucune commande pour ce produit pour l'instant.</p><?php endif; ?>
  </section>
<?php endif; ?>

<div class="pp-grid">
  <?php foreach ($list as $p):
      $ps = $sales[$p['id']] ?? ['orders' => 0, 'qty' => 0, 'amount' => 0]; $pv = $views[$p['id']] ?? 0;
      [$stateLabel, $stateCls] = !$p['is_active'] ? ['Hors ligne', 'off'] : (!$p['is_listed'] ? ['Masqué', 'hidden'] : ['Publié', 'on']);
      $hidden = ['f_q' => $q, 'f_vue' => $vue === 'tous' ? '' : $vue];
      $hiddenInputs = csrf_field() . '<input type="hidden" name="id" value="' . $p['id'] . '"><input type="hidden" name="f_q" value="' . e($q) . '"><input type="hidden" name="f_vue" value="' . e($hidden['f_vue']) . '">'; ?>
    <article class="pp-card" id="p<?= $p['id'] ?>">
      <div class="pp-top">
        <img class="pp-img" src="<?= e(product_image($p)) ?>" alt="">
        <div class="pp-info">
          <div class="pp-title-row"><h3><?= e($p['name']) ?></h3><span class="pp-state <?= $stateCls ?>"><?= $stateLabel ?></span></div>
          <p class="pp-desc"><?= e(category_of($p['category'])['name']) ?><?= $p['weight'] ? ' · ' . e($p['weight']) : '' ?></p>
          <div class="pp-price"><b><?= fcfa($p['price']) ?></b><?php if ($p['old_price'] !== null): ?><s><?= fcfa($p['old_price']) ?></s><?php endif; ?>
            <span class="pp-stock <?= $p['stock'] === 0 ? 'out' : ($p['stock'] < 5 ? 'low' : '') ?>"><?= $p['stock'] === 0 ? 'Rupture' : 'Stock : ' . $p['stock'] ?></span></div>
        </div>
      </div>
      <div class="pp-numbers">
        <div><b><?= $pv ?></b><span>Vues</span></div>
        <div><b><?= $ps['orders'] ?></b><span>Commandes</span></div>
        <div><b><?= e($conversion($ps['orders'], $pv)) ?></b><span>Conversion</span></div>
      </div>
      <div class="pp-switches">
        <form method="post"><?= $hiddenInputs ?><input type="hidden" name="action" value="toggle_listed">
          <div><b>Boutique</b><span><?= $p['is_listed'] ? 'Visible' : 'Masqué' ?></span></div>
          <button class="pp-switch <?= $p['is_listed'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $p['is_listed'] ? 'true' : 'false' ?>" aria-label="Visible dans la boutique"></button>
        </form>
        <form method="post"><?= $hiddenInputs ?><input type="hidden" name="action" value="toggle_active">
          <div><b>Page en ligne</b><span><?= $p['is_active'] ? 'Active' : 'Désactivée' ?></span></div>
          <button class="pp-switch <?= $p['is_active'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $p['is_active'] ? 'true' : 'false' ?>" aria-label="Page produit en ligne"></button>
        </form>
      </div>
      <div class="pp-actions">
        <a class="pp-btn primary" href="<?= e(url('admin/produits', ['edit' => $p['id']])) ?>"><?= icon('pencil') ?>Modifier</a>
        <?php if ($p['is_active']): ?><a class="pp-btn" href="<?= e(url('produit/' . $p['slug'])) ?>" target="_blank"><?= icon('eye') ?>Voir</a><?php endif; ?>
        <a class="pp-btn" href="<?= e(url('admin/produits', ['stats' => $p['id']])) ?>#stats"><?= icon('chart') ?>Statistiques</a>
      </div>
      <div class="pp-icons">
        <form method="post"><?= $hiddenInputs ?><input type="hidden" name="action" value="duplicate"><button class="pp-icon" title="Dupliquer" aria-label="Dupliquer"><?= icon('copy') ?></button></form>
        <form method="post"><?= $hiddenInputs ?><input type="hidden" name="action" value="toggle_listed"><button class="pp-icon" title="<?= $p['is_listed'] ? 'Masquer de la boutique' : 'Afficher dans la boutique' ?>" aria-label="<?= $p['is_listed'] ? 'Masquer de la boutique' : 'Afficher dans la boutique' ?>"><?= icon($p['is_listed'] ? 'eye-off' : 'eye') ?></button></form>
        <form method="post"><?= $hiddenInputs ?><input type="hidden" name="action" value="toggle_active"><button class="pp-icon red" title="<?= $p['is_active'] ? 'Mettre la page hors ligne' : 'Remettre la page en ligne' ?>" aria-label="<?= $p['is_active'] ? 'Mettre la page hors ligne' : 'Remettre la page en ligne' ?>"><?= icon('power') ?></button></form>
        <form method="post" data-confirm="Supprimer « <?= e($p['name']) ?> » ? Cette action est définitive."><?= $hiddenInputs ?><input type="hidden" name="action" value="delete"><button class="pp-icon red" title="Supprimer" aria-label="Supprimer"><?= icon('trash') ?></button></form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php if (!$list): ?><div class="panel empty-panel">Aucun produit trouvé.</div><?php endif; ?>
<script>
document.querySelectorAll("[data-toggle]").forEach(function (b) {
  b.addEventListener("click", function () {
    var el = document.querySelector(b.getAttribute("data-toggle")); el.hidden = !el.hidden; b.classList.toggle("is-on", !el.hidden);
    var i = el.querySelector("input:not([type=hidden])"); if (!el.hidden && i) i.focus();
  });
});
</script>
<?php admin_footer(); ?>
