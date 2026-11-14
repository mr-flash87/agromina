<?php
require __DIR__ . '/includes/bootstrap.php';
page_header(['title' => 'Politique de confidentialité – AgroMina', 'description' => 'Quelles données AgroMina collecte, pourquoi, combien de temps elles sont conservées et comment exercer vos droits.']);
page_hero('Politique de confidentialité', 'Comment AgroMina collecte, utilise et protège vos données.');

$contact = '<a href="' . e(url('contact')) . '">page Contact</a>';
$mail = e((string) cfg('contact_email', 'contact@agromina.sn'));

legal_page([
    ['Qui est responsable de vos données ?', '<p>Le responsable du traitement des données collectées sur ce site est :</p>' . legal_identity()
        . '<p>Pour toute question sur vos données, écrivez-nous à <a href="mailto:' . $mail . '">' . $mail . '</a> ou via la ' . $contact . '.</p>'],
    ['Les données que nous collectons', '<p>Nous collectons uniquement ce qui est nécessaire :</p><ul>'
        . '<li><b>Lors d\'une commande</b> : nom et prénom, numéro de téléphone, adresse et ville de livraison, indications d\'adresse, e-mail (facultatif), mode de paiement choisi et produits commandés ;</li>'
        . '<li><b>Lors d\'un message</b> via le formulaire de contact : nom, e-mail et contenu du message ;</li>'
        . '<li><b>Lors de l\'inscription à la newsletter</b> : adresse e-mail.</li></ul>'
        . '<p>Aucun compte client n\'est créé et <b>aucune donnée bancaire</b> n\'est demandée ni enregistrée sur le site.</p>'],
    ['Pourquoi nous les utilisons', '<ul><li>Traiter, confirmer et livrer vos commandes, et vous en donner le suivi ;</li>'
        . '<li>Vous recontacter au sujet d\'une commande (appel, WhatsApp, SMS) ;</li>'
        . '<li>Répondre à vos messages ;</li>'
        . '<li>Vous envoyer nos nouveautés et promotions, uniquement si vous vous êtes inscrit à la newsletter ;</li>'
        . '<li>Tenir notre comptabilité et respecter nos obligations légales.</li></ul>'
        . '<p>Vos données ne sont <b>jamais vendues ni louées</b>.</p>'],
    ['Qui peut y accéder', '<p>Seule l\'équipe AgroMina habilitée (administrateurs et gérants) accède à vos données, ainsi que, si nécessaire, la personne chargée de votre livraison (nom, téléphone, adresse). Le site est hébergé par Hostinger, qui stocke les données pour notre compte.</p>'],
    ['Combien de temps nous les gardons', '<ul><li>Données de commande : le temps nécessaire au traitement de la commande puis pendant la durée imposée par les obligations comptables et légales ;</li>'
        . '<li>Messages de contact : le temps nécessaire pour vous répondre et assurer le suivi de votre demande ;</li>'
        . '<li>Newsletter : jusqu\'à votre désinscription.</li></ul>'],
    ['Cookies et stockage sur votre appareil', '<p>Le site n\'utilise <b>pas de cookies publicitaires ni d\'outil de suivi</b>. Il utilise seulement :</p><ul>'
        . '<li>un cookie de session, indispensable à la sécurité des formulaires et à la connexion de l\'équipe ;</li>'
        . '<li>le stockage de votre navigateur pour retenir <b>votre panier et vos favoris</b> sur votre appareil.</li></ul>'
        . '<p>Les polices d\'écriture du site sont chargées depuis Google Fonts, ce qui transmet votre adresse IP à Google.</p>'],
    ['Sécurité', '<p>Nous protégeons vos données : connexion sécurisée (https), accès à l\'espace de gestion protégé par mot de passe et réservé à l\'équipe, mots de passe chiffrés, protection des formulaires contre les envois frauduleux.</p>'],
    ['Vos droits', '<p>Conformément à la loi sénégalaise n° 2008-12 du 25 janvier 2008 sur la protection des données à caractère personnel, vous pouvez à tout moment demander l\'<b>accès</b> à vos données, leur <b>rectification</b> ou leur <b>suppression</b>, et vous <b>opposer</b> à leur utilisation, notamment pour la newsletter.</p>'
        . '<p>Écrivez-nous à <a href="mailto:' . $mail . '">' . $mail . '</a> ou via la ' . $contact . ' en indiquant votre numéro de téléphone ou votre référence de commande. Si vous estimez que vos droits ne sont pas respectés, vous pouvez saisir la Commission de Protection des Données Personnelles (CDP) du Sénégal.</p>'],
    ['Modifications', '<p>Nous pouvons mettre à jour cette politique, par exemple si le site évolue. La date de dernière mise à jour figure en haut de cette page.</p>'],
], '14 novembre 2026');
page_footer();
