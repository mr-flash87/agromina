<?php
require __DIR__ . '/includes/bootstrap.php';
page_header(['title' => 'Conditions générales de vente – AgroMina', 'description' => 'Les conditions applicables à vos commandes sur la boutique en ligne AgroMina : commande, prix, paiement, livraison, réclamations.']);
page_hero('Conditions générales de vente', 'Les règles applicables à vos commandes sur la boutique AgroMina.');

$fee = fcfa((int) cfg('delivery_fee', 1500));
$contact = '<a href="' . e(url('contact')) . '">page Contact</a>';
$wa = shop_whatsapp_link();
$waTxt = $wa ? ', par <a href="' . e($wa) . '" target="_blank" rel="noopener">WhatsApp</a>' : '';

legal_page([
    ['Objet', '<p>Les présentes conditions générales de vente (CGV) s\'appliquent à toutes les commandes passées sur la boutique en ligne AgroMina par des particuliers ou des professionnels, pour une livraison au Sénégal. Passer une commande vaut acceptation de ces conditions.</p>'
        . '<p>Le vendeur est :</p>' . legal_identity()],
    ['Produits', '<p>Nous proposons des produits locaux transformés : céréales, jus et boissons, fruits et légumes transformés, produits halieutiques, produits traditionnels et artisanaux. Les photos sont aussi fidèles que possible, mais de légères différences d\'aspect ou d\'emballage peuvent exister, notamment pour les produits artisanaux.</p>'
        . '<p>Les produits sont proposés dans la limite des stocks disponibles. Le stock affiché est mis à jour à chaque commande.</p>'],
    ['Prix', '<p>Les prix sont indiqués en francs CFA (FCFA), toutes taxes comprises. Les <b>frais de livraison de ' . e($fee) . '</b> s\'ajoutent au montant des produits et sont affichés avant la validation de la commande.</p>'
        . '<p>Les prix des promotions s\'appliquent pendant la durée indiquée. Le prix facturé est celui affiché au moment de la validation de la commande.</p>'],
    ['Commande', '<p>Pour commander, ajoutez les produits au panier puis indiquez votre nom, un numéro de téléphone joignable (WhatsApp de préférence), votre adresse de livraison et votre mode de paiement. Aucune création de compte n\'est nécessaire.</p>'
        . '<p>Après validation, vous recevez une <b>référence de commande</b> (AM-…). Notre équipe vous appelle pour confirmer la commande, l\'adresse et le moment de la livraison. Nous pouvons refuser ou annuler une commande en cas d\'informations incomplètes, de numéro injoignable ou de produit devenu indisponible ; vous en êtes alors informé.</p>'
        . '<p>Vous pouvez aussi commander directement auprès de nous' . $waTxt . '.</p>'],
    ['Paiement', '<p>Nous acceptons :</p><ul><li><b>Le paiement en espèces à la livraison</b>, après vérification de votre commande ;</li><li><b>Wave</b> et <b>Orange Money</b> : la demande de paiement vous est envoyée après la confirmation de la commande.</li></ul>'
        . '<p>Aucune donnée bancaire n\'est demandée ni enregistrée sur le site.</p>'],
    ['Livraison', '<p>Nous livrons partout au Sénégal. Le délai est confirmé lors de l\'appel de confirmation et dépend de votre ville. Veillez à rester joignable au numéro indiqué : en cas d\'absence ou d\'adresse erronée, une nouvelle livraison pourra être facturée.</p>'
        . '<p>Vous pouvez suivre l\'avancement de votre commande sur la page <a href="' . e(url('suivi')) . '">Suivre ma commande</a> avec votre référence et votre numéro de téléphone.</p>'],
    ['Inspection à la livraison et réclamations', '<p>Vous pouvez <b>vérifier votre commande avant de la payer</b>. Si un produit est abîmé, manquant ou ne correspond pas à votre commande, signalez-le au livreur ou contactez-nous <b>dans les 48 heures</b> suivant la livraison, avec votre référence de commande, via la ' . $contact . $waTxt . '.</p>'
        . '<p>Après vérification, nous vous proposons un échange ou un remboursement du produit concerné. Pour des raisons d\'hygiène et de sécurité alimentaire, les produits ouverts ou entamés ne sont ni repris ni échangés, sauf défaut de qualité.</p>'],
    ['Conservation des produits', '<p>Respectez les conditions de conservation indiquées sur les emballages (au frais, au sec, à l\'abri de la lumière) et les dates de consommation. Les jus naturels, sans conservateur, se conservent au réfrigérateur et se consomment rapidement après ouverture.</p>'],
    ['Données personnelles', '<p>Les informations de votre commande servent uniquement à la traiter et à vous livrer. Pour en savoir plus, consultez notre <a href="' . e(url('confidentialite')) . '">politique de confidentialité</a>.</p>'],
    ['Litiges et droit applicable', '<p>Les présentes CGV sont soumises au droit sénégalais. En cas de désaccord, nous vous invitons à nous contacter d\'abord pour trouver une solution amiable. À défaut, le litige sera porté devant les juridictions compétentes du Sénégal.</p>'
        . '<p>AgroMina peut modifier ces CGV à tout moment ; les conditions applicables sont celles en vigueur au jour de la commande.</p>'],
], '14 novembre 2026');
page_footer();
