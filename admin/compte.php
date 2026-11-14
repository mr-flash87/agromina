<?php
require __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        db()->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?')
            ->execute([str_input('full_name', 80), str_input('phone', 20), $ADMIN['id']]);
        flash('success', 'Coordonnées enregistrées');
    } elseif ($action === 'password') {
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$ADMIN['id']]);
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $st->fetchColumn())) {
            flash('error', 'Mot de passe actuel incorrect.');
        } elseif (mb_strlen($new) < 8) {
            flash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
        } elseif ($new !== (string) ($_POST['confirm'] ?? '')) {
            flash('error', 'Les deux nouveaux mots de passe ne sont pas identiques.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $ADMIN['id']]);
            flash('success', 'Mot de passe mis à jour.');
        }
    }
    redirect(url('admin/compte'));
}

admin_header('Mon compte', 'compte');
admin_title('Mon compte', $ADMIN['email'] . ' · ' . ($ADMIN['role'] === 'admin' ? 'Super administrateur' : 'Gérant'));
?>
<div class="dash-grid" style="margin-top:0">
  <form class="panel panel-pad form-card" method="post" style="align-content:start">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2 style="font-size:1.125rem;color:var(--fg)">Mes informations</h2>
    <label class="label"><span>Nom complet</span><input class="field" name="full_name" maxlength="80" value="<?= e($ADMIN['full_name']) ?>"></label>
    <label class="label"><span>Téléphone</span><input class="field" name="phone" maxlength="20" value="<?= e($ADMIN['phone']) ?>"></label>
    <label class="label"><span>E-mail de connexion</span><input class="field" value="<?= e($ADMIN['email']) ?>" readonly style="background:var(--muted);color:var(--muted-fg)"></label>
    <div><button class="btn btn-primary">Enregistrer</button></div>
  </form>
  <form class="panel panel-pad form-card" method="post" style="align-content:start">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2 style="font-size:1.125rem;color:var(--fg)">Changer mon mot de passe</h2>
    <label class="label"><span>Mot de passe actuel</span><input class="field" type="password" name="current" autocomplete="current-password" required></label>
    <label class="label"><span>Nouveau mot de passe (8 caractères minimum)</span><input class="field" type="password" name="new" minlength="8" autocomplete="new-password" required></label>
    <label class="label"><span>Confirmer le nouveau mot de passe</span><input class="field" type="password" name="confirm" minlength="8" autocomplete="new-password" required></label>
    <div><button class="btn btn-outline">Mettre à jour</button></div>
  </form>
</div>
<?php admin_footer(); ?>
