<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $club = post('id') ? q1('SELECT * FROM clubs WHERE id = ?', [(int) post('id')]) : null;
        switch (post('action')) {
            case 'ajouter':
                if (club_propre(post('nom')) === '') throw new RuntimeException('Nom du club vide.');
                $nom = ajouter_club(post('nom'));
                q('UPDATE clubs SET actif = 1 WHERE nom = ?', [$nom]);
                flash("Club « $nom » dans la liste.");
                break;
            case 'renommer': // renommer, ou fusionner si le nouveau nom existe déjà
                $nouveau = club_propre(post('nom'));
                if (!$club || $nouveau === '') throw new RuntimeException('Nom invalide.');
                $cible = null;
                foreach (q('SELECT * FROM clubs WHERE id <> ?', [$club['id']])->fetchAll() as $c) {
                    if (mb_strtolower($c['nom']) === mb_strtolower($nouveau)) $cible = $c;
                }
                if ($cible) {
                    $n = q('UPDATE participants SET club = ? WHERE club = ?', [$cible['nom'], $club['nom']])->rowCount();
                    q('DELETE FROM clubs WHERE id = ?', [$club['id']]);
                    flash("« {$club['nom']} » fusionné dans « {$cible['nom']} » ($n participant(s) mis à jour).");
                } else {
                    $n = q('UPDATE participants SET club = ? WHERE club = ?', [$nouveau, $club['nom']])->rowCount();
                    update('clubs', (int) $club['id'], ['nom' => $nouveau]);
                    flash("Club renommé en « $nouveau » ($n participant(s) mis à jour).");
                }
                break;
            case 'basculer':
                update('clubs', (int) $club['id'], ['actif' => $club['actif'] ? 0 : 1]);
                flash($club['actif'] ? "« {$club['nom']} » masqué du formulaire d'inscription." : "« {$club['nom']} » de nouveau proposé.");
                break;
            case 'supprimer':
                if ((int) qv('SELECT COUNT(*) FROM participants WHERE club = ?', [$club['nom']])) throw new RuntimeException('Ce club a des participants : masquez-le ou fusionnez-le plutôt.');
                q('DELETE FROM clubs WHERE id = ?', [$club['id']]);
                flash("Club « {$club['nom']} » supprimé.");
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'ko');
    }
    redirect('clubs.php');
}

$clubs = q('SELECT c.*, (SELECT COUNT(*) FROM participants p WHERE p.club = c.nom) AS nb FROM clubs c ORDER BY c.actif DESC, c.nom')->fetchAll();
entete('Clubs', 'clubs');
?>
<h1>Clubs photo (<?= count($clubs) ?>)</h1>
<p class="petit">Liste proposée dans le formulaire d'inscription. Quand un participant choisit « Autre club… », son club est ajouté automatiquement ici.
  Pour corriger une faute ou regrouper deux écritures du même club, renommez-le avec le nom de l'autre : les participants sont mis à jour.</p>

<form method="post" class="actions panneau"><?= csrf_field() ?><input type="hidden" name="action" value="ajouter">
  <input name="nom" placeholder="Nom du club (Ville)" required style="max-width:420px">
  <button class="btn">+ Ajouter</button>
</form>

<input class="filtre" placeholder="Filtrer…" oninput="const v=this.value.toLowerCase();document.querySelectorAll('#tc tr[data-t]').forEach(r=>r.hidden=!r.dataset.t.includes(v))">
<table class="liste" id="tc">
  <tr><th>Club</th><th>Participants</th><th>Formulaire</th><th>Renommer / fusionner</th><th></th></tr>
  <?php foreach ($clubs as $c): ?>
  <tr data-t="<?= h(mb_strtolower($c['nom'])) ?>">
    <td><strong><?= h($c['nom']) ?></strong></td>
    <td><?= (int) $c['nb'] ?></td>
    <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="basculer"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <button class="btn petit sec"><?= $c['actif'] ? '✔ proposé' : '✕ masqué' ?></button></form></td>
    <td><form method="post" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="action" value="renommer"><input type="hidden" name="id" value="<?= $c['id'] ?>">
      <input name="nom" value="<?= h($c['nom']) ?>" list="lc" style="padding:4px 8px"><button class="btn petit sec">OK</button></form></td>
    <td><?php if (!$c['nb']): ?><form method="post" onsubmit="return confirm('Supprimer ce club ?')"><?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn petit danger">✕</button></form><?php endif ?></td>
  </tr>
  <?php endforeach ?>
</table>
<datalist id="lc"><?php foreach ($clubs as $c): ?><option value="<?= h($c['nom']) ?>"><?php endforeach ?></datalist>
<?php pied();
