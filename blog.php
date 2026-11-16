<?php
require __DIR__ . '/includes/bootstrap.php';
$posts = get_posts();
page_header(['title' => 'Blog AgroMina – Recettes, produits locaux et terroir du Saloum', 'description' => 'Recettes sénégalaises, conseils et actualités sur les produits locaux du Delta du Saloum : céréales, jus naturels, produits de la mer et savoir-faire des transformatrices.', 'active' => 'blog']);
page_hero('Blog et actualités', "Produits locaux, transformation et vie d'AgroMina.");
?>
<div class="container posts" style="margin-top:2rem">
  <?php foreach ($posts as $post): ?>
    <a class="card post" href="<?= e(url('blog/' . $post['slug'])) ?>">
      <img src="<?= e(media_url($post['image_url'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
      <div class="post-body">
        <time datetime="<?= e(substr($post['published_at'], 0, 10)) ?>"><?= e(post_date($post)) ?></time>
        <h2><?= e($post['title']) ?></h2>
        <?php if ($post['excerpt']): ?><p><?= e($post['excerpt']) ?></p><?php endif; ?>
        <span class="see-all" style="margin-top:.75rem">Lire l'article <?= icon('arrow-right') ?></span>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php if (!$posts): ?><p class="container muted" style="margin-top:2rem">Aucun article pour le moment. Revenez bientôt !</p><?php endif; ?>
<?php page_footer(); ?>
