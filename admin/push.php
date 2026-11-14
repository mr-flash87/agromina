<?php
/** Abonnement des appareils admin aux notifications de commande. */
require __DIR__ . '/_layout.php';
require APP_ROOT . '/includes/webpush.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false], 405);
}
check_csrf();
$raw = json_decode((string) file_get_contents('php://input'), true);
$action = is_array($raw) ? (string) ($raw['action'] ?? '') : '';

try {
    if ($action === 'key') {
        json_out(['ok' => true, 'key' => vapid_keys()['public']]);
    }
    if ($action === 'subscribe') {
        $sub = $raw['subscription'] ?? [];
        $endpoint = (string) ($sub['endpoint'] ?? '');
        $p256dh = (string) ($sub['keys']['p256dh'] ?? '');
        $auth = (string) ($sub['keys']['auth'] ?? '');
        if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 700 || strlen(b64u_decode($p256dh)) !== 65 || strlen(b64u_decode($auth)) !== 16) {
            json_out(['ok' => false, 'error' => 'Abonnement invalide.'], 422);
        }
        db()->prepare('INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent)')
            ->execute([$ADMIN['id'], $endpoint, hash('sha256', $endpoint), $p256dh, $auth, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
        json_out(['ok' => true]);
    }
    if ($action === 'unsubscribe') {
        db()->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ?')->execute([hash('sha256', (string) ($raw['endpoint'] ?? ''))]);
        json_out(['ok' => true]);
    }
    if ($action === 'test') {
        $st = db()->prepare('SELECT * FROM push_subscriptions WHERE endpoint_hash = ?');
        $st->execute([hash('sha256', (string) ($raw['endpoint'] ?? ''))]);
        $sub = $st->fetch();
        if (!$sub) json_out(['ok' => false, 'error' => "Cet appareil n'est pas abonné."], 404);
        $code = webpush_send($sub, ['title' => 'AgroMina Admin', 'body' => 'Les notifications fonctionnent ✅ Vous serez prévenu à chaque commande.', 'url' => url('admin/commandes'), 'tag' => 'test']);
        json_out(['ok' => $code >= 200 && $code < 300, 'code' => $code, 'error' => $code >= 200 && $code < 300 ? null : "Le service de notification a refusé l'envoi (code $code)."]);
    }
} catch (Throwable $e) {
    error_log('AgroMina push: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Notifications indisponibles sur cet hébergement : ' . $e->getMessage()], 500);
}
json_out(['ok' => false, 'error' => 'Action inconnue.'], 400);
