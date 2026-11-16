<?php
require __DIR__ . '/includes/bootstrap.php';

$post = get_post_by_slug(str_input('slug', 100, $_GET));
if (!$post) {
    http_response_code(404);
    page_header(['title' => 'Article introuvable – AgroMina', 'noindex' => true]);
    echo '<div class="container" style="padding-block:6rem;text-align:center">Article introuvable. <a class="see-all" href="' . e(url('blog')) . '">Retour au blog</a></div>';
    page_footer();
    exit;
}
$others = array_values(array_filter(get_posts(true, 4), fn($p) => $p['id'] !== $post['id']));
$others = array_slice($others, 0, 3);

page_header([
    'title' => $post['title'] . ' | Blog AgroMina',
    'description' => mb_strlen((string) $post['excerpt']) >= 100 ? $post['excerpt'] : trim($post['excerpt'] . ' ' . strip_tags($post['content'])),
    'active' => 'blog',
    'image' => media_url($post['image_url']),
    'og_type' => 'article',
    'canonical' => absolute_url('blog/' . $post['slug']),
    'schema' => [
        array_filter([
            '@type' => 'BlogPosting',
            'headline' => mb_substr($post['title'], 0, 110),
            'description' => meta_excerpt((string) ($post['excerpt'] ?: strip_tags($post['content'])), 200),
            'image' => $post['image_url'] ? [full_url(media_url($post['image_url']))] : null,
            'datePublished' => date('c', strtotime($post['published_at'] . ' UTC')),
            'dateModified' => date('c', strtotime(($post['updated_at'] ?? $post['published_at']) . ' UTC')),
            'inLanguage' => 'fr-SN',
            'author' => ['@type' => 'Organization', 'name' => 'AgroMina', 'url' => absolute_url('')],
            'publisher' => ['@type' => 'Organization', 'name' => 'AgroMina', 'logo' => ['@type' => 'ImageObject', 'url' => full_url(img('logo.jpeg'))]],
            'mainEntityOfPage' => absolute_url('blog/' . $post['slug']),
        ]),
        schema_breadcrumb([['Accueil', absolute_url('')], ['Blog', absolute_url('blog')], [$post['title'], absolute_url('blog/' . $post['slug'])]]),
    ],
]);
?>
<article class="container article">
  <nav class="breadcrumb" aria-label="Fil d'Ariane">
    <a href="<?= e(url('')) ?>">Accueil</a><?= icon('chevron-right') ?><a href="<?= e(url('blog')) ?>">Blog</a><?= icon('chevron-right') ?><span><?= e($post['title']) ?></span>
  </nav>
  <header class="article-head">
    <time datetime="<?= e(substr($post['published_at'], 0, 10)) ?>"><?= e(post_date($post)) ?></time>
    <h1><?= e($post['title']) ?></h1>
    <?php if ($post['excerpt']): ?><p class="article-lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
  </header>
  <?php if ($post['image_url']): ?><img class="article-cover" src="<?= e(media_url($post['image_url'])) ?>" alt="<?= e($post['title']) ?>" fetchpriority="high"><?php endif; ?>
  <div class="article-body"><?= render_post_content($post['content']) ?></div>
  <div class="article-cta">
    <p><b>Envie de goûter ?</b> Découvrez nos produits du Delta du Saloum.</p>
    <a class="btn btn-accent" href="<?= e(url('boutique')) ?>">Voir la boutique <?= icon('arrow-right') ?></a>
  </div>
</article>
<?php if ($others): ?>
  <section class="container section">
    <h2 class="section-title" style="margin-bottom:1.5rem">À lire aussi</h2>
    <div class="posts">
      <?php foreach ($others as $o): ?>
        <a class="card post" href="<?= e(url('blog/' . $o['slug'])) ?>">
          <img src="<?= e(media_url($o['image_url'])) ?>" alt="<?= e($o['title']) ?>" loading="lazy">
          <div class="post-body"><time><?= e(post_date($o)) ?></time><h3><?= e($o['title']) ?></h3></div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
<?php page_footer(); ?>
