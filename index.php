<?php
require __DIR__ . '/inc/bootstrap.php';

$ed = edition_inscriptions();
$res = null;
$err = '';

if ($ed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (post('site_web') !== '') { // pot de miel anti-robots
        $res = ['deja' => false];
    } elseif (!filter_var(post('mail'), FILTER_VALIDATE_EMAIL)) {
        $err = 'Adresse mail invalide.';
    } elseif (post('nom') === '' || post('prenom') === '') {
        $err = 'Nom et prénom obligatoires.';
    } elseif (!post('accord')) {
        $err = 'Merci d\'accepter le règlement.';
    } else {
        $club = post('club') === '__autre' ? post('club_autre') : post('club');
        $res = inscrire((int) $ed['id'], post('nom'), post('prenom'), post('mail'), $club);
    }
}

$clubs = q("SELECT club, COUNT(*) n FROM participants WHERE club IS NOT NULL AND club <> '' GROUP BY club ORDER BY club")->fetchAll(PDO::FETCH_COLUMN);
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inscription – Cadavre Exquis Photographique</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>"><?= pwa_head() ?>
<body class="public"><main class="carte">
<h1>Cadavre Exquis <span>Photographique</span></h1>

<?php if (!$ed): ?>
  <p>Les inscriptions ne sont pas ouvertes pour le moment. Revenez bientôt !</p>

<?php elseif ($res): ?>
  <?php if ($res['deja']): ?>
    <p class="alerte ok">Vous êtes déjà inscrit(e) avec cette adresse. Vous recevrez un mail dès que ce sera votre tour.</p>
  <?php else: ?>
    <p class="alerte ok">Merci <?= h($res['participant']['prenom'] ?? '') ?> ! Votre inscription au <?= h($ed['nom']) ?> est enregistrée.
      Un mail de confirmation vient de vous être envoyé.
      <?= !empty($res['maillon']) ? '<br><strong>Bonne nouvelle : c\'est déjà votre tour, regardez vos mails !</strong>' : 'Vous recevrez un mail dès que ce sera votre tour.' ?></p>
    <p class="petit">Pensez à vérifier vos courriers indésirables et à ajouter <?= h(cfg('mail.from')) ?> à vos contacts.</p>
  <?php endif ?>

<?php else: ?>
  <h2><?= h($ed['nom']) ?></h2>
  <div class="regles"><?= $ed['texte_accueil'] ? nl2br(h($ed['texte_accueil'])) : '
    <p>Le principe : chaque participant reçoit la photo de la personne qui le précède dans sa chaîne,
    et réalise une photo qui s\'en inspire (forme, couleur, sujet, idée…). Il ne voit que cette photo-là :
    le résultat complet est dévoilé à la fin.</p>
    <p>Vous disposez de ' . (int) cfg('delai_jours') . ' jours pour déposer votre photo à partir du moment où vous la recevez.</p>' ?></div>

  <?php if ($err): ?><p class="alerte ko"><?= h($err) ?></p><?php endif ?>
  <form method="post" class="form">
    <?= csrf_field() ?>
    <div class="ligne">
      <label>Prénom<input name="prenom" required value="<?= h(post('prenom')) ?>" autocomplete="given-name"></label>
      <label>Nom<input name="nom" required value="<?= h(post('nom')) ?>" autocomplete="family-name"></label>
    </div>
    <label>Adresse mail<input name="mail" type="email" required value="<?= h(post('mail')) ?>" autocomplete="email"></label>
    <label>Club photo
      <select name="club" id="club" required>
        <option value="">— Choisir —</option>
        <?php foreach ($clubs as $c): ?><option <?= post('club') === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach ?>
        <option value="Indépendant">Indépendant (pas de club)</option>
        <option value="__autre" <?= post('club') === '__autre' ? 'selected' : '' ?>>Autre club…</option>
      </select>
    </label>
    <label id="club_autre_l" hidden>Nom du club (et ville)<input name="club_autre" value="<?= h(post('club_autre')) ?>" placeholder="ex. Photo Club de Neyron (Neyron)"></label>
    <input name="site_web" class="pot" tabindex="-1" autocomplete="off">
    <label class="case"><input type="checkbox" name="accord" value="1" required>
      J'accepte que mes photos soient projetées et présentées lors de la restitution du cadavre exquis,
      avec mon nom et mon club. Mes coordonnées ne servent qu'à l'organisation du jeu.</label>
    <button class="btn">Je m'inscris</button>
  </form>
  <script>
    const s = document.getElementById('club'), a = document.getElementById('club_autre_l');
    const maj = () => { a.hidden = s.value !== '__autre'; a.querySelector('input').required = !a.hidden; };
    s.addEventListener('change', maj); maj();
  </script>
<?php endif ?>
</main></body></html>
