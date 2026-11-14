<?php
// Ancienne page « Mon compte » : le compte se gère maintenant dans l'espace admin.
require __DIR__ . '/includes/bootstrap.php';
header('Location: ' . url('admin/compte'), true, 301);
exit;
