<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

$ch = chaine((int) get('id'));
if (!$ch) redirect('index.php');
$ed = edition((int) $ch['edition_id']);
$self = 'chaine.php?id=' . $ch['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $m = post('maillon') ? maillon((int) post('maillon')) : null;
    if ($m && $m['chaine_id'] != $ch['id']) $m = null;
    $p = $m ? participant((int) $m['participant_id']) : null;
    try {
        switch (post('action')) {
            case 'relancer':
                envoyer_modele('relance', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
                update('maillons', (int) $m['id'], ['relance_envoyee' => 1]);
                flash('Relance envoyée à ' . $p['prenom'] . ' ' . $p['nom'] . '.');
                break;
            case 'renvoyer':
                envoyer_modele('a_toi', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
                flash('Mail « c\'est ton tour » renvoyé.');
                break;
            case 'prolonger':
                update('maillons', (int) $m['id'], ['date_limite' => plus_jours($m['date_limite'], (int) post('jours', 7)),
                    'relance_envoyee' => 0, 'alerte_envoyee' => 0]);
                flash('Délai prolongé.');
                break;
            case 'passer':
                passer_maillon($m, post('note') ?: 'Passé par l\'administrateur');
                flash($p['prenom'] . ' ' . $p['nom'] . ' passé(e) ; la chaîne continue.');
                break;
            case 'deposer': // photo reçue par mail : l'admin la dépose à la place du participant
                deposer_photo($m, $_FILES['photo'] ?? []);
                flash('Photo déposée pour ' . $p['prenom'] . ' ' . $p['nom'] . ', le suivant a été prévenu.');
                break;
            case 'remplacer':
                [$o, $r] = enregistrer_image($_FILES['photo'] ?? [], $ch['edition_id'] . '/' . $ch['numero'],
                    sprintf('%02d %s %s', $m['position'], $p['prenom'], $p['nom']) . ' v' . date('His'));
                update('maillons', (int) $m['id'], ['photo' => $o, 'photo_reduite' => $r]);
                flash('Photo remplacée.');
                break;
            case 'placer':
                $np = participant((int) post('participant'));
                if (!$np || $np['statut'] !== 'attente' || $np['edition_id'] != $ch['edition_id']) throw new RuntimeException('Participant indisponible.');
                if (qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'en_cours'", [$ch['id']])) throw new RuntimeException('Un participant est déjà en cours sur cette chaîne.');
                lancer_maillon($ch, $np);
                flash($np['prenom'] . ' ' . $np['nom'] . ' placé(e) dans la chaîne et prévenu(e).');
                break;
            case 'fermer':
                update('chaines', (int) $ch['id'], ['statut' => 'close']);
                flash('Chaîne terminée (le participant en cours peut encore déposer sa photo).');
                break;
            case 'supprimer':
                supprimer_chaine($ch, (bool) post('avec_participants'));
                flash('Chaîne ' . $ch['numero'] . ' supprimée.');
                redirect('index.php');
            case 'textes':
                update('maillons', (int) $m['id'], ['titre' => mb_substr(post('titre'), 0, 200) ?: null,
                    'demarche' => mb_substr(str_replace("\r\n", "\n", post('demarche')), 0, 600) ?: null]);
                flash('Titre et démarche enregistrés.');
                break;
            case 'rouvrir':
                update('chaines', (int) $ch['id'], ['statut' => 'active']);
                avancer_chaine((int) $ch['id']);
                flash('Chaîne rouverte.');
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'ko');
    }
    redirect($self);
}

$ms = maillons_chaine((int) $ch['id']);
$en_cours = (bool) array_filter($ms, fn($m) => $m['statut'] === 'en_cours');
$attente = q("SELECT * FROM participants WHERE edition_id = ? AND statut = 'attente' ORDER BY date_inscription", [$ch['edition_id']])->fetchAll();

entete('Chaîne ' . $ch['numero'], 'index');
?>
<h1>Chaîne <?= h($ch['numero']) ?> <?= badge($ch['statut']) ?></h1>
<div class="actions">
  <a class="btn sec" href="index.php">← Tableau de bord</a>
  <a class="btn sec" href="presentation.php?chaine=<?= $ch['id'] ?>">▣ Présentation</a>
  <a class="btn sec" href="export.php?chaine=<?= $ch['id'] ?>&amp;zip=1">⇩ Photos (zip)</a>
  <?php if ($ch['statut'] !== 'close'): ?>
    <form method="post" onsubmit="return confirm('Terminer cette chaîne ?')"><?= csrf_field() ?><input type="hidden" name="action" value="fermer"><button class="btn sec">■ Terminer la chaîne</button></form>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rouvrir"><button class="btn sec">↺ Rouvrir</button></form>
    <form method="post" class="actions" style="margin:0" onsubmit="return confirm('Supprimer définitivement la chaîne <?= h($ch['numero']) ?> et ses photos ?')"><?= csrf_field() ?>
      <input type="hidden" name="action" value="supprimer">
      <label class="case" style="font-size:13px"><input type="checkbox" name="avec_participants" value="1" checked> et ses participants</label>
      <button class="btn danger">✕ Supprimer la chaîne</button></form>
  <?php endif ?>
</div>

<?php if (!$en_cours && $ch['statut'] !== 'close' && $attente): ?>
<form method="post" class="actions panneau"><?= csrf_field() ?><input type="hidden" name="action" value="placer">
  <span>Placer manuellement :</span>
  <select name="participant" style="width:auto"><?php foreach ($attente as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['nom'] . ' ' . $p['prenom'] . ' – ' . $p['club']) ?></option><?php endforeach ?></select>
  <button class="btn petit">Placer et envoyer le mail</button>
</form>
<?php endif ?>

<table class="liste">
<tr><th>#</th><th>Photo</th><th>Participant</th><th>Club</th><th>Envoi</th><th>Limite</th><th>Retour</th><th>État</th><th>Actions</th></tr>
<tr><td>0</td><td><?php if ($ed['photo_base']): ?><img class="mini" src="../img.php?base=<?= $ed['id'] ?>"><?php endif ?></td><td colspan="7"><em>Photo de départ</em></td></tr>
<?php foreach ($ms as $m): ?>
<tr>
  <td><?= $m['position'] ?></td>
  <td><?php if ($m['photo_reduite']): ?><a href="../img.php?m=<?= $m['id'] ?>&amp;v=o" target="_blank"><img class="mini" src="../img.php?m=<?= $m['id'] ?>" loading="lazy"></a><?php endif ?></td>
  <td><strong><?= h($m['nom']) ?></strong> <?= h($m['prenom']) ?><br><span class="petit"><?= h($m['mail']) ?></span><?= $m['titre'] ? '<br><em>« ' . h($m['titre']) . ' »</em>' : '' ?><?= !empty($m['demarche']) ? '<br><span class="petit" title="' . h($m['demarche']) . '">Démarche : ' . h(mb_strimwidth($m['demarche'], 0, 90, '…')) . '</span>' : '' ?></td>
  <td><?= h($m['club']) ?></td>
  <td><?= fdate($m['date_envoi']) ?></td>
  <td><?= fdate($m['date_limite']) ?><?= $m['relance_envoyee'] ? '<br><span class="petit">relancé</span>' : '' ?></td>
  <td><?= fdate($m['date_retour']) ?></td>
  <td><?= en_retard($m) ? badge('retard') : badge($m['statut']) ?><?= $m['note'] ? '<br><span class="petit">' . h($m['note']) . '</span>' : '' ?></td>
  <td>
  <?php if ($m['statut'] === 'en_cours'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="relancer"><button class="btn petit sec">Relancer</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="prolonger"><input type="hidden" name="jours" value="7"><button class="btn petit sec">+7 j</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="renvoyer"><button class="btn petit sec" title="Renvoyer le mail avec le lien">Renvoyer lien</button></form>
    <form method="post" onsubmit="this.note.value = prompt('Raison (facultatif) :', 'Pas de réponse') ?? ''; return confirm('Passer ce participant et envoyer au suivant ?')"><?= csrf_field() ?>
      <input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="passer"><input type="hidden" name="note"><button class="btn petit danger">Passer</button></form>
    <form method="post" enctype="multipart/form-data" style="display:block;margin-top:6px"><?= csrf_field() ?>
      <input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="deposer"><input type="hidden" name="accord" value="1">
      <input type="file" name="photo" accept="image/*" required style="width:200px;padding:3px"> <button class="btn petit">Déposer pour lui</button></form>
    <span class="petit">Lien : <a href="<?= h(url('depot.php?t=' . $m['token'])) ?>" target="_blank">page de dépôt</a></span>
  <?php elseif ($m['statut'] === 'rendu'): ?>
    <a class="btn petit sec" href="../img.php?m=<?= $m['id'] ?>&amp;v=o&amp;dl=1">Original</a>
    <details style="margin-top:6px"><summary class="petit" style="cursor:pointer">Titre / démarche</summary>
      <form method="post" class="form" style="margin-top:6px;min-width:260px"><?= csrf_field() ?><input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="textes">
        <input name="titre" maxlength="200" placeholder="Titre" value="<?= h($m['titre']) ?>">
        <textarea name="demarche" class="court" maxlength="600" placeholder="Démarche"><?= h($m['demarche'] ?? '') ?></textarea>
        <button class="btn petit">Enregistrer</button></form></details>
    <form method="post" enctype="multipart/form-data" style="display:block;margin-top:6px"><?= csrf_field() ?>
      <input type="hidden" name="maillon" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="remplacer">
      <input type="file" name="photo" accept="image/*" required style="width:200px;padding:3px"> <button class="btn petit sec">Remplacer</button></form>
  <?php endif ?>
  </td>
</tr>
<?php endforeach ?>
</table>
<?php pied();
