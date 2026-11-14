<?php
require __DIR__ . '/_layout.php';

function find_promo(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM promotions WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) {
        return null;
    }
    $ids = db()->prepare('SELECT product_id FROM promotion_products WHERE promotion_id = ? ORDER BY position');
    $ids->execute([$id]);
    $p['product_ids'] = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
    return $p;
}

function promo_status(array $p): array
{
    $now = time();
    $start = $p['starts_at'] ? strtotime($p['starts_at'] . ' UTC') : null;
    $end = $p['ends_at'] ? strtotime($p['ends_at'] . ' UTC') : null;
    if (!(int) $p['is_active']) return ['Désactivée', ''];
    if ($end !== null && $end <= $now) return ['Terminée', ''];
    if ($start !== null && $start > $now) return ['Programmée', 'sched'];
    return ['En cours', 'on'];
}

/** « 2026-11-20 » + heure → date UTC pour la base (le site est à l'heure de Dakar, UTC). */
function form_date(string $date, string $time): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    return $date . ' ' . $time;
}

$draft = null;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $pdo = db();

    if ($action === 'toggle' && ($p = find_promo($id))) {
        $pdo->prepare('UPDATE promotions SET is_active = ? WHERE id = ?')->execute([(int) $p['is_active'] ? 0 : 1, $id]);
        flash('success', (int) $p['is_active'] ? 'Promotion désactivée' : 'Promotion activée');
        redirect(url('admin/promotions') . '#pr' . $id);
    }
    if ($action === 'delete' && find_promo($id)) {
        $pdo->prepare('DELETE FROM promotions WHERE id = ?')->execute([$id]);
        flash('success', 'Promotion supprimée');
        redirect(url('admin/promotions'));
    }
    if ($action === 'save') {
        $productIds = array_values(array_unique(array_map('intval', (array) ($_POST['products'] ?? []))));
        $draft = [
            'id' => $id ?: null,
            'name' => str_input('name', 120),
            'badge' => mb_strtoupper(str_input('badge', 40)),
            'title' => str_input('title', 160),
            'description' => str_input('description', 400),
            'discount_percent' => max(0, min(90, (int) ($_POST['discount_percent'] ?? 0))),
            'starts_at' => form_date(str_input('starts_at', 10), '00:00:00'),
            'ends_at' => form_date(str_input('ends_at', 10), '23:59:59'),
            'is_active' => !empty($_POST['is_active']),
            'show_on_home' => !empty($_POST['show_on_home']),
            'product_ids' => $productIds,
        ];
        if (mb_strlen($draft['name']) < 2) $formError = 'Donnez un nom à la promotion.';
        elseif (!$productIds) $formError = 'Sélectionnez au moins un produit.';
        elseif ($draft['starts_at'] && $draft['ends_at'] && $draft['ends_at'] < $draft['starts_at']) $formError = 'La date de fin est avant la date de début.';

        if (!$formError) {
            $vals = [$draft['name'], $draft['badge'] ?: 'OFFRES SPÉCIALES', $draft['title'], $draft['description'], $draft['discount_percent'],
                $draft['starts_at'], $draft['ends_at'], (int) $draft['is_active'], (int) $draft['show_on_home']];
            $pdo->beginTransaction();
            if ($id && find_promo($id)) {
                $pdo->prepare('UPDATE promotions SET name=?, badge=?, title=?, description=?, discount_percent=?, starts_at=?, ends_at=?, is_active=?, show_on_home=? WHERE id=?')->execute([...$vals, $id]);
                $pdo->prepare('DELETE FROM promotion_products WHERE promotion_id = ?')->execute([$id]);
            } else {
                $pdo->prepare('INSERT INTO promotions (name, badge, title, description, discount_percent, starts_at, ends_at, is_active, show_on_home) VALUES (?,?,?,?,?,?,?,?,?)')->execute($vals);
                $id = (int) $pdo->lastInsertId();
            }
            $ins = $pdo->prepare('INSERT INTO promotion_products (promotion_id, product_id, position) SELECT ?, id, ? FROM products WHERE id = ?');
            foreach ($productIds as $pos => $pid) {
                $ins->execute([$id, $pos, $pid]);
            }
            $pdo->commit();
            flash('success', 'Promotion enregistrée');
            redirect(url('admin/promotions') . '#pr' . $id);
        }
    }
}

if (!$draft) {
    if (isset($_GET['edit']) && ($p = find_promo((int) $_GET['edit']))) {
        $draft = $p;
    } elseif (isset($_GET['nouveau'])) {
        $draft = ['id' => null, 'name' => '', 'badge' => 'OFFRES SPÉCIALES', 'title' => '', 'description' => '', 'discount_percent' => 10,
            'starts_at' => null, 'ends_at' => null, 'is_active' => true, 'show_on_home' => true, 'product_ids' => []];
    }
}

$promos = db()->query('SELECT p.*, (SELECT COUNT(*) FROM promotion_products pp WHERE pp.promotion_id = p.id) n FROM promotions p ORDER BY p.is_active DESC, p.updated_at DESC')->fetchAll();
$products = get_products(false);
$homeId = ($hp = home_promotion()) ? (int) $hp['id'] : 0;
$thumbs = [];
foreach (db()->query('SELECT pp.promotion_id, p.image_url, p.category FROM promotion_products pp JOIN products p ON p.id = pp.product_id ORDER BY pp.position') as $r) {
    if (count($thumbs[(int) $r['promotion_id']] ?? []) < 4) $thumbs[(int) $r['promotion_id']][] = product_image($r);
}

admin_header('Promotions', 'promotions');
admin_title('Promotions', 'Créez vos offres, choisissez les produits et la remise : les prix se mettent à jour tout seuls dans la boutique.',
    '<a class="btn btn-primary" href="' . e(url('admin/promotions', ['nouveau' => 1])) . '">' . icon('plus') . 'Créer une promotion</a>');

if ($draft): $isEdit = !empty($draft['id']); $sel = array_flip($draft['product_ids']); ?>
  <form class="panel pform" method="post" action="<?= e(url('admin/promotions')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($draft['id'] ?? 0) ?>">
    <div class="pform-head">
      <h2><?= $isEdit ? 'Modifier la promotion' : 'Nouvelle promotion' ?></h2>
      <a class="icon-btn" href="<?= e(url('admin/promotions')) ?>" aria-label="Fermer"><?= icon('x') ?></a>
    </div>
    <?php if ($formError): ?><p class="alert-danger" style="margin:0 0 1rem"><?= e($formError) ?></p><?php endif; ?>

    <div class="pform-grid">
      <label class="label"><span>Nom de la promotion (pour vous)</span><input class="field" name="name" required maxlength="120" value="<?= e($draft['name']) ?>" placeholder="Ex : Promo Tabaski"></label>
      <label class="label"><span>Remise sur les produits choisis</span>
        <div class="input-suffix"><input class="field" type="number" name="discount_percent" min="0" max="90" value="<?= (int) $draft['discount_percent'] ?>"><span>%</span></div>
        <small>0 % = mise en avant sans remise (les prix barrés déjà saisis restent affichés).</small>
      </label>
      <label class="label"><span>Début</span><input class="field" type="date" name="starts_at" value="<?= e($draft['starts_at'] ? substr($draft['starts_at'], 0, 10) : '') ?>"><small>Vide = tout de suite</small></label>
      <label class="label"><span>Fin</span><input class="field" type="date" name="ends_at" value="<?= e($draft['ends_at'] ? substr($draft['ends_at'], 0, 10) : '') ?>"><small>Vide = sans fin. La promo s'arrête seule à minuit.</small></label>
    </div>

    <fieldset class="pform-fieldset">
      <legend>Bandeau sur la page d'accueil</legend>
      <div class="pform-grid">
        <label class="label"><span>Étiquette</span><input class="field" name="badge" maxlength="40" value="<?= e($draft['badge']) ?>" placeholder="OFFRES SPÉCIALES"></label>
        <label class="label"><span>Titre</span><input class="field" name="title" maxlength="160" value="<?= e($draft['title']) ?>" placeholder="Ex : −15 % sur les jus pour la Tabaski"></label>
        <label class="label" style="grid-column:1/-1"><span>Texte</span><textarea class="field" name="description" rows="2" maxlength="400" placeholder="Ex : Bissap, bouye et gingembre à petit prix jusqu'à dimanche."><?= e($draft['description']) ?></textarea></label>
      </div>
      <label class="check" style="margin-top:.75rem"><input type="checkbox" name="show_on_home" value="1" <?= $draft['show_on_home'] ? 'checked' : '' ?>>Afficher cette promotion sur la page d'accueil (les 4 premiers produits choisis)</label>
    </fieldset>

    <fieldset class="pform-fieldset">
      <legend>Produits en promotion <span class="muted" data-pick-count>(<?= count($draft['product_ids']) ?> choisi<?= count($draft['product_ids']) > 1 ? 's' : '' ?>)</span></legend>
      <label class="a-search" style="margin-bottom:.75rem"><?= icon('search') ?><input type="search" placeholder="Filtrer les produits…" data-pick-filter></label>
      <div class="pick-grid">
        <?php foreach ($products as $p): $pct = (int) $draft['discount_percent']; ?>
          <label class="pick" data-pick-name="<?= e(normalize_text($p['name'])) ?>">
            <input type="checkbox" name="products[]" value="<?= $p['id'] ?>" <?= isset($sel[$p['id']]) ? 'checked' : '' ?>>
            <img src="<?= e(product_image($p)) ?>" alt="">
            <span class="pick-info">
              <b><?= e($p['name']) ?></b>
              <small><span data-base="<?= $p['price'] ?>"><?= fcfa($p['price']) ?></span><?= $p['is_active'] ? '' : ' · masqué' ?></small>
            </span>
            <span class="pick-tick"><?= icon('check') ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="pform-checks">
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $draft['is_active'] ? 'checked' : '' ?>>Promotion activée</label>
    </div>
    <div class="pform-actions">
      <button class="btn btn-primary">Enregistrer</button>
      <a class="btn btn-outline" href="<?= e(url('admin/promotions')) ?>">Annuler</a>
    </div>
  </form>
  <script>
  (function () {
    var f = document.querySelector("[data-pick-filter]"), count = document.querySelector("[data-pick-count]");
    var pct = document.querySelector("[name=discount_percent]");
    function fcfa(n) { return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " FCFA"; }
    function refresh() {
      var n = document.querySelectorAll(".pick input:checked").length;
      count.textContent = "(" + n + " choisi" + (n > 1 ? "s" : "") + ")";
      var d = Math.max(0, Math.min(90, parseInt(pct.value, 10) || 0));
      document.querySelectorAll("[data-base]").forEach(function (el) {
        var b = parseInt(el.getAttribute("data-base"), 10);
        el.innerHTML = d ? "<s>" + fcfa(b) + "</s> → <b>" + fcfa(Math.round(b * (100 - d) / 100 / 5) * 5) + "</b>" : fcfa(b);
      });
    }
    f.addEventListener("input", function () {
      var q = f.value.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
      document.querySelectorAll("[data-pick-name]").forEach(function (el) { el.hidden = q && el.getAttribute("data-pick-name").indexOf(q) === -1; });
    });
    document.addEventListener("change", function (e) { if (e.target.matches(".pick input")) refresh(); });
    pct.addEventListener("input", refresh);
    refresh();
  })();
  </script>
<?php endif; ?>

<div class="promo-list">
  <?php foreach ($promos as $p): [$label, $cls] = promo_status($p); ?>
    <div class="panel promo-item" id="pr<?= (int) $p['id'] ?>">
      <div class="promo-item-top">
        <div class="promo-thumbs"><?php foreach ($thumbs[(int) $p['id']] ?? [] as $t): ?><img src="<?= e($t) ?>" alt=""><?php endforeach; ?></div>
        <div class="promo-item-info">
          <div class="aprod-name"><p><?= e($p['name']) ?></p><span class="state <?= $cls ?>"><?= e($label) ?></span></div>
          <p class="aprod-cat">
            <?= (int) $p['discount_percent'] ? '<b class="promo-pct">−' . (int) $p['discount_percent'] . ' %</b> · ' : 'Mise en avant · ' ?>
            <?= (int) $p['n'] ?> produit<?= (int) $p['n'] > 1 ? 's' : '' ?>
            <?php if ($p['starts_at'] || $p['ends_at']): ?> · <?= $p['starts_at'] ? 'du ' . e(fr_date($p['starts_at'], 'numeric')) . ' ' : '' ?><?= $p['ends_at'] ? 'au ' . e(fr_date($p['ends_at'], 'numeric')) : '' ?><?php endif; ?>
          </p>
          <?php if ((int) $p['id'] === $homeId): ?><p class="home-flag"><?= icon('store') ?> Affichée sur l'accueil</p><?php endif; ?>
        </div>
      </div>
      <form method="post" class="aprod-vis" style="border-top:1px solid var(--border)">
        <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <div><p>Promotion activée</p><small><?= (int) $p['is_active'] ? 'Appliquée selon ses dates' : 'Aucun effet sur la boutique' ?></small></div>
        <button class="switch <?= (int) $p['is_active'] ? 'on' : '' ?>" role="switch" aria-checked="<?= (int) $p['is_active'] ? 'true' : 'false' ?>" aria-label="Promotion activée"></button>
      </form>
      <div class="aprod-actions">
        <a class="btn btn-primary" href="<?= e(url('admin/promotions', ['edit' => $p['id']])) ?>"><?= icon('pencil') ?>Modifier</a>
        <div class="push">
          <form method="post" data-confirm="Supprimer la promotion « <?= e($p['name']) ?> » ? Les prix reviendront à la normale."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="icon-btn danger" aria-label="Supprimer" title="Supprimer"><?= icon('trash') ?></button></form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$promos): ?><div class="panel empty-panel">Aucune promotion. Cliquez sur « Créer une promotion » pour commencer.</div><?php endif; ?>
<p class="muted" style="margin-top:1rem;font-size:.8125rem">Si plusieurs promotions s'appliquent au même produit, c'est la plus forte remise qui compte. L'accueil affiche la promotion « En cours » modifiée le plus récemment.</p>
<?php admin_footer(); ?>
