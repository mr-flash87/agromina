<?php
// Il n'y a pas de compte client : seule l'équipe AgroMina se connecte, via /admin.
require __DIR__ . '/includes/bootstrap.php';
$next = isset($_GET['next']) && is_string($_GET['next']) && str_starts_with($_GET['next'], '/admin') ? $_GET['next'] : null;
header('Location: ' . url('admin/connexion', ['next' => $next]), true, 301);
exit;
