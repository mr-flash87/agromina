<?php
require __DIR__ . '/_layout.php';

function find_post(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

$draft = null;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $pdo = db();

    if ($action === 'toggle' && ($p = find_post($id))) {
        $pdo->prepare('UPDATE posts SET is_published = ? WHERE id = ?')->execute([$p['is_published'] ? 0 : 1, $id]);
        flash('success', $p['is_published'] ? 'Article repassé en brouillon' : 'Article publié');
        redirect(url('admin/blog') . '#a' . $id);
    }

    if ($action === 'delete' && ($p = find_post($id))) {
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
        delete_uploaded_image($p['image_url']);
        flash('success', 'Article supprimé');
        redirect(url('admin/blog'));
    }

    if ($action === 'save') {
        $existing = $id ? find_post($id) : null;
        $title = str_input('title', 160);
        $slug = slugify(str_input('slug', 100) ?: $title);
        $date = str_input('published_at', 10);
        $publishedAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date . ' ' . ($existing ? substr($existing['published_at'], 11) : date('H:i:s')) : date('Y-m-d H:i:s');
        $image = $existing['image_url'] ?? null;
        $draft = [
            'id' => $id ?: null, 'title' => $title, 'slug' => $slug, 'excerpt' => str_input('excerpt', 300),
            'content' => str_input('content', 60000), 'image_url' => $image,
            'is_published' => !empty($_POST['is_published']), 'published_at' => $publishedAt,
        ];

        if (mb_strlen($title) < 3) $formError = 'Le titre est trop court.';
        elseif (strlen($slug) < 2) $formError = "L'adresse (slug) est trop courte.";
        elseif (mb_strlen($draft['content']) < 10) $formError = "Écrivez le contenu de l'article.";

        if (!$formError) {
            $dupe = $pdo->prepare('SELECT id FROM posts WHERE slug = ? AND id <> ?');
            $dupe->execute([$slug, $id]);
            if ($dupe->fetchColumn()) $formError = 'Cette adresse (slug) est déjà utilisée par un autre article.';
        }
        if (!$formError) {
            try {
                $uploaded = store_image_upload($_FILES['image'] ?? [], 'blog');
                if ($uploaded) $image = $uploaded;
                elseif (!empty($_POST['remove_image'])) $image = null;
            } catch (RuntimeException $e) {
                $formError = $e->getMessage();
            }
        }
        if (!$formError) {
            $vals = [$slug, $title, $draft['excerpt'], $draft['content'], $image, (int) $draft['is_published'], $publishedAt];
            if ($existing) {
                $pdo->prepare('UPDATE posts SET slug=?, title=?, excerpt=?, content=?, image_url=?, is_published=?, published_at=? WHERE id=?')->execute([...$vals, $id]);
                if ($existing['image_url'] !== $image) delete_uploaded_image($existing['image_url']);
            } else {
                $pdo->prepare('INSERT INTO posts (slug, title, excerpt, content, image_url, is_published, published_at) VALUES (?,?,?,?,?,?,?)')->execute($vals);
                $id = (int) $pdo->lastInsertId();
            }
            flash('success', $draft['is_published'] ? 'Article publié' : 'Brouillon enregistré');
            redirect(url('admin/blog') . '#a' . $id);
        }
    }
}

if (!$draft) {
    if (isset($_GET['edit']) && ($p = find_post((int) $_GET['edit']))) {
        $draft = $p;
    } elseif (isset($_GET['nouveau'])) {
        $draft = ['id' => null, 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'image_url' => null, 'is_published' => true, 'published_at' => date('Y-m-d H:i:s')];
    }
}

$posts = get_posts(false);
$now = time();

admin_header('Blog', 'blog');
admin_title('Blog', count($posts) . ' article(s)', '<a class="btn btn-primary" href="' . e(url('admin/blog', ['nouveau' => 1])) . '">' . icon('plus') . 'Écrire un article</a>');

if ($draft): $isEdit = !empty($draft['id']); ?>
  <form class="panel pform" method="post" enctype="multipart/form-data" action="<?= e(url('admin/blog')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($draft['id'] ?? 0) ?>">
    <div class="pform-head">
      <h2><?= $isEdit ? "Modifier l'article" : 'Nouvel article' ?></h2>
      <a class="icon-btn" href="<?= e(url('admin/blog')) ?>" aria-label="Fermer"><?= icon('x') ?></a>
    </div>
    <?php if ($formError): ?><p class="alert-danger" style="margin:0 0 1rem"><?= e($formError) ?></p><?php endif; ?>

    <div class="pform-grid">
      <label class="label" style="grid-column:1/-1"><span>Titre</span><input class="field" name="title" required maxlength="160" value="<?= e($draft['title']) ?>" data-name placeholder="Ex : Le fonio, céréale ancestrale aux mille vertus"></label>
      <label class="label"><span>Adresse de la page (slug)</span><input class="field" name="slug" maxlength="100" value="<?= e($draft['slug']) ?>" data-slug<?= $isEdit ? '' : ' data-auto' ?>><small>Créée automatiquement à partir du titre</small></label>
      <label class="label"><span>Date de publication</span><input class="field" type="date" name="published_at" value="<?= e(substr($draft['published_at'], 0, 10)) ?>"><small>Une date future programme l'article.</small></label>
      <label class="label" style="grid-column:1/-1"><span>Résumé (affiché sur la liste des articles)</span><textarea class="field" name="excerpt" rows="2" maxlength="300"><?= e($draft['excerpt']) ?></textarea></label>
    </div>

    <div class="pform-image">
      <img src="<?= e(media_url($draft['image_url'])) ?>" alt="" data-preview>
      <label class="label"><span>Photo de couverture (JPG, PNG ou WebP, 5 Mo max)</span>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-file>
        <?php if ($draft['image_url']): ?><label class="check" style="margin-top:.5rem"><input type="checkbox" name="remove_image" value="1">Retirer la photo actuelle</label><?php endif; ?>
      </label>
    </div>

    <label class="label" style="margin-top:.75rem"><span>Contenu de l'article</span>
      <textarea class="field editor" name="content" rows="14" required><?= e($draft['content']) ?></textarea>
    </label>
    <div class="editor-help">
      <b>Mise en forme :</b> laissez une ligne vide entre deux paragraphes ·
      <code>## Intertitre</code> pour un sous-titre ·
      <code>- élément</code> pour une liste ·
      <code>**texte**</code> pour mettre en <b>gras</b>.
    </div>

    <div class="pform-checks">
      <label class="check"><input type="checkbox" name="is_published" value="1" <?= $draft['is_published'] ? 'checked' : '' ?>>Publier sur le site (sinon : brouillon)</label>
    </div>
    <div class="pform-actions">
      <button class="btn btn-primary">Enregistrer</button>
      <a class="btn btn-outline" href="<?= e(url('admin/blog')) ?>">Annuler</a>
    </div>
  </form>
  <script>
  (function () {
    var name = document.querySelector("[data-name]"), slug = document.querySelector("[data-slug]");
    function slugify(s) { return s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 100); }
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

<div class="prod-grid">
  <?php foreach ($posts as $p):
      $scheduled = $p['is_published'] && strtotime($p['published_at'] . ' UTC') > $now;
      $state = !$p['is_published'] ? 'Brouillon' : ($scheduled ? 'Programmé' : 'Publié'); ?>
    <div class="panel aprod" id="a<?= (int) $p['id'] ?>">
      <div class="aprod-top">
        <img src="<?= e(media_url($p['image_url'])) ?>" alt="">
        <div class="aprod-info">
          <div class="aprod-name"><p><?= e($p['title']) ?></p><span class="state <?= $state === 'Publié' ? 'on' : '' ?>"><?= $state ?></span></div>
          <p class="aprod-cat"><?= e(post_date($p)) ?></p>
          <p class="aprod-cat" style="white-space:normal;margin-top:.375rem"><?= e(mb_strimwidth($p['excerpt'], 0, 110, '…')) ?></p>
        </div>
      </div>
      <form method="post" class="aprod-vis" style="border-top:1px solid var(--border)">
        <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <div><p>Visible sur le site</p><small><?= $p['is_published'] ? 'Les visiteurs le voient' : 'Brouillon, caché aux visiteurs' ?></small></div>
        <button class="switch <?= $p['is_published'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $p['is_published'] ? 'true' : 'false' ?>" aria-label="Visible sur le site"></button>
      </form>
      <div class="aprod-actions">
        <a class="btn btn-primary" href="<?= e(url('admin/blog', ['edit' => $p['id']])) ?>"><?= icon('pencil') ?>Modifier</a>
        <?php if ($state === 'Publié'): ?><a class="btn btn-outline" href="<?= e(url('blog/' . $p['slug'])) ?>" target="_blank"><?= icon('eye') ?>Voir</a><?php endif; ?>
        <div class="push">
          <form method="post" data-confirm="Supprimer l'article « <?= e($p['title']) ?> » ? Cette action est définitive."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="icon-btn danger" aria-label="Supprimer" title="Supprimer"><?= icon('trash') ?></button></form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$posts): ?><div class="panel empty-panel">Aucun article. Cliquez sur « Écrire un article » pour commencer.</div><?php endif; ?>
<?php admin_footer(); ?>
