<?php
require __DIR__ . '/inc/bootstrap.php';

$t = get('t');
$m = $t !== '' ? q1('SELECT * FROM maillons WHERE token = ?', [$t]) : null;
$err = '';
$ok = false;

if ($m && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if (post('action') === 'desister') {
            passer_maillon($m, 'Désistement du participant');
            mail_admin('Désistement', "Désistement via le lien de dépôt (maillon #{$m['id']}). La chaîne a continué automatiquement.");
        } else {
            if (!post('accord')) throw new RuntimeException('Merci de cocher l\'accord de diffusion.');
            if (!array_key_exists((string) post('type_photo'), types_photo())) throw new RuntimeException('Indiquez si la photo a été réalisée pour l\'occasion ou si c\'est une photo d\'archive.');
            deposer_photo($m, $_FILES['photo'] ?? []);
            $ok = true;
        }
        redirect(url('depot.php?t=' . urlencode($t) . ($ok ? '&ok=1' : '')));
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
// Fichier trop gros pour PHP : $_POST est vide
if ($m && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) $err = 'Fichier trop volumineux pour le serveur.';

$p = $m ? participant((int) $m['participant_id']) : null;
$ch = $m ? chaine((int) $m['chaine_id']) : null;
$ed = $ch ? edition((int) $ch['edition_id']) : null;
$prec = $m ? photo_precedente($m) : null;
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cadavre Exquis – Dépôt</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>"><?= pwa_head('manifest-suivi.webmanifest', 'Suivi Cadavre') ?>
<body class="public"><main class="carte large">
<h1>Cadavre Exquis <span>Photographique</span></h1>

<?php if (!$m): ?>
  <p class="alerte ko">Ce lien n'est pas valide. Vérifiez qu'il a été copié en entier depuis votre mail.</p>

<?php elseif ($m['statut'] === 'passe'): ?>
  <p>Bonjour <?= h($p['prenom']) ?>, ce lien n'est plus actif : la chaîne <?= h($ch['numero']) ?> a continué sans vous.
     Si c'est une erreur, répondez au mail reçu.</p>

<?php elseif ($m['statut'] === 'rendu'): ?>
  <p class="alerte ok"><?= get('ok') ? 'Merci ' . h($p['prenom']) . ', votre photo est bien arrivée et vient d\'être transmise au participant suivant !' : 'Votre photo a bien été reçue le ' . fdate($m['date_retour'], true) . '. Merci !' ?></p>
  <div class="duo">
    <figure><img src="img.php?t=<?= h($t) ?>&amp;q=prec" alt=""><figcaption>La photo reçue</figcaption></figure>
    <figure><img src="img.php?t=<?= h($t) ?>&amp;q=moi" alt=""><figcaption>Votre photo<?= $m['titre'] ? ' – « ' . h($m['titre']) . ' »' : '' ?></figcaption></figure>
  </div>
  <?php if (!empty($m['type_photo'])): ?><p class="petit"><?= h(libelle_type_photo($m['type_photo'])) ?></p><?php endif ?>
  <?php if (!empty($m['demarche'])): ?><p class="regles"><em><?= nl2br(h($m['demarche'])) ?></em></p><?php endif ?>
  <p class="petit">Le résultat complet sera dévoilé à la fin du <?= h($ed['nom']) ?>.</p>

<?php else: ?>
  <p>Bonjour <strong><?= h($p['prenom']) ?></strong>, c'est votre tour dans la <strong>chaîne <?= h($ch['numero']) ?></strong>.
     Voici la photo qui vous précède<?= $prec['type'] === 'base' ? ' (la photo de départ)' : '' ?> :</p>
  <figure class="unique"><a href="img.php?t=<?= h($t) ?>&amp;q=prec&amp;dl=1" title="Télécharger"><img src="img.php?t=<?= h($t) ?>&amp;q=prec" alt="Photo précédente"></a>
    <figcaption><a href="img.php?t=<?= h($t) ?>&amp;q=prec&amp;dl=1">Télécharger la photo</a></figcaption></figure>

  <p class="delai">À déposer avant le <strong><?= fdate($m['date_limite']) ?></strong>
    <?php $j = (int) ceil((strtotime($m['date_limite']) - time()) / 86400); ?>
    <?= $j >= 0 ? "(encore $j jour" . ($j > 1 ? 's' : '') . ')' : '(délai dépassé, mais vous pouvez encore la déposer)' ?></p>

  <?php if ($err): ?><p class="alerte ko"><?= h($err) ?></p><?php endif ?>
  <form method="post" enctype="multipart/form-data" class="form" id="f">
    <?= csrf_field() ?>
    <label>Votre photo (JPEG, pleine résolution, <?= (int) cfg('upload_max_mo') ?> Mo max)
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required id="photo"></label>
    <img id="apercu" alt="" hidden>
    <fieldset class="choix-type"><legend>Ta photo est…</legend>
      <?php foreach (types_photo() as $k => $lib): ?>
        <label class="case"><input type="radio" name="type_photo" value="<?= $k ?>" required <?= post('type_photo') === $k ? 'checked' : '' ?>> <?= h($lib) ?></label>
      <?php endforeach ?>
    </fieldset>
    <label>Titre (facultatif)<input name="titre" maxlength="200" value="<?= h(post('titre')) ?>"></label>
    <label>Ta démarche en quelques mots (facultatif)
      <textarea name="demarche" class="court" maxlength="600" placeholder="Ce que tu as retenu de la photo précédente, ton idée, ta technique…"><?= h(post('demarche')) ?></textarea>
      <span class="petit">Affiché avec ta photo lors de la projection. 600 caractères maximum.</span></label>
    <label class="case"><input type="checkbox" name="accord" value="1" required>
      Je certifie être l'auteur de cette photo et j'accepte qu'elle soit transmise au participant suivant
      et projetée lors de la restitution.</label>
    <button class="btn" id="go">Envoyer ma photo</button>
  </form>
  <form method="post" class="desister" onsubmit="return confirm('Confirmer : vous ne participerez pas et la chaîne passe au suivant ?')">
    <?= csrf_field() ?><input type="hidden" name="action" value="desister">
    <button class="lien">Je ne peux finalement pas participer</button>
  </form>
  <script>
    const f = document.getElementById('photo'), ap = document.getElementById('apercu'), max = <?= (int) cfg('upload_max_mo') ?> * 1048576;
    f.addEventListener('change', () => {
      const x = f.files[0]; if (!x) return;
      if (x.size > max) { alert('Fichier trop lourd (' + (x.size / 1048576).toFixed(1) + ' Mo).'); f.value = ''; ap.hidden = true; return; }
      ap.src = URL.createObjectURL(x); ap.hidden = false;
    });
    document.getElementById('f').addEventListener('submit', () => { const b = document.getElementById('go'); b.disabled = true; b.textContent = 'Envoi en cours…'; });
  </script>
<?php endif ?>
</main></body></html>
