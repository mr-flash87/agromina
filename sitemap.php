<?php
// Plan du site pour Google (accessible à l'adresse /sitemap.xml).
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$urls = [];
foreach (['' => '1.0', 'boutique' => '0.9', 'categories' => '0.7', 'blog' => '0.6', 'a-propos' => '0.5', 'engagements' => '0.5', 'contact' => '0.5', 'cgv' => '0.2', 'confidentialite' => '0.2'] as $path => $prio) {
    $urls[] = [absolute_url($path), null, $prio];
}
foreach (get_products() as $p) {
    $urls[] = [absolute_url('produit/' . $p['slug']), $p['created_at'] ?? null, '0.8'];
}
foreach (get_posts() as $post) {
    $urls[] = [absolute_url('blog/' . $post['slug']), $post['published_at'] ?? ($post['created_at'] ?? null), '0.6'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $prio]) {
    echo '  <url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . e(date('Y-m-d', strtotime((string) $mod))) . '</lastmod>' : '') . '<priority>' . $prio . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
