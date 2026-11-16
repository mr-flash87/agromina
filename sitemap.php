<?php
// Plan du site pour Google (accessible à l'adresse /sitemap.xml), avec les images des produits.
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$urls = [];
foreach (['' => '1.0', 'boutique' => '0.9', 'categories' => '0.7', 'blog' => '0.6', 'a-propos' => '0.5', 'engagements' => '0.5', 'contact' => '0.5', 'cgv' => '0.2', 'confidentialite' => '0.2'] as $path => $prio) {
    $urls[] = ['loc' => absolute_url($path), 'prio' => $prio, 'freq' => $path === '' || $path === 'boutique' ? 'daily' : 'monthly'];
}
foreach (categories() as $slug => $c) {
    $urls[] = ['loc' => absolute_url('boutique', ['cat' => $slug]), 'prio' => '0.8', 'freq' => 'weekly', 'img' => [[full_url(media_url($c['image'])), $c['name']]]];
}
foreach (get_products() as $p) {
    $urls[] = ['loc' => absolute_url('produit/' . $p['slug']), 'mod' => $p['updated_at'] ?? $p['created_at'] ?? null, 'prio' => '0.8', 'freq' => 'weekly',
        'img' => [[full_url(product_image($p)), $p['name']]]];
}
foreach (get_posts() as $post) {
    $urls[] = ['loc' => absolute_url('blog/' . $post['slug']), 'mod' => $post['updated_at'] ?? $post['published_at'], 'prio' => '0.6', 'freq' => 'monthly',
        'img' => $post['image_url'] ? [[full_url(media_url($post['image_url'])), $post['title']]] : []];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
    . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . e($u['loc']) . '</loc>';
    if (!empty($u['mod'])) echo '<lastmod>' . e(date('Y-m-d', strtotime((string) $u['mod']))) . '</lastmod>';
    echo '<changefreq>' . $u['freq'] . '</changefreq><priority>' . $u['prio'] . '</priority>';
    foreach ($u['img'] ?? [] as [$src, $title]) {
        echo '<image:image><image:loc>' . e($src) . '</image:loc></image:image>';
    }
    echo "</url>\n";
}
echo "</urlset>\n";
