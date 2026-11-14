<?php
require __DIR__ . '/_layout.php';

$days = (int) ($_GET['p'] ?? 30);
$days = in_array($days, [7, 30, 90], true) ? $days : 30;
$DAY = 86400;
$end = strtotime('tomorrow');
$start = $end - $days * $DAY;
$prevStart = $start - $days * $DAY;
$pdo = db();

$orders = $pdo->query('SELECT id, ref, full_name, phone, status, total, created_at FROM orders ORDER BY created_at DESC LIMIT 5000')->fetchAll();
foreach ($orders as &$o) {
    $o['ts'] = strtotime($o['created_at'] . ' UTC');
    $o['total'] = (int) $o['total'];
}
unset($o);

$inRange = static fn(int $a, int $b) => array_values(array_filter($orders, fn($o) => $o['ts'] >= $a && $o['ts'] < $b));
$valid = static fn(array $l) => array_values(array_filter($l, fn($o) => $o['status'] !== 'annulee'));
$rev = static fn(array $l) => array_sum(array_column($valid($l), 'total'));
$avg = static fn(array $l) => count($valid($l)) ? (int) round($rev($l) / count($valid($l))) : 0;
$delivered = static fn(array $l) => count(array_filter($l, fn($o) => $o['status'] === 'livree'));

$cur = $inRange($start, $end);
$prev = $inRange($prevStart, $start);

// Graphique : barres par jour (7 j) ou par groupe de jours.
$buckets = $days === 7 ? 7 : ($days === 30 ? 15 : 13);
$size = (int) ($days * $DAY / $buckets);
$series = [];
for ($i = 0; $i < $buckets; $i++) {
    $a = $start + $i * $size;
    $b = $i === $buckets - 1 ? $end : $a + $size;
    $series[] = ['from' => $a, 'to' => $b - 1, 'total' => $rev($inRange($a, $b)), 'count' => count($valid($inRange($a, $b)))];
}
$maxBar = max(1, ...array_column($series, 'total'));
$niceMax = max(15000, (int) ceil($maxBar / 5000) * 5000);

// Répartition des statuts (anneau)
$statusCount = [];
foreach (ORDER_STATUSES as $st => $_) {
    $statusCount[$st] = count(array_filter($cur, fn($o) => $o['status'] === $st));
}
$statusColors = ['nouvelle' => '#f29a19', 'confirmee' => '#126333', 'en_preparation' => '#8cbd50', 'livree' => '#0b4a25', 'annulee' => '#d63a2b'];
$totalCur = max(1, count($cur));
$acc = 0;
$stops = [];
foreach ($statusCount as $st => $n) {
    if (!$n) continue;
    $from = $acc / $totalCur * 100;
    $acc += $n;
    $stops[] = $statusColors[$st] . ' ' . round($from, 2) . '% ' . round($acc / $totalCur * 100, 2) . '%';
}
$ring = $stops ? 'conic-gradient(' . implode(', ', $stops) . ')' : 'conic-gradient(var(--border) 0 100%)';

// Meilleures ventes de la période
$top = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) qty, SUM(oi.quantity * oi.unit_price) amount
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status <> 'annulee' AND o.created_at >= ? AND o.created_at < ?
    GROUP BY oi.product_name ORDER BY qty DESC, amount DESC LIMIT 5");
$top->execute([gmdate('Y-m-d H:i:s', $start), gmdate('Y-m-d H:i:s', $end)]);
$top = $top->fetchAll();
$topMax = max(1, ...array_map(fn($r) => (int) $r['qty'], $top ?: [['qty' => 1]]));

$lowStock = $pdo->query('SELECT id, name, stock, category, image_url FROM products WHERE stock < 5 ORDER BY stock ASC, name LIMIT 6')->fetchAll();
$lowCount = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE stock < 5')->fetchColumn();
$newOrders = count(array_filter($orders, fn($o) => $o['status'] === 'nouvelle'));
$toPrepare = count(array_filter($orders, fn($o) => in_array($o['status'], ['confirmee', 'en_preparation'], true)));
$unread = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
$todayRev = $rev($inRange(strtotime('today'), $end));

$hour = (int) date('G');
$hello = $hour < 5 ? 'Bonsoir' : ($hour < 18 ? 'Bonjour' : 'Bonsoir');
$firstName = trim(explode(' ', (string) ($ADMIN['full_name'] ?? ''))[0] ?? '');
$initials = static function (string $n): string {
    $w = array_slice(array_filter(preg_split('/\s+/', trim($n)) ?: []), 0, 2);
    return $w ? implode('', array_map(fn($x) => mb_strtoupper(mb_substr($x, 0, 1)), $w)) : '?';
};

admin_header('Tableau de bord', 'index');
?>
<section class="dash-hero pattern-band">
  <div class="dash-hero-text">
    <p class="dash-date"><?= e(fr_date(time(), 'long')) ?></p>
    <h1><?= e($hello) ?><?= $firstName !== '' ? ' ' . e($firstName) : '' ?> 👋</h1>
    <p>
      <?php if ($newOrders): ?>
        Vous avez <b><?= $newOrders ?> nouvelle<?= $newOrders > 1 ? 's' : '' ?> commande<?= $newOrders > 1 ? 's' : '' ?></b> à confirmer.
      <?php else: ?>
        Aucune nouvelle commande en attente. Tout est à jour !
      <?php endif; ?>
      Ventes du jour : <b><?= fcfa($todayRev) ?></b>.
    </p>
  </div>
  <div class="dash-actions">
    <a href="<?= e(url('admin/commandes', ['statut' => 'nouvelle'])) ?>" class="dash-action is-main"><?= icon('bag') ?><span>Traiter les commandes</span><?php if ($newOrders): ?><em><?= $newOrders ?></em><?php endif; ?></a>
    <a href="<?= e(url('admin/produits', ['nouveau' => 1])) ?>" class="dash-action"><?= icon('plus') ?><span>Ajouter un produit</span></a>
    <a href="<?= e(url('admin/blog', ['nouveau' => 1])) ?>" class="dash-action"><?= icon('book') ?><span>Écrire un article</span></a>
    <a href="<?= e(url('')) ?>" target="_blank" class="dash-action"><?= icon('store') ?><span>Voir la boutique</span></a>
  </div>
</section>

<div class="dash-todo">
  <a href="<?= e(url('admin/commandes', ['statut' => 'nouvelle'])) ?>" class="todo"><span class="todo-ic t-accent"><?= icon('bell') ?></span><div><b><?= $newOrders ?></b><small>À confirmer</small></div></a>
  <a href="<?= e(url('admin/commandes', ['statut' => 'en_preparation'])) ?>" class="todo"><span class="todo-ic t-leaf"><?= icon('package') ?></span><div><b><?= $toPrepare ?></b><small>À préparer / livrer</small></div></a>
  <a href="<?= e(url('admin/produits', ['vue' => 'stock'])) ?>" class="todo"><span class="todo-ic t-red"><?= icon('alert') ?></span><div><b><?= $lowCount ?></b><small>Stock faible</small></div></a>
  <a href="<?= e(url('admin/messages')) ?>" class="todo"><span class="todo-ic t-primary"><?= icon('inbox') ?></span><div><b><?= $unread ?></b><small>Message<?= $unread > 1 ? 's' : '' ?> non lu<?= $unread > 1 ? 's' : '' ?></small></div></a>
</div>

<div class="dash-bar">
  <h2>Performances</h2>
  <div class="segmented" role="tablist" aria-label="Période">
    <?php foreach ([7, 30, 90] as $p): ?>
      <a href="<?= e(url('admin/', ['p' => $p])) ?>" class="<?= $days === $p ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $days === $p ? 'true' : 'false' ?>"><?= $p ?> j</a>
    <?php endforeach; ?>
  </div>
</div>
<p class="dash-range"><?= e(fr_date($start, 'day')) ?> – <?= e(fr_date($end - 1, 'day')) ?> <?= date('Y') ?> · comparé aux <?= $days ?> jours précédents</p>

<div class="kpis">
  <?php foreach ([
      ["Chiffre d'affaires", fcfa($rev($cur)), $rev($cur), $rev($prev), fcfa($rev($prev)), 'banknote', 'k-primary'],
      ['Commandes reçues', (string) count($cur), count($cur), count($prev), (string) count($prev), 'bag', 'k-accent'],
      ['Panier moyen', fcfa($avg($cur)), $avg($cur), $avg($prev), fcfa($avg($prev)), 'cart', 'k-leaf'],
      ['Commandes livrées', (string) $delivered($cur), $delivered($cur), $delivered($prev), (string) $delivered($prev), 'package-check', 'k-deep'],
  ] as [$label, $value, $n, $pv, $pl, $ic, $tone]): ?>
    <div class="panel kpi <?= $tone ?>">
      <div class="kpi-top"><p><?= e($label) ?></p><span><?= icon($ic) ?></span></div>
      <div class="kpi-val"><b><?= e($value) ?></b><?= delta($n, $pv) ?></div>
      <small>Période préc. : <?= e($pl) ?></small>
    </div>
  <?php endforeach; ?>
</div>

<div class="dash-grid">
  <div class="panel panel-pad">
    <div class="card-head"><div><p class="muted">Chiffre d'affaires</p><div class="big-number"><b><?= fcfa($rev($cur)) ?></b><?= delta($rev($cur), $rev($prev)) ?></div></div></div>
    <div class="chart">
      <?php foreach ([1, 2 / 3, 1 / 3, 0] as $f): ?>
        <div class="chart-line" style="bottom:<?= $f * 100 ?>%"><span><?= e(compact_number((int) round($niceMax * $f))) ?></span><i></i></div>
      <?php endforeach; ?>
      <div class="chart-bars">
        <?php foreach ($series as $b): $label = $days === 7 ? fr_date($b['from'], 'day') : fr_date($b['from'], 'day') . ' – ' . fr_date($b['to'], 'day'); ?>
          <div><b style="height:<?= round($b['total'] / $niceMax * 100, 2) ?>%;<?= $b['total'] ? 'min-height:4px' : '' ?>"></b><em><?= e($label) ?><br><?= fcfa($b['total']) ?> · <?= $b['count'] ?> cmd</em></div>
        <?php endforeach; ?>
      </div>
      <?php if ($rev($cur) === 0): ?><p class="chart-empty">Vos ventes s'afficheront ici dès votre première commande.</p><?php endif; ?>
    </div>
    <div class="chart-axis">
      <?php if ($days === 7): foreach ($series as $b): ?><span><?= e(mb_substr(FR_DAYS[(int) date('w', $b['from'])], 0, 3)) ?></span><?php endforeach; else: ?>
        <span><?= e(fr_date($start, 'day')) ?></span><span><?= e(fr_date((int) (($start + $end) / 2), 'day')) ?></span><span>Aujourd'hui</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel panel-pad">
    <p class="card-title">Statuts des commandes</p>
    <div class="ring-wrap">
      <div class="ring" style="background:<?= e($ring) ?>"><div><b><?= count($cur) ?></b><small>commande<?= count($cur) > 1 ? 's' : '' ?></small></div></div>
    </div>
    <ul class="status-list">
      <?php foreach (ORDER_STATUSES as $st => $label): ?>
        <li><span class="dot st-<?= e($st) ?>"></span><span><?= e($label) ?></span><b><?= $statusCount[$st] ?></b></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="dash-grid dash-grid-even">
  <div class="panel panel-pad">
    <div class="card-head"><p class="card-title">Meilleures ventes</p><a class="see-all" href="<?= e(url('admin/produits')) ?>">Produits</a></div>
    <?php if ($top): ?>
      <ol class="top-list">
        <?php foreach ($top as $i => $r): ?>
          <li>
            <span class="rank"><?= $i + 1 ?></span>
            <div><p><?= e($r['product_name']) ?></p><i style="width:<?= round($r['qty'] / $topMax * 100) ?>%"></i></div>
            <span class="top-num"><b><?= (int) $r['qty'] ?></b><small><?= fcfa((int) $r['amount']) ?></small></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?><p class="empty-panel">Pas encore de ventes sur cette période.</p><?php endif; ?>
  </div>

  <div class="panel panel-pad">
    <div class="card-head"><p class="card-title">Stock à surveiller</p><a class="see-all" href="<?= e(url('admin/produits', ['vue' => 'stock'])) ?>">Tout voir</a></div>
    <?php if ($lowStock): ?>
      <ul class="stock-list">
        <?php foreach ($lowStock as $p): ?>
          <li>
            <img src="<?= e(product_image($p)) ?>" alt="">
            <p><?= e($p['name']) ?></p>
            <span class="stock-pill <?= (int) $p['stock'] === 0 ? 'is-out' : '' ?>"><?= (int) $p['stock'] === 0 ? 'Rupture' : (int) $p['stock'] . ' restant' . ((int) $p['stock'] > 1 ? 's' : '') ?></span>
            <a class="icon-btn" href="<?= e(url('admin/produits', ['edit' => $p['id']])) ?>" aria-label="Modifier le stock"><?= icon('pencil') ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?><p class="empty-panel">Tous les produits ont un stock suffisant. 👍</p><?php endif; ?>
  </div>
</div>

<div class="panel" style="margin-top:1.25rem">
  <div class="list-head"><b>Dernières commandes</b><a href="<?= e(url('admin/commandes')) ?>">Tout voir</a></div>
  <div class="mini-orders">
    <?php foreach (array_slice($orders, 0, 6) as $o): ?>
      <a class="mini-order" href="<?= e(url('admin/commandes', ['q' => $o['ref']])) ?>">
        <span class="initials"><?= e($initials($o['full_name'])) ?></span>
        <div><p class="nm"><?= e($o['full_name']) ?></p><p class="meta"><?= e($o['ref']) ?> · <?= e(fr_date($o['created_at'])) ?> à <?= e(fr_time($o['created_at'])) ?></p></div>
        <div class="r"><b><?= fcfa($o['total']) ?></b><?= status_pill($o['status']) ?></div>
      </a>
    <?php endforeach; ?>
    <?php if (!$orders): ?><p class="empty-panel">Aucune commande pour le moment. Partagez le lien de votre boutique pour recevoir les premières !</p><?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
