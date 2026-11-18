<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

$ed = edition_admin();
if (!$ed) redirect('edition.php?nouvelle=1');
$eid = (int) $ed['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $p = post('id') ? participant((int) post('id')) : null;
    if ($p && $p['edition_id'] != $eid) $p = null;
    try {
        switch (post('action')) {
            case 'ajouter':
                if (!filter_var(post('mail'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Mail invalide.');
                $r = inscrire($eid, post('nom'), post('prenom'), post('mail'), post('club'), (bool) post('bienvenue'));
                flash($r['deja'] ? 'Ce mail est déjà inscrit.' : 'Participant ajouté' . (!empty($r['maillon']) ? ' et placé directement dans une chaîne.' : ' en liste d\'attente.'), $r['deja'] ? 'info' : 'ok');
                break;
            case 'modifier':
                update('participants', (int) $p['id'], ['nom' => nom_propre(post('nom')), 'prenom' => prenom_propre(post('prenom')),
                    'mail' => mb_strtolower(post('mail')), 'club' => club_inscription(post('club')), 'remarque' => post('remarque') ?: null]);
                flash('Participant modifié.');
                break;
            case 'supprimer':
                if ($p['statut'] !== 'attente' && $p['statut'] !== 'abandon') throw new RuntimeException('Impossible : ce participant fait partie d\'une chaîne.');
                if (qv('SELECT COUNT(*) FROM maillons WHERE participant_id = ?', [$p['id']])) throw new RuntimeException('Impossible : ce participant a un historique dans une chaîne.');
                q('DELETE FROM participants WHERE id = ?', [$p['id']]);
                flash('Participant supprimé.');
                break;
            case 'bienvenue':
                envoyer_modele('bienvenue', $p['mail'], vars_mail($p), $p['prenom'] . ' ' . $p['nom']);
                update('participants', (int) $p['id'], ['mail_bienvenue' => 1]);
                flash('Mail de bienvenue envoyé.');
                break;
            case 'remettre':
                update('participants', (int) $p['id'], ['statut' => 'attente', 'date_attente' => now(), 'sans_reponse' => 0]);
                placer_si_chaine_en_attente(participant((int) $p['id']));
                flash('Remis en fin de liste d\'attente.');
                break;
            case 'renommer_club':
                $n = q('UPDATE participants SET club = ? WHERE club = ?', [club_propre(post('nouveau')), post('ancien')])->rowCount();
                flash("$n participant(s) mis à jour.");
                break;
            case 'import':
                $f = $_FILES['csv']['tmp_name'] ?? '';
                if (!$f || !is_uploaded_file($f)) throw new RuntimeException('Choisissez un fichier CSV.');
                $txt = file_get_contents($f);
                if (!mb_check_encoding($txt, 'UTF-8')) $txt = mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
                $txt = preg_replace('/^\xEF\xBB\xBF/', '', $txt);
                $lignes = preg_split('/\r\n|\r|\n/', trim($txt));
                $sep = substr_count($lignes[0], ';') >= substr_count($lignes[0], ',') ? ';' : ',';
                $tete = array_map(fn($x) => mb_strtolower(trim($x, " \"'")), str_getcsv(array_shift($lignes), $sep));
                $col = function (array $noms) use ($tete) { foreach ($tete as $i => $t) foreach ($noms as $n) if (str_contains($t, $n)) return $i; return null; };
                $cn = $col(['nom']); $cp = $col(['prenom', 'prénom']); $cm = $col(['mail']); $cc = $col(['club']);
                // "nom" peut matcher "prenom" : on prend la colonne exacte si elle existe
                $exact = array_search('nom', $tete, true); if ($exact !== false) $cn = $exact;
                if ($cn === null || $cm === null) throw new RuntimeException('Colonnes attendues : Nom, Prenom, Mail, Club (ligne d\'en-tête).');
                $ok = $deja = 0;
                foreach ($lignes as $l) {
                    $c = str_getcsv($l, $sep);
                    $mail = trim($c[$cm] ?? '');
                    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) continue;
                    $r = inscrire($eid, $c[$cn] ?? '', $cp !== null ? ($c[$cp] ?? '') : '', $mail, $cc !== null ? ($c[$cc] ?? '') : '', (bool) post('bienvenue'));
                    $r['deja'] ? $deja++ : $ok++;
                }
                flash("Import : $ok ajouté(s), $deja déjà présent(s).");
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'ko');
    }
    redirect('participants.php');
}

$edit = get('edit') ? participant((int) get('edit')) : null;
$liste = q("SELECT p.*, m.position, m.statut AS m_statut, c.numero, c.id AS chaine_id
            FROM participants p LEFT JOIN maillons m ON m.id = (SELECT MAX(id) FROM maillons WHERE participant_id = p.id) LEFT JOIN chaines c ON c.id = m.chaine_id
            WHERE p.edition_id = ? ORDER BY p.nom, p.prenom", [$eid])->fetchAll();
$clubs = q('SELECT club, COUNT(*) n FROM participants WHERE edition_id = ? GROUP BY club ORDER BY club', [$eid])->fetchAll();
$tous_clubs = clubs_actifs();

entete('Participants', 'participants');
?>
<datalist id="clubs"><?php foreach ($tous_clubs as $c): ?><option value="<?= h($c) ?>"><?php endforeach ?></datalist>
<h1>Participants – <?= h($ed['nom']) ?> (<?= count($liste) ?>)</h1>

<div class="deux-col">
<?php if ($edit): ?>
  <form method="post" class="form panneau"><?= csrf_field() ?><input type="hidden" name="action" value="modifier"><input type="hidden" name="id" value="<?= $edit['id'] ?>">
    <h2 style="margin:0">Modifier</h2>
    <div class="ligne"><label>Nom<input name="nom" value="<?= h($edit['nom']) ?>"></label><label>Prénom<input name="prenom" value="<?= h($edit['prenom']) ?>"></label></div>
    <label>Mail<input name="mail" type="email" value="<?= h($edit['mail']) ?>"></label>
    <label>Club<input name="club" list="clubs" value="<?= h($edit['club']) ?>"></label>
    <label>Remarque<input name="remarque" value="<?= h($edit['remarque']) ?>"></label>
    <div class="actions"><button class="btn">Enregistrer</button><a class="btn sec" href="participants.php">Annuler</a></div>
  </form>
<?php else: ?>
  <form method="post" class="form panneau"><?= csrf_field() ?><input type="hidden" name="action" value="ajouter">
    <h2 style="margin:0">Ajouter un participant</h2>
    <div class="ligne"><label>Nom<input name="nom" required></label><label>Prénom<input name="prenom" required></label></div>
    <label>Mail<input name="mail" type="email" required></label>
    <label>Club<input name="club" list="clubs"></label>
    <label class="case"><input type="checkbox" name="bienvenue" value="1" checked> Envoyer le mail de bienvenue</label>
    <button class="btn">Ajouter</button>
  </form>
<?php endif ?>
  <div class="panneau">
    <form method="post" enctype="multipart/form-data" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="import">
      <h2 style="margin:0">Importer (CSV)</h2>
      <p class="petit" style="margin:0">Colonnes : Nom ; Prenom ; Mail ; Club (en-tête obligatoire, séparateur ; ou ,). Les doublons de mail sont ignorés.</p>
      <input type="file" name="csv" accept=".csv,text/csv" required>
      <label class="case"><input type="checkbox" name="bienvenue" value="1"> Envoyer le mail de bienvenue</label>
      <button class="btn sec">Importer</button>
    </form>
    <p class="petit" style="margin-top:18px">Liste, ajout et harmonisation des clubs : page <a href="clubs.php">Clubs</a>.</p>
  </div>
</div>

<h2>Liste</h2>
<input class="filtre" placeholder="Filtrer…" oninput="const v=this.value.toLowerCase();document.querySelectorAll('#tp tr[data-t]').forEach(r=>r.hidden=!r.dataset.t.includes(v))">
<table class="liste" id="tp">
<tr><th>Nom</th><th>Prénom</th><th>Club</th><th>Mail</th><th>Statut</th><th>Chaîne</th><th>Inscrit le</th><th></th></tr>
<?php foreach ($liste as $p): ?>
<tr data-t="<?= h(mb_strtolower($p['nom'] . ' ' . $p['prenom'] . ' ' . $p['club'] . ' ' . $p['mail'])) ?>">
  <td><strong><?= h($p['nom']) ?></strong></td><td><?= h($p['prenom']) ?></td><td><?= h($p['club']) ?></td>
  <td><?= h($p['mail']) ?><?= $p['remarque'] ? '<br><span class="petit">' . h($p['remarque']) . '</span>' : '' ?></td>
  <td><?= $p['m_statut'] ? badge($p['m_statut']) : badge($p['statut']) ?><?= (int) ($p['sans_reponse'] ?? 0) ? ' <span class="petit">' . (int) $p['sans_reponse'] . ' sans réponse</span>' : '' ?></td>
  <td><?= $p['numero'] ? '<a href="chaine.php?id=' . $p['chaine_id'] . '">' . h($p['numero']) . '</a> · n°' . $p['position'] : '' ?></td>
  <td><?= fdate($p['date_inscription']) ?></td>
  <td>
    <a class="btn petit sec" href="?edit=<?= $p['id'] ?>">Modifier</a>
    <?php if ($p['statut'] === 'attente'): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="bienvenue"><button class="btn petit sec"><?= $p['mail_bienvenue'] ? 'Renvoyer bienvenue' : 'Mail bienvenue' ?></button></form>
      <form method="post" onsubmit="return confirm('Supprimer ce participant ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="supprimer"><button class="btn petit danger">✕</button></form>
    <?php elseif ($p['statut'] === 'abandon'): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="remettre"><button class="btn petit sec">Remettre en attente</button></form>
    <?php endif ?>
  </td>
</tr>
<?php endforeach ?>
</table>
<?php pied();
