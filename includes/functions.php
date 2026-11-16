<?php
declare(strict_types=1);

/* ---------------------------------------------------------------- Constantes boutique */


const PAYMENT_METHODS = ['Paiement à la livraison', 'Wave', 'Orange Money'];

const ORDER_STATUSES = [
    'nouvelle' => 'Nouvelle',
    'confirmee' => 'Confirmée',
    'en_preparation' => 'En préparation',
    'livree' => 'Livrée',
    'annulee' => 'Annulée',
];


/* ---------------------------------------------------------------- Aides générales */

function cfg(string $key, mixed $default = null): mixed
{
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Adresse interne du site, en tenant compte d'un éventuel sous-dossier (base_url). */
function url(string $path = '', array $query = []): string
{
    $base = rtrim((string) cfg('base_url', ''), '/');
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '' && $v !== false);
    return $base . $path . ($query ? '?' . http_build_query($query) : '');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . ltrim($path, '/')) . $v;
}

function img(string $file): string
{
    return url('assets/img/' . $file);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function fcfa(int|float $n): string
{
    return number_format((float) $n, 0, ',', "\u{202F}") . ' FCFA';
}

function compact_number(int|float $n): string
{
    if ($n >= 1_000_000) {
        return number_format($n / 1_000_000, 1, ',', '') . 'M';
    }
    if ($n >= 1000) {
        return (string) round($n / 1000) . 'K';
    }
    return (string) $n;
}

const FR_MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const FR_MONTHS_SHORT = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const FR_DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

function fr_date(string|int $date, string $style = 'short'): string
{
    $ts = is_int($date) ? $date : strtotime($date . ' UTC');
    $d = (int) date('j', $ts);
    $m = (int) date('n', $ts) - 1;
    $y = date('Y', $ts);
    return match ($style) {
        'long' => ucfirst(FR_DAYS[(int) date('w', $ts)]) . ' ' . $d . ' ' . FR_MONTHS[$m] . ' ' . $y,
        'day' => $d . ' ' . FR_MONTHS_SHORT[$m],
        'numeric' => date('d/m/Y', $ts),
        default => $d . ' ' . FR_MONTHS_SHORT[$m] . ' ' . $y,
    };
}

function fr_time(string $date): string
{
    return date('H:i', strtotime($date . ' UTC'));
}

/** Minuscule sans accents, pour la recherche. */
function normalize_text(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
        'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
    ]);
}

function slugify(string $s): string
{
    $s = normalize_text($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return substr(trim($s, '-'), 0, 80);
}

function str_input(string $key, int $max = 500, ?array $source = null): string
{
    $source ??= $_POST;
    $v = $source[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $max);
}

/* ---------------------------------------------------------------- Flash & CSRF */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page et réessayez.');
    }
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------------------------------------------------------- Comptes */

function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($user !== false && !$refresh) {
        return $user;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $user = null;
    }
    $st = db()->prepare('SELECT id, email, full_name, phone, role FROM users WHERE id = ?');
    $st->execute([$id]);
    $user = $st->fetch() ?: null;
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function is_staff(?array $u = null): bool
{
    $u ??= current_user();
    return $u !== null && in_array($u['role'], ['admin', 'moderator'], true);
}

function is_super(?array $u = null): bool
{
    $u ??= current_user();
    return $u !== null && $u['role'] === 'admin';
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    current_user(true);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_staff(): array
{
    $u = current_user();
    if (!$u) {
        $here = $_SERVER['REQUEST_URI'] ?? '';
        $base = rtrim((string) cfg('base_url', ''), '/');
        $next = $base !== '' && str_starts_with($here, $base) ? substr($here, strlen($base)) : $here;
        redirect(url('admin/connexion', ['next' => $next]));
    }
    if (!is_staff($u)) {
        flash('error', "Accès refusé : ce compte n'est pas administrateur.");
        redirect(url('admin/connexion'));
    }
    return $u;
}

/**
 * Vérifie un e-mail et un mot de passe (avec limite de tentatives).
 * Renvoie la fiche du compte, ou un message d'erreur.
 */
function check_credentials(string $email, string $password): array|string
{
    $now = time();
    $_SESSION['login_fail'] = array_values(array_filter($_SESSION['login_fail'] ?? [], fn($t) => $t > $now - 900));
    if (count($_SESSION['login_fail']) >= 8) {
        return 'Trop de tentatives. Réessayez dans quelques minutes.';
    }
    $st = db()->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        $_SESSION['login_fail'][] = $now;
        return 'Connexion impossible. Vérifiez votre e-mail et votre mot de passe.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    unset($_SESSION['login_fail']);
    return $u;
}

function safe_next(?string $next, string $fallback): string
{
    if (is_string($next) && preg_match('#^/(?!/)[A-Za-z0-9/_\-?=&.]*$#', $next)) {
        return url($next);
    }
    return $fallback;
}

/* ---------------------------------------------------------------- Catalogue */

/**
 * Catégories créées dans l'admin, dans l'ordre choisi.
 * @return array<string, array{id:int, slug:string, name:string, short:string, description:string, image:string}>
 */
function categories(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $cache = [];
    foreach (db()->query('SELECT * FROM categories ORDER BY position, name') as $r) {
        $cache[$r['slug']] = [
            'id' => (int) $r['id'],
            'slug' => $r['slug'],
            'name' => $r['name'],
            'short' => $r['short_name'] !== '' ? $r['short_name'] : $r['name'],
            'description' => $r['description'],
            'image' => $r['image_url'] ?: 'assets/img/cat-autres.jpg',
        ];
    }
    return $cache;
}

function category_of(string $slug): array
{
    return categories()[$slug] ?? ['id' => 0, 'slug' => $slug, 'name' => 'Autres produits', 'short' => 'Autres', 'description' => '', 'image' => 'assets/img/cat-autres.jpg'];
}

function product_image(array $p): string
{
    if (!empty($p['image_url'])) {
        return preg_match('#^https?://#', $p['image_url']) ? $p['image_url'] : url($p['image_url']);
    }
    return media_url(category_of($p['category'])['image']);
}

function normalize_product(array $r): array
{
    foreach (['id', 'price', 'stock'] as $k) {
        $r[$k] = (int) $r[$k];
    }
    $r['old_price'] = $r['old_price'] === null ? null : (int) $r['old_price'];
    foreach (['is_new', 'best_seller', 'is_active', 'is_listed'] as $k) {
        $r[$k] = (bool) ($r[$k] ?? true);
    }
    return $r;
}

/** @return array<int, array> Produits avec les promotions en cours déjà appliquées (sauf pour l'admin). */
function get_products(bool $activeOnly = true, bool $listedOnly = true): array
{
    $where = $activeOnly ? ' WHERE is_active = 1' . ($listedOnly ? ' AND is_listed = 1' : '') : '';
    $sql = 'SELECT * FROM products' . $where
        . ($activeOnly ? ' ORDER BY created_at ASC, name ASC' : ' ORDER BY created_at DESC, name ASC');
    $rows = array_map('normalize_product', db()->query($sql)->fetchAll());
    return $activeOnly ? array_map('apply_promo', $rows) : $rows;
}

function get_product_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1');
    $st->execute([$slug]);
    $r = $st->fetch();
    return $r ? apply_promo(normalize_product($r)) : null;
}

/* ---------------------------------------------------------------- Promotions */

/** Promotions actives en ce moment (activées et dans leurs dates). */
function active_promotions(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = db()->query('SELECT * FROM promotions WHERE is_active = 1
        AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())
        ORDER BY updated_at DESC, id DESC')->fetchAll();
    return $cache;
}

/** @return array<int, int> id produit → meilleure remise (%) en cours */
function promo_discounts(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $ids = array_map(fn($p) => (int) $p['id'], array_filter(active_promotions(), fn($p) => (int) $p['discount_percent'] > 0));
    if ($ids) {
        $in = implode(',', $ids);
        foreach (db()->query("SELECT pp.product_id, MAX(p.discount_percent) d FROM promotion_products pp JOIN promotions p ON p.id = pp.promotion_id WHERE pp.promotion_id IN ($in) GROUP BY pp.product_id") as $r) {
            $cache[(int) $r['product_id']] = (int) $r['d'];
        }
    }
    return $cache;
}

function promo_price(int $price, int $pct): int
{
    return max(0, (int) (round($price * (100 - $pct) / 100 / 5) * 5));
}

/** Applique la remise en cours : le prix normal devient le prix barré. */
function apply_promo(array $p): array
{
    $pct = promo_discounts()[$p['id']] ?? 0;
    if ($pct > 0) {
        $base = $p['price'];
        $p['price'] = promo_price($base, $pct);
        $p['old_price'] = max($p['old_price'] ?? 0, $base);
    }
    return $p;
}

/** Promotion mise en avant sur l'accueil, avec ses produits (prix remisés). */
function home_promotion(): ?array
{
    foreach (active_promotions() as $promo) {
        if (!(int) $promo['show_on_home']) {
            continue;
        }
        $st = db()->prepare('SELECT p.* FROM promotion_products pp JOIN products p ON p.id = pp.product_id
            WHERE pp.promotion_id = ? AND p.is_active = 1 AND p.is_listed = 1 ORDER BY pp.position, p.name LIMIT 4');
        $st->execute([$promo['id']]);
        $promo['products'] = array_map(fn($r) => apply_promo(normalize_product($r)), $st->fetchAll());
        if ($promo['products']) {
            return $promo;
        }
    }
    return null;
}

/** Plus forte remise visible en boutique (pour le bandeau « PROMOS »). */
function best_discount_now(): int
{
    $best = 0;
    foreach (get_products() as $p) {
        $best = max($best, discount_pct($p));
    }
    return $best;
}

function discount_pct(array $p): int
{
    if (!$p['old_price'] || $p['old_price'] <= $p['price']) {
        return 0;
    }
    return (int) round((1 - $p['price'] / $p['old_price']) * 100);
}

/** Données minimales d'un produit pour le panier côté navigateur. */
function product_for_js(array $p): array
{
    return [
        'slug' => $p['slug'],
        'name' => $p['name'],
        'price' => $p['price'],
        'stock' => $p['stock'],
        'image' => product_image($p),
        'url' => url('produit/' . $p['slug']),
    ];
}

function load_order_items(array $orderIds): array
{
    if (!$orderIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $st = db()->prepare("SELECT * FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $st->execute(array_values($orderIds));
    $by = [];
    foreach ($st->fetchAll() as $it) {
        $by[(int) $it['order_id']][] = $it;
    }
    return $by;
}

/* ---------------------------------------------------------------- Photos envoyées depuis l'admin */

const MAX_UPLOAD = 5 * 1024 * 1024;

/** Enregistre une photo envoyée dans uploads/<dossier> et renvoie son chemin relatif (ou null si aucun fichier). */
function store_image_upload(array $file, string $subdir): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("L'envoi de la photo a échoué (fichier trop lourd ?).");
    }
    if ($file['size'] > MAX_UPLOAD) {
        throw new RuntimeException('La photo dépasse 5 Mo.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Format non accepté : utilisez une photo JPG, PNG ou WebP.');
    }
    $rel = 'uploads/' . $subdir;
    $dir = APP_ROOT . '/' . $rel;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException("Le dossier d'images n'est pas accessible en écriture.");
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        throw new RuntimeException("Impossible d'enregistrer la photo.");
    }
    return "$rel/$name";
}

/** Supprime une photo envoyée si plus aucun produit ni article ne l'utilise. */
function delete_uploaded_image(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
        return;
    }
    $used = 0;
    foreach (['products', 'posts', 'categories'] as $table) {
        $st = db()->prepare("SELECT COUNT(*) FROM $table WHERE image_url = ?");
        $st->execute([$path]);
        $used += (int) $st->fetchColumn();
    }
    if ($used === 0) {
        @unlink(APP_ROOT . '/' . $path);
    }
}

function media_url(?string $path, string $fallback = 'terroir.jpg'): string
{
    if (!$path) {
        return img($fallback);
    }
    return preg_match('#^https?://#', $path) ? $path : url($path);
}

/* ---------------------------------------------------------------- Blog */

/** @return array<int, array> */
function get_posts(bool $publishedOnly = true, int $limit = 0): array
{
    $sql = 'SELECT * FROM posts' . ($publishedOnly ? ' WHERE is_published = 1 AND published_at <= UTC_TIMESTAMP()' : '')
        . ' ORDER BY published_at DESC, id DESC' . ($limit > 0 ? ' LIMIT ' . $limit : '');
    return db()->query($sql)->fetchAll();
}

function get_post_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM posts WHERE slug = ? AND is_published = 1 AND published_at <= UTC_TIMESTAMP()');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function post_date(array $post): string
{
    $ts = strtotime($post['published_at'] . ' UTC');
    return (int) date('j', $ts) . ' ' . FR_MONTHS[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

/**
 * Texte d'article → HTML sûr. Une ligne vide sépare les paragraphes,
 * « ## Titre » crée un intertitre, « - élément » une liste, **texte** met en gras.
 */
function render_post_content(string $text): string
{
    $html = '';
    foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
        $lines = preg_split('/\R/', trim($block)) ?: [];
        if (count($lines) === 1 && str_starts_with($lines[0], '## ')) {
            $html .= '<h2>' . inline_md(substr($lines[0], 3)) . '</h2>';
        } elseif ($lines && array_reduce($lines, fn($ok, $l) => $ok && preg_match('/^[-•]\s+/', $l), true)) {
            $html .= '<ul>' . implode('', array_map(fn($l) => '<li>' . inline_md(preg_replace('/^[-•]\s+/', '', $l)) . '</li>', $lines)) . '</ul>';
        } else {
            $html .= '<p>' . implode('<br>', array_map('inline_md', $lines)) . '</p>';
        }
    }
    return $html;
}

function inline_md(string $s): string
{
    return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($s)) ?? e($s);
}

/** Crée les nouvelles tables sur un site déjà installé (mise à jour automatique, une seule fois). */
function ensure_schema(): void
{
    $steps = [
        'v2' => ['posts.sql', 'posts-seed.sql'],
        'v3' => ['promotions.sql', 'promotions-seed.sql'],
        'v4' => ['categories.sql', 'categories-seed.sql'],
        'v5' => 'migrate_v5',
    ];
    foreach ($steps as $version => $files) {
        $marker = __DIR__ . '/.schema-' . $version;
        if (is_file($marker)) {
            continue;
        }
        if (is_string($files)) {
            $files();
        } else {
            foreach ($files as $file) {
                run_sql_file(APP_ROOT . '/sql/' . $file);
            }
        }
        @file_put_contents($marker, date('c'));
    }
}

function run_sql_file(string $path): void
{
    $sql = (string) file_get_contents($path);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $stmt) {
        if (trim($stmt) !== '') {
            db()->exec($stmt);
        }
    }
}

/* ---------------------------------------------------------------- WhatsApp de la boutique */

/** Numéro WhatsApp d'AgroMina (réglage « whatsapp », sinon le téléphone affiché), chiffres seuls. */
function shop_whatsapp(): string
{
    $digits = preg_replace('/\D/', '', (string) cfg('whatsapp', cfg('contact_phone', ''))) ?? '';
    // Numéro d'exemple non modifié (+221 00 000 00 00) : pas de bouton.
    if (strlen($digits) < 8 || preg_match('/^(221)?0+$/', $digits)) {
        return '';
    }
    return $digits;
}

function shop_whatsapp_link(string $text = ''): string
{
    $n = shop_whatsapp();
    return $n === '' ? '' : 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** v5 : « visible en boutique » séparé de « page en ligne » + compteur de vues par produit. */
function migrate_v5(): void
{
    $pdo = db();
    $has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'is_listed'")->fetchColumn();
    if (!(int) $has) {
        $pdo->exec('ALTER TABLE products ADD COLUMN is_listed TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active');
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS product_views (
        product_id INT UNSIGNED NOT NULL,
        day DATE NOT NULL,
        views INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (product_id, day),
        CONSTRAINT product_views_product_fk FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

/** Compte une visite de fiche produit (une par visiteur et par produit toutes les 30 min ; ni l'équipe ni les robots). */
function record_product_view(int $productId): void
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|curl|wget|python|headless/i', $ua) || is_staff()) {
        return;
    }
    $now = time();
    $seen = $_SESSION['viewed'] ?? [];
    if (isset($seen[$productId]) && $seen[$productId] > $now - 1800) {
        return;
    }
    $seen[$productId] = $now;
    $_SESSION['viewed'] = array_slice($seen, -50, null, true);
    try {
        db()->prepare('INSERT INTO product_views (product_id, day, views) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE views = views + 1')->execute([$productId]);
    } catch (Throwable $e) {
        error_log('AgroMina vues: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// Messages de suivi envoyés au client (WhatsApp, SMS, e-mail)
// ---------------------------------------------------------------------------

/** Adresse complète du site (https://domaine/…), pour les liens envoyés aux clients. */
function absolute_url(string $path = '', array $query = []): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https' : 'http') . '://' . $host . url($path, $query);
}

/** Texte du message envoyé au client selon le statut de sa commande. */
function order_message(array $o, ?string $status = null): string
{
    $status ??= $o['status'];
    $first = trim(explode(' ', trim((string) $o['full_name']))[0] ?? '');
    $ref = $o['ref'];
    $total = fcfa((int) $o['total']);
    $track = absolute_url('suivi', ['ref' => $ref]);
    $hello = 'Bonjour' . ($first !== '' ? ' ' . $first : '') . ',';
    $msg = match ($status) {
        'confirmee' => "$hello votre commande $ref ($total) est confirmée. Nous la préparons avec soin. Paiement : " . $o['payment_method'] . '.',
        'en_preparation' => "$hello votre commande $ref est en préparation et sera bientôt en route. Notre livreur vous appellera avant de passer.",
        'livree' => "$hello votre commande $ref a bien été livrée. Merci pour votre confiance et à bientôt sur AgroMina !",
        'annulee' => "$hello votre commande $ref a été annulée. Pour toute question, répondez simplement à ce message.",
        default => "$hello merci pour votre commande $ref ($total) chez AgroMina. Nous l'avons bien reçue et vous contactons très vite pour la confirmer.",
    };
    return $msg . ($status !== 'annulee' && $status !== 'livree' ? "\nSuivre ma commande : $track" : '') . "\n— AgroMina, produits du Delta du Saloum";
}

/** Numéro au format international sans « + » (pour wa.me). */
function phone_digits(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone);
    return strlen($d) === 9 ? '221' . $d : $d; // numéro sénégalais saisi sans indicatif
}

function order_whatsapp_link(array $o, ?string $status = null): string
{
    return 'https://wa.me/' . phone_digits($o['phone']) . '?text=' . rawurlencode(order_message($o, $status));
}

function order_sms_link(array $o, ?string $status = null): string
{
    // « ?&body= » est compris à la fois par iPhone et Android.
    return 'sms:+' . phone_digits($o['phone']) . '?&body=' . rawurlencode(order_message($o, $status));
}

/** E-mail automatique au client (s'il a laissé son adresse). Silencieux en cas d'échec. */
function email_order_update(array $o, ?string $status = null): bool
{
    $to = trim((string) ($o['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) return false;
    $status ??= $o['status'];
    $subjects = [
        'nouvelle' => 'Commande %s bien reçue', 'confirmee' => 'Commande %s confirmée',
        'en_preparation' => 'Commande %s en préparation', 'livree' => 'Commande %s livrée', 'annulee' => 'Commande %s annulée',
    ];
    $subject = 'AgroMina – ' . sprintf($subjects[$status] ?? 'Commande %s', $o['ref']);
    $from = (string) cfg('contact_email', '');
    $host = preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $sender = $host !== '' && $host !== 'localhost' ? 'no-reply@' . $host : $from;
    $headers = "Content-Type: text/plain; charset=UTF-8\r\nFrom: =?UTF-8?B?" . base64_encode('AgroMina') . "?= <$sender>";
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) $headers .= "\r\nReply-To: $from";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', order_message($o, $status), $headers);
}
