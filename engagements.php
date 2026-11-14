<?php
require __DIR__ . '/includes/bootstrap.php';
page_header([
    'title' => 'Nos engagements – AgroMina',
    'description' => 'Qualité, traçabilité, rémunération équitable et respect de l\'environnement : les engagements d\'AgroMina envers ses clients et les producteurs du Delta du Saloum.',
    'active' => 'engagements',
]);
page_hero('Nos engagements', 'Qualité, traçabilité et soutien aux producteurs du Delta du Saloum.');
?>
<section class="container section" style="margin-top:3rem">
  <div class="commitments">
    <?php foreach ([
        ['sprout', 'Des matières premières locales', 'Nous nous approvisionnons auprès des producteurs, des pêcheurs et des transformatrices du Delta du Saloum. Nos céréales, fruits, produits de la mer et produits artisanaux viennent de la région.'],
        ['award', 'Une transformation soignée', 'Nos produits sont transformés sans additifs inutiles, selon des recettes traditionnelles, dans le respect des règles d\'hygiène à chaque étape : lavage, séchage, cuisson, conditionnement.'],
        ['hand-heart', 'Une rémunération équitable', 'Nous payons un prix juste aux femmes et aux hommes qui produisent et transforment. Acheter AgroMina, c\'est soutenir directement l\'économie locale du Saloum.'],
        ['shield-check', 'La satisfaction de nos clients', 'Inspection libre à la livraison : vous vérifiez votre commande avant de payer. Un souci avec un produit ? Signalez-le-nous sous 48 h et nous trouvons une solution ensemble.'],
        ['leaf', 'Le respect de l\'environnement', 'Nous privilégions des emballages réutilisables ou recyclables (bouteilles en verre, bocaux, sachets kraft) et nous protégeons les ressources fragiles des mangroves.'],
        ['truck', 'Une logistique de proximité', 'Livraison partout au Sénégal avec un appel de confirmation avant chaque envoi. Paiement en espèces à la livraison, par Wave ou par Orange Money.'],
    ] as $i => [$ic, $t, $d]): ?>
      <article class="card commitment">
        <div class="commitment-head"><span><?= icon($ic) ?></span><em>0<?= $i + 1 ?></em></div>
        <h2><?= e($t) ?></h2>
        <p><?= e($d) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="container section">
  <div class="quote card">
    <p>« Chaque produit AgroMina est le fruit du travail d'une famille, d'un village, d'un terroir. Notre rôle est de le valoriser et de le faire connaître, en respectant ceux qui le font naître. »</p>
    <span>— L'équipe AgroMina</span>
  </div>
</section>

<section class="container section">
  <div class="cta-band pattern-band">
    <div><h2>Une question sur un produit ?</h2><p>Origine, ingrédients, conservation : notre équipe vous répond.</p></div>
    <div class="cta-actions">
      <a class="btn btn-accent" href="<?= e(url('contact')) ?>">Nous contacter</a>
      <a class="btn btn-outline-light" href="<?= e(url('boutique')) ?>">Voir la boutique</a>
    </div>
  </div>
</section>
<?php page_footer(); ?>
