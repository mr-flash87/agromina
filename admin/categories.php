<?php
require __DIR__ . '/_layout.php';

function find_category(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

$draft = null;
$formError = '';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if (in_array($action, ['up', 'down'], true) && find_category($id)) {
        // Réécrit les positions dans l'ordre affiché puis échange avec la voisine.
        $ids = array_map('intval', $pdo->query('SELECT id FROM categories ORDER BY position, name')->fetchAll(PDO::FETCH_COLUMN));
        $i = array_search($id, $ids, true);
        $j = $action === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        $upd = $pdo->prepare('UPDATE categories SET position = ? WHERE id = ?');
        foreach ($ids as $pos => $cid) {
            $upd->execute([$pos + 1, $cid]);
        }
        redirect(url('admin/categories') . '#c' . $id);
    }

    if ($action === 'delete' && ($c = find_category($id))) {
        $count = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
        $count->execute([$c['slug']]);
        $n = (int) $count->fetchColumn();
        $target = str_input('move_to', 40);
        if ($n > 0) {
            $to = $pdo->prepare('SELECT slug FROM categories WHERE slug = ? AND id <> ?');
            $to->execute([$target, $id]);
            if (!$to->fetchColumn()) {
                flash('error', "Choisissez la catégorie qui recevra les $n produit(s) de « {$c['name']} ».");
                redirect(url('admin/categories') . '#c' . $id);
            }
            $pdo->prepare('UPDATE products SET category = ? WHERE category = ?')->execute([$target, $c['slug']]);
        }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        delete_uploaded_image($c['image_url']);
        flash('success', 'Catégorie supprimée' . ($n ? " ($n produit(s) déplacé(s))" : ''));
        redirect(url('admin/categories'));
    }

    if ($action === 'save') {
        $existing = $id ? find_category($id) : null;
        $name = str_input('name', 80);
        $slug = slugify(str_input('slug', 40) ?: $name);
        $slug = substr($slug, 0, 40);
        $image = $existing['image_url'] ?? null;
        $draft = ['id' => $id ?: null, 'name' => $name, 'slug' => $slug, 'short_name' => str_input('short_name', 30),
            'description' => str_input('description', 300), 'image_url' => $image];

        if (mb_strlen($name) < 2) $formError = 'Donnez un nom à la catégorie.';
        elseif (strlen($slug) < 2) $formError = "L'adresse (slug) est trop courte.";
        if (!$formError) {
            $dupe = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
            $dupe->execute([$slug, $id]);
            if ($dupe->fetchColumn()) $formError = 'Une autre catégorie utilise déjà cette adresse (slug).';
        }
        if (!$formError) {
            try {
                $uploaded = store_image_upload($_FILES['image'] ?? [], 'categories');
                if ($uploaded) $image = $uploaded;
                elseif (!$existing && !$image) $formError = 'Ajoutez une photo pour la catégorie.';
            } catch (RuntimeException $e) {
                $formError = $e->getMessage();
            }
        }
        if (!$formError) {
            $pdo->beginTransaction();
            if ($existing) {
                $pdo->prepare('UPDATE categories SET slug=?, name=?, short_name=?, description=?, image_url=? WHERE id=?')
                    ->execute([$slug, $name, $draft['short_name'], $draft['description'], $image, $id]);
                if ($existing['slug'] !== $slug) {
                    $pdo->prepare('UPDATE products SET category = ? WHERE category = ?')->execute([$slug, $existing['slug']]);
                }
            } else {
                $pos = (int) $pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM categories')->fetchColumn();
                $pdo->prepare('INSERT INTO categories (slug, name, short_name, description, image_url, position) VALUES (?,?,?,?,?,?)')
                    ->execute([$slug, $name, $draft['short_name'], $draft['description'], $image, $pos]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->commit();
            if ($existing && $existing['image_url'] !== $image) delete_uploaded_image($existing['image_url']);
            flash('success', 'Catégorie enregistrée');
            redirect(url('admin/categories') . '#c' . $id);
        }
    }
}

if (!$draft) {
    if (isset($_GET['edit']) && ($c = find_category((int) $_GET['edit']))) {
        $draft = $c;
    } elseif (isset($_GET['nouveau'])) {
        $draft = ['id' => null, 'name' => '', 'slug' => '', 'short_name' => '', 'description' => '', 'image_url' => null];
    }
}

$cats = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category = c.slug) n,
    (SELECT COUNT(*) FROM products p WHERE p.category = c.slug AND p.is_active = 1) visible
    FROM categories c ORDER BY c.position, c.name')->fetchAll();
$orphans = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE category NOT IN (SELECT slug FROM categories)')->fetchColumn();

admin_header('Catégories', 'categories');
admin_title('Catégories', count($cats) . ' catégorie(s) · elles organisent la boutique, l\'accueil et le menu',
    '<a class="btn btn-primary" href="' . e(url('admin/categories', ['nouveau' => 1])) . '">' . icon('plus') . 'Nouvelle catégorie</a>');

if ($draft): $isEdit = !empty($draft['id']); ?>
  <form class="panel pform" method="post" enctype="multipart/form-data" action="<?= e(url('admin/categories')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($draft['id'] ?? 0) ?>">
    <div class="pform-head">
      <h2><?= $isEdit ? 'Modifier la catégorie' : 'Nouvelle catégorie' ?></h2>
      <a class="icon-btn" href="<?= e(url('admin/categories')) ?>" aria-label="Fermer"><?= icon('x') ?></a>
    </div>
    <?php if ($formError): ?><p class="alert-danger" style="margin:0 0 1rem"><?= e($formError) ?></p><?php endif; ?>
    <div class="pform-grid">
      <label class="label"><span>Nom de la catégorie</span><input class="field" name="name" required maxlength="80" value="<?= e($draft['name']) ?>" placeholder="Ex : Épices et condiments" data-name></label>
      <label class="label"><span>Nom court (pastilles et fiches produits)</span><input class="field" name="short_name" maxlength="30" value="<?= e($draft['short_name']) ?>" placeholder="Ex : Épices"><small>Vide = le nom complet est utilisé.</small></label>
      <label class="label"><span>Adresse (slug)</span><input class="field" name="slug" maxlength="40" value="<?= e($draft['slug']) ?>" data-slug<?= $isEdit ? '' : ' data-auto' ?>><small>Créée à partir du nom. Si vous la changez, les produits suivent automatiquement.</small></label>
      <label class="label"><span>Description (facultatif)</span><input class="field" name="description" maxlength="300" value="<?= e($draft['description']) ?>" placeholder="Affichée sur la page Catégories"></label>
    </div>
    <div class="pform-image">
      <img src="<?= e(media_url($draft['image_url'], 'cat-autres.jpg')) ?>" alt="" data-preview>
      <label class="label"><span>Photo de la catégorie (JPG, PNG ou WebP, 5 Mo max)<?= $isEdit ? '' : ' – obligatoire' ?></span>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-file <?= $isEdit ? '' : 'required' ?>>
        <small>Affichée sur l'accueil, la page Catégories et la boutique. Elle sert aussi d'image aux produits sans photo. Format paysage conseillé.</small>
      </label>
    </div>
    <div class="pform-actions">
      <button class="btn btn-primary">Enregistrer</button>
      <a class="btn btn-outline" href="<?= e(url('admin/categories')) ?>">Annuler</a>
    </div>
  </form>
  <script>
  (function () {
    var name = document.querySelector("[data-name]"), slug = document.querySelector("[data-slug]");
    function slugify(s) { return s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 40); }
    if (slug.hasAttribute("data-auto")) {
      slug.addEventListener("input", function () { slug.removeAttribute("data-auto"); });
      name.addEventListener("input", function () { if (slug.hasAttribute("data-auto")) slug.value = slugify(name.value); });
    }
    document.querySelector("[data-file]").addEventListener("change", function (e) { var f = e.target.files[0]; if (f) document.querySelector("[data-preview]").src = URL.createObjectURL(f); });
  })();
  </script>
<?php endif; ?>

<?php if ($orphans): ?>
  <p class="alert-danger" style="margin:0 0 1rem"><?= $orphans ?> produit(s) n'ont plus de catégorie existante : modifiez-les dans Produits pour leur en choisir une.</p>
<?php endif; ?>

<div class="cat-admin-grid">
  <?php foreach ($cats as $i => $c): $n = (int) $c['n']; ?>
    <div class="panel cat-admin" id="c<?= (int) $c['id'] ?>">
      <div class="cat-admin-media">
        <img src="<?= e(media_url($c['image_url'], 'cat-autres.jpg')) ?>" alt="">
        <span class="cat-admin-pos"><?= $i + 1 ?></span>
      </div>
      <div class="cat-admin-body">
        <p class="cat-admin-name"><?= e($c['name']) ?></p>
        <p class="aprod-cat"><?= e($c['short_name'] ?: $c['name']) ?> · /<?= e($c['slug']) ?></p>
        <p class="cat-admin-count"><b><?= $n ?></b> produit<?= $n > 1 ? 's' : '' ?><?= $n && (int) $c['visible'] < $n ? ' (' . (int) $c['visible'] . ' en ligne)' : '' ?></p>
      </div>
      <div class="aprod-actions">
        <a class="btn btn-primary" href="<?= e(url('admin/categories', ['edit' => $c['id']])) ?>"><?= icon('pencil') ?>Modifier</a>
        <a class="btn btn-outline" href="<?= e(url('admin/produits', ['nouveau' => 1, 'cat' => $c['slug']])) ?>"><?= icon('plus') ?>Produit</a>
        <div class="push">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="up"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="icon-btn" aria-label="Monter" title="Monter" <?= $i === 0 ? 'disabled' : '' ?>><?= icon('chevron-up') ?></button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="down"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="icon-btn" aria-label="Descendre" title="Descendre" <?= $i === count($cats) - 1 ? 'disabled' : '' ?>><?= icon('chevron-down') ?></button></form>
          <details class="cat-del">
            <summary class="icon-btn danger" aria-label="Supprimer" title="Supprimer"><?= icon('trash') ?></summary>
            <form method="post" class="panel cat-del-box" data-confirm="Supprimer la catégorie « <?= e($c['name']) ?> » ?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <?php if ($n): ?>
                <label class="label"><span>Déplacer ses <?= $n ?> produit(s) vers :</span>
                  <select class="select" name="move_to" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($cats as $o): if ($o['id'] === $c['id']) continue; ?><option value="<?= e($o['slug']) ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
                  </select>
                </label>
              <?php else: ?><p class="muted" style="font-size:.875rem">Cette catégorie est vide.</p><?php endif; ?>
              <button class="btn btn-danger btn-sm">Supprimer la catégorie</button>
            </form>
          </details>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$cats): ?><div class="panel empty-panel">Aucune catégorie. Créez-en une avant d'ajouter des produits.</div><?php endif; ?>
<?php admin_footer(); ?>
