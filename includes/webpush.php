<?php
/**
 * Notifications push (Web Push, RFC 8030 / 8291 / 8292) sans bibliothèque externe.
 * Utilise uniquement les extensions PHP openssl et curl, présentes chez Hostinger.
 *
 * - Les clés VAPID sont créées automatiquement au premier usage (includes/vapid.json).
 * - Les abonnements des téléphones/ordinateurs admin sont dans la table push_subscriptions.
 */
declare(strict_types=1);

function b64u_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64u_decode(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/** Clé publique P-256 brute (65 octets 0x04||X||Y) → objet clé OpenSSL. */
function p256_public_key(string $raw)
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    return openssl_pkey_get_public($pem);
}

function p256_raw_public(array $details): string
{
    return "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
}

function webpush_new_key(): array
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$key) {
        throw new RuntimeException('OpenSSL ne peut pas créer de clé EC.');
    }
    $details = openssl_pkey_get_details($key);
    openssl_pkey_export($key, $pem);
    return ['key' => $key, 'pem' => $pem, 'public' => p256_raw_public($details)];
}

/** Clés VAPID du site (créées une seule fois). */
function vapid_keys(): array
{
    static $keys = null;
    if ($keys) {
        return $keys;
    }
    $file = __DIR__ . '/vapid.json';
    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (!empty($data['private_pem']) && !empty($data['public'])) {
            return $keys = $data;
        }
    }
    $k = webpush_new_key();
    $keys = ['private_pem' => $k['pem'], 'public' => b64u_encode($k['public'])];
    if (@file_put_contents($file, json_encode($keys)) === false) {
        throw new RuntimeException("Impossible d'enregistrer les clés de notification (droits du dossier includes).");
    }
    @chmod($file, 0600);
    return $keys;
}

/** Signature ECDSA DER → r||s (64 octets) pour un JWT ES256. */
function der_to_jose(string $der): string
{
    $pos = 2 + (ord($der[1]) & 0x80 ? (ord($der[1]) & 0x7f) : 0);
    $parts = [];
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$pos + 1]);
        $int = substr($der, $pos + 2, $len);
        $parts[] = str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $len;
    }
    return $parts[0] . $parts[1];
}

function vapid_header(string $endpoint): string
{
    $keys = vapid_keys();
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $contact = (string) cfg('order_notify_email', '') ?: shop_email();
    $header = b64u_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = b64u_encode(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . $contact]));
    $priv = openssl_pkey_get_private($keys['private_pem']);
    openssl_sign("$header.$claims", $sig, $priv, OPENSSL_ALGO_SHA256);
    $jwt = "$header.$claims." . b64u_encode(der_to_jose($sig));
    return 'vapid t=' . $jwt . ', k=' . $keys['public'];
}

/** Chiffre le message pour un abonnement (aes128gcm, RFC 8291). */
function webpush_encrypt(string $payload, string $uaPublicB64, string $authB64): string
{
    $uaPublic = b64u_decode($uaPublicB64);
    $authSecret = b64u_decode($authB64);
    if (strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) {
        throw new InvalidArgumentException('Abonnement invalide.');
    }
    $as = webpush_new_key();
    $shared = openssl_pkey_derive(p256_public_key($uaPublic), $as['key'], 32);
    if ($shared === false) {
        throw new RuntimeException('Échange de clés impossible.');
    }
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $as['public'], $authSecret);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(65) . $as['public'] . $cipher . $tag;
}

/**
 * Envoie une notification à un abonnement.
 * @return int code HTTP (201 = reçu ; 404/410 = abonnement expiré)
 */
function webpush_send(array $sub, array $message): int
{
    $body = webpush_encrypt(json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sub['p256dh'], $sub['auth']);
    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . vapid_header($sub['endpoint']),
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: 86400',
            'Urgency: high',
        ],
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

/** Prévient tous les appareils admin abonnés. Les abonnements expirés sont supprimés. */
function notify_staff(array $message): int
{
    if (!function_exists('curl_init') || !function_exists('openssl_pkey_derive')) {
        return 0;
    }
    $subs = db()->query("SELECT s.* FROM push_subscriptions s JOIN users u ON u.id = s.user_id WHERE u.role IN ('admin','moderator')")->fetchAll();
    $sent = 0;
    foreach ($subs as $sub) {
        try {
            $code = webpush_send($sub, $message);
            if ($code >= 200 && $code < 300) {
                $sent++;
            } elseif (in_array($code, [404, 410], true)) {
                db()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$sub['id']]);
            }
        } catch (Throwable $e) {
            error_log('AgroMina push: ' . $e->getMessage());
        }
    }
    return $sent;
}
