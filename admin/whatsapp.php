<?php
require __DIR__ . '/_layout.php';

if (!is_super($ADMIN)) {
    flash('error', 'Réservé au super administrateur.');
    redirect(url('admin/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $token = trim((string) ($_POST['token'] ?? ''));
        if ($token !== '') set_setting('wa_token', mb_substr($token, 0, 300)); // vide = garder le jeton actuel
        if (!empty($_POST['forget_token'])) set_setting('wa_token', '');
        $tpl = preg_replace('/[^a-z0-9_]/', '', strtolower(str_input('template', 120))) ?: 'agromina_commande_confirmee';
        set_setting('wa_template', $tpl);
        set_setting('wa_language', preg_replace('/[^A-Za-z_]/', '', str_input('language', 10)) ?: 'fr');
        set_setting('wa_from', preg_replace('/\D/', '', str_input('from', 20)));
        set_setting('wa_enabled', !empty($_POST['enabled']) ? '1' : '0');
        flash('success', 'Réglages WhatsApp enregistrés');
    } elseif ($action === 'test') {
        $phone = preg_replace('/\D/', '', str_input('phone', 20));
        if (strlen($phone) < 8) {
            flash('error', 'Indiquez un numéro au format international, ex. 221771234567.');
        } else {
            $res = whatsapp_order_confirmation([
                'id' => null, 'ref' => 'AM-TEST', 'full_name' => $ADMIN['full_name'] ?: 'Test',
                'phone' => $phone, 'city' => 'Kaolack', 'total' => 4700, 'payment_method' => 'Paiement à la livraison',
            ], true);
            flash($res[0] ? 'success' : 'error', $res[0] ? 'Message de test envoyé au ' . $phone : 'Échec de l\'envoi : ' . $res[1]);
        }
    }
    redirect(url('admin/whatsapp'));
}

$cfg = whatsapp_settings();
$log = db()->query('SELECT l.*, o.ref, o.full_name FROM message_log l LEFT JOIN orders o ON o.id = l.order_id ORDER BY l.id DESC LIMIT 15')->fetchAll();
$stats = db()->query('SELECT SUM(ok = 1) ok, SUM(ok = 0) ko FROM message_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetch();
$sample = whatsapp_order_vars(['full_name' => 'Awa Sow', 'ref' => 'AM-076E91B2', 'total' => 4700, 'payment_method' => 'Paiement à la livraison', 'city' => 'Kaolack']);

admin_header('WhatsApp', 'whatsapp');
admin_title('WhatsApp automatique', 'Confirmation de commande envoyée au client via BBG SMS');
?>
<div class="wa-status <?= $cfg['enabled'] && $cfg['token'] !== '' ? 'on' : '' ?>">
  <span class="wa-dot"></span>
  <div>
    <b><?= $cfg['enabled'] && $cfg['token'] !== '' ? 'Envoi automatique activé' : 'Envoi automatique désactivé' ?></b>
    <small><?php if ($cfg['token'] === ''): ?>Ajoutez votre jeton API BBG SMS pour commencer.<?php elseif (!$cfg['enabled']): ?>Le jeton est enregistré. Activez l'envoi une fois le modèle approuvé par Meta.<?php else: ?>Chaque nouvelle commande déclenche le message « <?= e($cfg['template']) ?> ».<?php endif; ?></small>
  </div>
  <div class="wa-count"><b><?= (int) ($stats['ok'] ?? 0) ?></b><small>envoyés · 30 j</small></div>
  <?php if ((int) ($stats['ko'] ?? 0) > 0): ?><div class="wa-count ko"><b><?= (int) $stats['ko'] ?></b><small>échecs</small></div><?php endif; ?>
</div>

<div class="dash-grid" style="margin-top:1rem">
  <form class="panel panel-pad form-card" method="post" autocomplete="off" style="align-content:start">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <h2 style="font-size:1.125rem;color:var(--fg)">Connexion BBG SMS</h2>
    <label class="label"><span>Jeton API <?= $cfg['token'] !== '' ? '<em class="ok-tag">enregistré</em>' : '' ?></span>
      <input class="field" name="token" type="password" placeholder="<?= $cfg['token'] !== '' ? '•••••••••• (laisser vide pour garder le jeton actuel)' : 'Collez ici le jeton de votre compte BBG SMS' ?>"></label>
    <?php if ($cfg['token'] !== ''): ?><label class="check"><input type="checkbox" name="forget_token" value="1">Supprimer le jeton enregistré</label><?php endif; ?>
    <label class="label"><span>Nom du modèle approuvé</span><input class="field" name="template" value="<?= e($cfg['template']) ?>" required></label>
    <div class="form-row">
      <label class="label"><span>Langue</span><input class="field" name="language" value="<?= e($cfg['language']) ?>" maxlength="10"></label>
      <label class="label"><span>Numéro d'envoi (facultatif)</span><input class="field" name="from" value="<?= e($cfg['from']) ?>" inputmode="numeric" placeholder="Numéro BBG par défaut"></label>
    </div>
    <label class="wa-toggle"><input type="checkbox" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>><span class="pp-switch-ui"></span><span><b>Envoyer automatiquement à chaque commande</b><small>À activer quand Meta a approuvé le modèle.</small></span></label>
    <div><button class="btn btn-primary">Enregistrer</button></div>
  </form>

  <div class="panel panel-pad" style="display:grid;gap:1rem;align-content:start">
    <h2 style="font-size:1.125rem;color:var(--fg);margin:0">Aperçu du message</h2>
    <div class="wa-phone">
      <div class="wa-bubble">
        Bonjour <b><?= e($sample[0]) ?></b>, votre commande <b><?= e($sample[1]) ?></b> a bien été confirmée ✅<br><br>
        Montant : <b><?= e($sample[2]) ?></b><br>Paiement : <b><?= e($sample[3]) ?></b><br>Livraison : <b><?= e($sample[4]) ?></b><br><br>
        Notre livreur vous appellera avant de passer. Merci de votre confiance !
        <small>AgroMina – Produits du Delta du Saloum</small>
      </div>
      <div class="wa-button">Suivre ma commande</div>
    </div>
    <p class="muted" style="font-size:.82rem;margin:0">Variables envoyées : var1 prénom · var2 référence · var3 montant · var4 paiement · var5 ville.</p>
    <form method="post" class="wa-test">
      <?= csrf_field() ?><input type="hidden" name="action" value="test">
      <input class="field" name="phone" inputmode="numeric" placeholder="Numéro test, ex. 221771234567" required>
      <button class="btn btn-outline" <?= $cfg['token'] === '' ? 'disabled' : '' ?>>Envoyer un test</button>
    </form>
  </div>
</div>

<div class="panel panel-pad" style="margin-top:1rem">
  <h2 style="font-size:1.125rem;color:var(--fg);margin:0 0 .75rem">Derniers envois</h2>
  <?php if (!$log): ?><p class="muted" style="margin:0">Aucun message envoyé pour l'instant.</p><?php else: ?>
    <div class="wa-log">
      <?php foreach ($log as $l): ?>
        <div>
          <span class="wa-dot <?= $l['ok'] ? 'ok' : 'ko' ?>"></span>
          <span class="wa-log-main"><b><?= $l['ref'] ? e($l['full_name']) . ' · ' . e($l['ref']) : 'Test' ?></b><small>+<?= e($l['recipient']) ?> · <?= e(fr_date($l['created_at'])) ?> <?= e(substr($l['created_at'], 11, 5)) ?></small></span>
          <span class="wa-log-info <?= $l['ok'] ? '' : 'ko' ?>"><?= e($l['ok'] ? 'Envoyé' : $l['info']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
