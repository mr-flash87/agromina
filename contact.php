<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
$err = '';
$name = $email = $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $name = str_input('name', 120);
    $email = str_input('email', 190);
    $message = str_input('message', 4000);
    $honeypot = str_input('website', 100);
    if ($honeypot !== '') {
        $sent = true; // robot : on fait semblant
    } elseif (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 5) {
        $err = 'Merci de remplir correctement tous les champs.';
    } else {
        db()->prepare('INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)')->execute([$name, $email, $message]);
        $notify = (string) cfg('order_notify_email', '');
        if ($notify !== '' && function_exists('mail')) {
            @mail($notify, '=?UTF-8?B?' . base64_encode('Nouveau message de ' . $name) . '?=', "$name <$email>\n\n$message", "Content-Type: text/plain; charset=UTF-8\r\nReply-To: $email");
        }
        flash('success', 'Message envoyé, merci !');
        redirect(url('contact'));
    }
}

page_header([
    'title' => 'Contact – AgroMina',
    'description' => "Une question, une commande professionnelle ? Écrivez à l'équipe AgroMina.",
    'active' => 'contact',
]);
page_hero('Contact', 'Particuliers ou professionnels, notre équipe vous répond.');
$phone = (string) cfg('contact_phone', '');
$mail = (string) cfg('contact_email', 'contact@agromina.sn');
$wa = shop_whatsapp_link('Bonjour AgroMina, j\'ai une question.');
?>
<div class="container contact-layout">
  <aside class="contact-cards">
    <?php if ($wa): ?>
      <a class="card contact-card is-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><span><?= icon('message') ?></span><div><b>WhatsApp</b><small>Le plus rapide : écrivez-nous</small></div><?= icon('arrow-right', 'ic go') ?></a>
    <?php endif; ?>
    <?php if ($phone !== ''): ?>
      <a class="card contact-card" href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><span><?= icon('phone') ?></span><div><b>Téléphone</b><small><?= e($phone) ?></small></div><?= icon('arrow-right', 'ic go') ?></a>
    <?php endif; ?>
    <a class="card contact-card" href="mailto:<?= e($mail) ?>"><span><?= icon('mail') ?></span><div><b>E-mail</b><small><?= e($mail) ?></small></div><?= icon('arrow-right', 'ic go') ?></a>
    <div class="card contact-card"><span><?= icon('map-pin') ?></span><div><b>Adresse</b><small><?= e(cfg('contact_address', 'Delta du Saloum, Sénégal')) ?></small></div></div>
    <div class="card contact-pro">
      <b>Professionnels, revendeurs, restaurants</b>
      <p>Vous souhaitez commander en quantité ou référencer nos produits ? Précisez-le dans votre message : nous vous proposerons des conditions adaptées.</p>
    </div>
  </aside>

  <form class="card contact-box" method="post" action="<?= e(url('contact')) ?>">
    <?= csrf_field() ?>
    <h2>Envoyez-nous un message</h2>
    <p class="muted">Nous vous répondons par e-mail dans les meilleurs délais.</p>
    <?php if ($err): ?><p class="alert-danger" style="margin:0"><?= e($err) ?></p><?php endif; ?>
    <div class="contact-row">
      <label class="label"><span>Votre nom</span><input class="field" name="name" required maxlength="120" value="<?= e($name) ?>" autocomplete="name"></label>
      <label class="label"><span>Votre e-mail</span><input class="field" name="email" type="email" required maxlength="190" value="<?= e($email) ?>" autocomplete="email"></label>
    </div>
    <label class="label"><span>Votre message</span><textarea class="field" name="message" required rows="6" maxlength="4000" placeholder="Votre question, votre commande professionnelle, une remarque…"><?= e($message) ?></textarea></label>
    <input type="text" name="website" tabindex="-1" autocomplete="off" class="sr-only" aria-hidden="true">
    <button class="btn btn-accent">Envoyer le message <?= icon('arrow-right') ?></button>
  </form>
</div>

<section class="container section">
  <div class="section-head"><h2 class="section-title">Questions fréquentes</h2></div>
  <div class="faq">
    <?php foreach ([
        ['Comment passer commande ?', "Ajoutez vos produits au panier, puis remplissez votre nom, votre numéro WhatsApp et votre adresse. Pas besoin de créer de compte. Vous pouvez aussi commander directement sur WhatsApp."],
        ['Comment se passe la livraison ?', 'Nous livrons partout au Sénégal. Après votre commande, nous vous appelons pour confirmer l\'adresse et le moment de la livraison. Les frais de livraison sont de ' . fcfa((int) cfg('delivery_fee', 1500)) . '.'],
        ['Quels moyens de paiement acceptez-vous ?', "Le paiement en espèces à la livraison, Wave et Orange Money. Avec le paiement à la livraison, vous vérifiez votre commande avant de payer."],
        ['Comment suivre ma commande ?', "Avec la référence reçue à la fin de la commande (AM-…) et votre numéro de téléphone, sur la page « Suivre ma commande »."],
        ['Un produit ne me convient pas, que faire ?', "Contactez-nous dans les 48 heures suivant la livraison avec votre référence de commande : nous trouverons une solution ensemble (échange ou remboursement selon le cas)."],
    ] as [$q, $a]): ?>
      <details class="card faq-item"><summary><?= e($q) ?><?= icon('chevron-down') ?></summary><p><?= e($a) ?></p></details>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="margin-top:1rem"><a class="see-all" href="<?= e(url('suivi')) ?>">Suivre ma commande <?= icon('arrow-right') ?></a></p>
</section>
<?php page_footer(); ?>
