<?php
/**
 * Connexion à l'espace admin : page indépendante de la boutique
 * (pas de menu, de recherche ni de catégories).
 */
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$next = isset($_GET['next']) && is_string($_GET['next']) ? $_GET['next'] : (string) ($_POST['next'] ?? '');
$adminPrefix = '/admin';
$target = static fn() => str_starts_with($next, $adminPrefix) ? safe_next($next, url('admin/')) : url('admin/');

$user = current_user();
if ($user && is_staff($user)) {
    redirect($target());
}

$email = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = mb_strtolower(str_input('email', 190));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $u = check_credentials($email, $password);
    if (is_string($u)) {
        $err = $u;
    } elseif (!in_array($u['role'], ['admin', 'moderator'], true)) {
        $err = "Ce compte n'a pas accès à l'espace admin.";
    } else {
        login_user((int) $u['id']);
        redirect($target());
    }
}
$flashes = take_flashes();
if ($user && !is_staff($user) && !$err) {
    $err = "Vous êtes connecté avec un compte client. Connectez-vous avec un compte administrateur.";
}
?>
<!doctype html>
<html lang="fr">
<head>
<?php admin_head('Connexion'); ?>
</head>
<body class="admin-body admin-login">
  <main class="login-wrap">
    <div class="login-brand">
      <img src="<?= e(img('logo.jpeg')) ?>" alt="AgroMina">
      <span>Espace administrateur</span>
    </div>
    <form class="panel login-card" method="post" action="<?= e(url('admin/connexion')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <h1>Connexion</h1>
      <p class="muted">Gérez vos commandes, produits, clients et articles.</p>
      <?php foreach ($flashes as $f): ?>
        <p class="flash flash-<?= e($f['type']) ?>" style="margin:0;width:auto"><?= e($f['message']) ?></p>
      <?php endforeach; ?>
      <?php if ($err): ?><p class="alert-danger" style="margin:0"><?= e($err) ?></p><?php endif; ?>
      <label class="label"><span>Adresse e-mail</span><input class="field" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus></label>
      <label class="label"><span>Mot de passe</span><input class="field" type="password" name="password" autocomplete="current-password" required></label>
      <button class="btn btn-primary btn-block btn-lg">Se connecter</button>
      <p class="login-help">Mot de passe oublié ? Un super administrateur peut le réinitialiser depuis la page « Équipe ».</p>
    </form>
    <a class="login-back" href="<?= e(url('')) ?>"><?= icon('store') ?> Retour à la boutique</a>
  </main>
<script>
  try { if (localStorage.getItem("am-admin-theme") === "dark") document.documentElement.classList.add("dark"); } catch (e) {}
  if ("serviceWorker" in navigator && window.self === window.top) {
    navigator.serviceWorker.register(<?= json_encode(url('sw.js')) ?>, { scope: <?= json_encode(url('')) ?> }).catch(function () {});
  }
</script>
</body>
</html>
