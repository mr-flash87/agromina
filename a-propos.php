<?php
require __DIR__ . '/includes/bootstrap.php';
page_header([
    'title' => 'À propos – AgroMina',
    'description' => "AgroMina transforme et commercialise les richesses agricoles, halieutiques et artisanales du Delta du Saloum, au Sénégal.",
    'active' => 'a-propos',
]);
page_hero('À propos', 'AgroMina, la transformation des produits locaux du Delta du Saloum.');
?>
<section class="container about-intro">
  <div class="about-text">
    <p class="eyebrow">Notre histoire</p>
    <h2>Une entreprise née au cœur du Delta du Saloum</h2>
    <p>Entre mangroves, bolongs et terres agricoles, le Delta du Saloum regorge de richesses : céréales, fruits, produits de la mer et savoir-faire artisanal transmis de génération en génération. Pourtant, une grande partie de ces produits reste peu connue en dehors de la région.</p>
    <p><b>AgroMina est née de cette envie : faire voyager le goût du Saloum partout au Sénégal.</b> Nous transformons et commercialisons des produits locaux de qualité, en travaillant directement avec les producteurs, les pêcheurs et les femmes transformatrices de la région.</p>
    <p>Céréales prêtes à cuisiner, jus naturels, confitures, poisson fumé, miel des mangroves… chaque produit raconte un terroir et le travail de celles et ceux qui le font vivre.</p>
  </div>
  <img src="<?= e(img('terroir.jpg')) ?>" alt="Mangroves du Delta du Saloum au coucher du soleil" loading="lazy">
</section>

<section class="container section">
  <div class="mission card">
    <span class="mission-icon"><?= icon('sprout') ?></span>
    <div>
      <p class="eyebrow">Notre mission</p>
      <p class="mission-text">Rendre les produits authentiques du Saloum accessibles aux familles comme aux professionnels, tout en créant de la valeur pour celles et ceux qui les produisent.</p>
    </div>
  </div>
</section>

<section class="container section">
  <div class="section-head"><h2 class="section-title">Ce qui nous guide</h2></div>
  <div class="values">
    <?php foreach ([
        ['leaf', 'Authenticité', 'Des recettes et des gestes traditionnels, des matières premières locales, sans artifice.'],
        ['award', 'Qualité', 'Des produits sélectionnés, transformés avec soin et dans le respect des règles d\'hygiène.'],
        ['hand-heart', 'Équité', 'Une juste rémunération pour les producteurs et les transformatrices avec qui nous travaillons.'],
        ['users', 'Proximité', 'Une relation directe avec nos clients : un appel avant chaque livraison, une réponse rapide sur WhatsApp.'],
    ] as [$ic, $t, $d]): ?>
      <div class="card value"><span><?= icon($ic) ?></span><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="container section">
  <div class="section-head"><h2 class="section-title">Nos familles de produits</h2><a class="see-all" href="<?= e(url('categories')) ?>">Tout voir <?= icon('arrow-right') ?></a></div>
  <div class="about-cats">
    <?php foreach (CATEGORIES as $slug => $c): ?>
      <a class="about-cat" href="<?= e(url('boutique', ['cat' => $slug])) ?>"><img src="<?= e(img($c['image'])) ?>" alt="" loading="lazy"><span><?= e($c['name']) ?></span></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="container section">
  <div class="cta-band pattern-band">
    <div><h2>Envie de goûter le Saloum ?</h2><p>Commandez en ligne et payez à la livraison, partout au Sénégal.</p></div>
    <div class="cta-actions">
      <a class="btn btn-accent" href="<?= e(url('boutique')) ?>">Découvrir la boutique</a>
      <a class="btn btn-outline-light" href="<?= e(url('engagements')) ?>">Nos engagements</a>
    </div>
  </div>
</section>
<?php page_footer(); ?>
