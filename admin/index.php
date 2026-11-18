<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

if (get('edition') !== '') { $_SESSION['edition_id'] = (int) get('edition'); redirect('index.php'); }
$ed = edition_admin();
if (!$ed) redirect('edition.php?nouvelle=1');
$eid = (int) $ed['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    switch (post('action')) {
        case 'demarrer':
            $n = demarrer_edition($ed);
            flash("Édition démarrée : $n chaîne(s) lancée(s).");
            break;
        case 'cloturer':
            update('editions', $eid, ['statut' => 'terminee', 'inscriptions_ouvertes' => 0]);
            foreach (q("SELECT * FROM chaines WHERE edition_id = ? AND statut <> 'close'", [$eid])->fetchAll() as $ch) {
                if (!qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'en_cours'", [$ch['id']])) {
                    update('chaines', (int) $ch['id'], ['statut' => 'close']);
                }
            }
            flash('Édition clôturée : plus aucun nouvel envoi. Les photos en cours peuvent encore être déposées.');
            break;
        case 'points':
            $n = envoyer_points_attente($ed, true);
            flash("Point sur la file d'attente mis en file d'envoi pour $n inscrit(s), fin estimée " . fin_estimee_file() . '.');
            break;
        case 'publier':
            lien_resultats($ed);
            update('editions', $eid, ['resultats_publies' => post('publier') ? 1 : 0]);
            flash(post('publier') ? 'Résultats publiés : le lien est actif.' : 'Résultats dépubliés : le lien ne montre plus rien.');
            break;
        case 'mail_resultats':
            if (!$ed['resultats_publies']) { flash('Publiez d\'abord les résultats.', 'ko'); break; }
            lien_resultats($ed);
            $n = 0;
            foreach (q("SELECT DISTINCT p.* FROM participants p JOIN maillons m ON m.participant_id = p.id
                        WHERE p.edition_id = ? AND m.statut = 'rendu' AND p.mail_resultats = 0", [$eid])->fetchAll() as $p) {
                if (mettre_en_file('resultats', $p['mail'], vars_mail($p), $p['prenom'] . ' ' . $p['nom'])) {
                    update('participants', (int) $p['id'], ['mail_resultats' => 1]); $n++;
                }
            }
            flash("Lien des résultats mis en file d'envoi pour $n participant(s), fin estimée " . fin_estimee_file() . '.');
            break;
        case 'mail_fin':
            $n = 0;
            foreach (q("SELECT DISTINCT p.* FROM participants p JOIN maillons m ON m.participant_id = p.id
                        WHERE p.edition_id = ? AND m.statut = 'rendu' AND p.mail_fin = 0", [$eid])->fetchAll() as $p) {
                if (mettre_en_file('fin', $p['mail'], vars_mail($p), $p['prenom'] . ' ' . $p['nom'])) {
                    update('participants', (int) $p['id'], ['mail_fin' => 1]); $n++;
                }
            }
            flash("Mail « cadavre terminé » mis en file pour $n participant(s), fin estimée " . fin_estimee_file() . '.');
            break;
    }
    redirect('index.php');
}

$st = [
    'inscrits' => qv('SELECT COUNT(*) FROM participants WHERE edition_id = ?', [$eid]),
    'attente' => qv("SELECT COUNT(*) FROM participants WHERE edition_id = ? AND statut = 'attente'", [$eid]),
    'rendus' => qv("SELECT COUNT(*) FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE c.edition_id = ? AND m.statut = 'rendu'", [$eid]),
    'en_cours' => qv("SELECT COUNT(*) FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE c.edition_id = ? AND m.statut = 'en_cours'", [$eid]),
    'retard' => qv("SELECT COUNT(*) FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE c.edition_id = ? AND m.statut = 'en_cours' AND m.date_limite < ?", [$eid, now()]),
];
$chaines = q('SELECT * FROM chaines WHERE edition_id = ? ORDER BY numero', [$eid])->fetchAll();
$attente = file_attente($eid);
$res_restants = (int) qv("SELECT COUNT(DISTINCT p.id) FROM participants p JOIN maillons m ON m.participant_id = p.id WHERE p.edition_id = ? AND m.statut = 'rendu' AND p.mail_resultats = 0", [$eid]);
$fin_restants = qv("SELECT COUNT(DISTINCT p.id) FROM participants p JOIN maillons m ON m.participant_id = p.id WHERE p.edition_id = ? AND m.statut = 'rendu' AND p.mail_fin = 0", [$eid]);

entete('Tableau de bord', 'index');
?>
<h1><?= h($ed['nom']) ?> <?= badge($ed['statut']) ?> <?= $ed['inscriptions_ouvertes'] ? badge('active', 'inscriptions ouvertes') : '' ?></h1>

<?php if (!$ed['photo_base']): ?>
  <p class="alerte info">Pas encore de photo de départ : <a href="edition.php#photo">ajoutez-la dans Édition</a> (elle est nécessaire pour démarrer).</p>
<?php endif ?>
<div class="actions">
  <?php if ($ed['statut'] !== 'terminee'): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="demarrer">
      <button class="btn" <?= $ed['photo_base'] ? '' : 'disabled title="Ajoutez d\'abord la photo de départ"' ?>><?= $ed['statut'] === 'preparation' ? '▶ Démarrer l\'édition' : '▶ Relancer les chaînes sans participant' ?></button></form>
    <form method="post" onsubmit="return confirm('Clôturer : plus aucun nouveau participant ne sera envoyé. Continuer ?')"><?= csrf_field() ?>
      <input type="hidden" name="action" value="cloturer"><button class="btn sec">■ Clôturer l'édition</button></form>
  <?php else: ?>
    <form method="post" onsubmit="return confirm('Envoyer le mail « cadavre terminé » à <?= (int) $fin_restants ?> participant(s) ?')"><?= csrf_field() ?>
      <input type="hidden" name="action" value="mail_fin"><button class="btn" <?= $fin_restants ? '' : 'disabled' ?>>✉ Mail « Cadavre terminé » (<?= (int) $fin_restants ?>)</button></form>
  <?php endif ?>
  <a class="btn sec" href="presentation.php">▣ Présentation / diaporama</a>
  <a class="btn sec" href="export.php">⇩ Export</a>
  <a class="btn sec" href="<?= h(url()) ?>" target="_blank">Formulaire d'inscription ↗</a>
  <a class="btn sec" href="<?= h(url('suivi.php')) ?>" target="_blank">Page de suivi publique ↗</a>
</div>

<?php if ($ed['statut'] === 'terminee' || $ed['resultats_publies']): ?>
<div class="panneau actions" style="margin-bottom:14px">
  <strong>Résultats en ligne</strong>
  <?= $ed['resultats_publies'] ? badge('rendu', 'publiés') : badge('close', 'non publiés') ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="publier"><input type="hidden" name="publier" value="<?= $ed['resultats_publies'] ? 0 : 1 ?>">
    <button class="btn <?= $ed['resultats_publies'] ? 'sec' : '' ?>"><?= $ed['resultats_publies'] ? 'Dépublier' : '🌐 Publier la présentation' ?></button></form>
  <?php if ($ed['resultats_publies']): ?>
    <a class="btn sec" href="<?= h(lien_resultats($ed)) ?>" target="_blank">Voir la page publique ↗</a>
    <form method="post" onsubmit="return confirm('Envoyer le lien des résultats à <?= $res_restants ?> participant(s) ?')"><?= csrf_field() ?>
      <input type="hidden" name="action" value="mail_resultats"><button class="btn" <?= $res_restants ? '' : 'disabled' ?>>✉ Envoyer le lien aux participants (<?= $res_restants ?>)</button></form>
    <input readonly value="<?= h(lien_resultats($ed)) ?>" onclick="this.select()" style="max-width:420px">
  <?php endif ?>
</div>
<?php endif ?>
<?php if ($nf = file_en_attente()): ?><p class="alerte info"><?= $nf ?> mail(s) groupé(s) en file d'envoi, fin estimée <?= h(fin_estimee_file()) ?> – <a href="mails.php">suivi</a>.</p><?php endif ?>
<div class="stats">
  <div class="stat"><b><?= $st['inscrits'] ?></b><span>inscrits</span></div>
  <div class="stat"><b><?= $st['attente'] ?></b><span>en liste d'attente</span></div>
  <div class="stat"><b><?= $st['en_cours'] ?></b><span>photos attendues</span></div>
  <div class="stat"><b style="color:<?= $st['retard'] ? 'var(--rouge)' : 'inherit' ?>"><?= $st['retard'] ?></b><span>en retard</span></div>
  <div class="stat"><b><?= $st['rendus'] ?></b><span>photos reçues</span></div>
</div>

<h2>Chaînes</h2>
<?php if (!$chaines): ?><p>Aucune chaîne : <a href="edition.php">paramétrez l'édition</a>.</p><?php endif ?>
<div class="grille-chaines">
<?php foreach ($chaines as $ch):
    $ms = maillons_chaine((int) $ch['id']);
    $cours = array_values(array_filter($ms, fn($m) => $m['statut'] === 'en_cours'))[0] ?? null;
    $rendus = array_filter($ms, fn($m) => $m['statut'] === 'rendu'); ?>
  <div class="ch">
    <h3><a href="chaine.php?id=<?= $ch['id'] ?>">Chaîne <?= h($ch['numero']) ?></a> <?= badge($ch['statut']) ?></h3>
    <div class="vignettes">
      <?php if ($ed['photo_base']): ?><img src="../img.php?base=<?= $eid ?>" title="Photo de départ" style="outline:2px solid var(--violet)"><?php endif ?>
      <?php foreach ($rendus as $m): ?><img src="../img.php?m=<?= $m['id'] ?>" title="<?= h($m['position'] . '. ' . $m['prenom'] . ' ' . $m['nom']) ?>" loading="lazy"><?php endforeach ?>
      <?php if ($cours): ?><div class="vide">?</div><?php endif ?>
    </div>
    <div class="encours">
      <?= count($rendus) ?> photo(s)
      <?php if ($cours): ?> · en attente de <strong><?= h($cours['prenom'] . ' ' . $cours['nom']) ?></strong>
        jusqu'au <?= fdate($cours['date_limite']) ?> <?= en_retard($cours) ? badge('retard') : '' ?>
      <?php elseif ($ch['statut'] === 'attente'): ?> · attend une inscription depuis le <?= fdate($ch['attente_depuis']) ?>
      <?php endif ?>
    </div>
  </div>
<?php endforeach ?>
</div>

<h2>Liste d'attente (<?= count($attente) ?>)</h2>
<?php if ($attente && $ed['statut'] === 'en_cours'): ?>
<form method="post" class="actions" onsubmit="return confirm('Envoyer maintenant à chaque inscrit en attente le nombre de personnes avant lui ?')"><?= csrf_field() ?>
  <input type="hidden" name="action" value="points"><button class="btn sec">✉ Envoyer le point « personnes avant toi » (<?= count($attente) ?>)</button>
  <span class="petit">Automatique tous les <?= (int) cfg('point_attente_jours', 14) ?> jours<?= cfg('point_attente_jours', 14) ? '' : ' (désactivé)' ?>. Estimation indicative.</span>
</form>
<?php endif ?>
<?php if ($attente): ?>
<table class="liste"><tr><th>#</th><th>Nom</th><th>Prénom</th><th>Club</th><th>Mail</th><th>Inscrit le</th><th>Tour estimé</th><th>Dernier point</th></tr>
<?php foreach ($attente as $p): ?>
  <tr><td><?= $p['rang'] ?></td><td><?= h($p['nom']) ?></td><td><?= h($p['prenom']) ?></td><td><?= h($p['club']) ?></td><td><?= h($p['mail']) ?></td><td><?= fdate($p['date_inscription']) ?></td>
    <td><?= h(texte_estimation($p['jours_estimes'])) ?></td><td><?= fdate($p['dernier_point'] ?? null) ?></td></tr>
<?php endforeach ?></table>
<?php else: ?><p class="petit">Personne en attente.</p><?php endif ?>
<?php pied();
