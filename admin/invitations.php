<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();

$ed = edition_admin();
if (!$ed) redirect('edition.php?nouvelle=1');
$eid = (int) $ed['id'];

/** Édition « Anciens participants » qui sert de carnet d'adresses pour les imports. */
function edition_archives(): int
{
    $id = qv("SELECT id FROM editions WHERE nom = 'Anciens participants' LIMIT 1");
    if ($id) return (int) $id;
    return insert('editions', ['nom' => 'Anciens participants', 'annee' => 0, 'statut' => 'terminee',
        'inscriptions_ouvertes' => 0, 'date_creation' => now()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        switch (post('action')) {
            case 'envoyer':
                $mails = array_values(array_unique(array_map('mb_strtolower', (array) ($_POST['mails'] ?? []))));
                $ok = $ko = 0;
                foreach ($mails as $mail) {
                    $p = q1('SELECT * FROM participants WHERE mail = ? AND ne_plus_inviter = 0 ORDER BY id DESC LIMIT 1', [$mail]);
                    if (!$p) continue;
                    if (qv('SELECT COUNT(*) FROM participants WHERE edition_id = ? AND mail = ?', [$eid, $mail])) continue;
                    $p['edition_id'] = $eid; // variables du mail = nouvelle édition
                    if (mettre_en_file('invitation', $mail, vars_mail($p), $p['prenom'] . ' ' . $p['nom'])) {
                        insert('invitations', ['edition_id' => $eid, 'mail' => $mail, 'date_envoi' => now()]);
                        $ok++;
                    } else {
                        $ko++;
                    }
                }
                flash("$ok invitation(s) mise(s) en file d'envoi : la tâche cron les envoie une par une, fin estimée " . fin_estimee_file() . '. Suivi dans Mails.');
                break;
            case 'stop':
                q('UPDATE participants SET ne_plus_inviter = 1 WHERE mail = ?', [mb_strtolower(post('mail'))]);
                flash(post('mail') . ' ne sera plus invité.');
                break;
            case 'reprendre':
                q('UPDATE participants SET ne_plus_inviter = 0 WHERE mail = ?', [mb_strtolower(post('mail'))]);
                flash(post('mail') . ' remis dans la liste.');
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
                $trouve = function (array $noms) use ($tete) { foreach ($tete as $i => $t) foreach ($noms as $n) if ($t === $n) return $i; foreach ($tete as $i => $t) foreach ($noms as $n) if (str_contains($t, $n)) return $i; return null; };
                $cn = $trouve(['nom']); $cp = $trouve(['prenom', 'prénom']); $cm = $trouve(['mail', 'email']); $cc = $trouve(['club']);
                if ($cn === null || $cm === null) throw new RuntimeException('Colonnes attendues : Nom, Prenom, Mail, Club (ligne d\'en-tête).');
                $arch = edition_archives();
                $n = 0;
                foreach ($lignes as $l) {
                    $c = str_getcsv($l, $sep);
                    $mail = trim($c[$cm] ?? '');
                    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) continue;
                    $r = inscrire($arch, $c[$cn] ?? '', $cp !== null ? ($c[$cp] ?? '') : '', $mail, $cc !== null ? ($c[$cc] ?? '') : '', false);
                    if (!$r['deja']) $n++;
                }
                flash("$n ancien(s) participant(s) importé(s).");
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'ko');
    }
    redirect('invitations.php');
}

// Anciens participants (toutes les autres éditions), une ligne par adresse mail, la plus récente
$tous = q('SELECT p.*, e.nom AS edition_nom FROM participants p JOIN editions e ON e.id = p.edition_id
           WHERE p.edition_id <> ? ORDER BY p.id DESC', [$eid])->fetchAll();
$deja_inscrits = array_flip(q('SELECT mail FROM participants WHERE edition_id = ?', [$eid])->fetchAll(PDO::FETCH_COLUMN));
$invites = [];
foreach (q('SELECT mail, MAX(date_envoi) d FROM invitations WHERE edition_id = ? GROUP BY mail', [$eid])->fetchAll() as $r) $invites[$r['mail']] = $r['d'];
$stop = array_flip(q('SELECT DISTINCT mail FROM participants WHERE ne_plus_inviter = 1')->fetchAll(PDO::FETCH_COLUMN));

$liste = [];
foreach ($tous as $p) {
    if (isset($liste[$p['mail']])) continue;
    $p['inscrit'] = isset($deja_inscrits[$p['mail']]);
    $p['invite_le'] = $invites[$p['mail']] ?? null;
    $p['stop'] = isset($stop[$p['mail']]);
    $liste[$p['mail']] = $p;
}
uasort($liste, fn($a, $b) => [$a['stop'], $a['inscrit'], $a['nom']] <=> [$b['stop'], $b['inscrit'], $b['nom']]);
$a_inviter = array_filter($liste, fn($p) => !$p['inscrit'] && !$p['stop'] && !$p['invite_le']);
$mod = q1("SELECT * FROM modeles_mail WHERE code = 'invitation'");

entete('Invitations', 'invitations');
?>
<h1>Inviter les anciens participants – <?= h($ed['nom']) ?></h1>
<?php if (!$ed['inscriptions_ouvertes'] || $ed['statut'] === 'terminee'): ?>
  <p class="alerte ko">Les inscriptions de cette édition sont fermées : ouvrez-les dans <a href="edition.php">Édition</a> avant d'inviter.</p>
<?php endif ?>

<div class="deux-col">
  <div class="panneau">
    <h2 style="margin-top:0">Aperçu du mail</h2>
    <p><strong><?= h(remplir($mod['sujet'] ?? '', ['edition' => $ed['nom']])) ?></strong></p>
    <pre style="white-space:pre-wrap;font:inherit;color:#cfcfdb;margin:0"><?= h(remplir($mod['corps'] ?? '', ['prenom' => 'Prénom', 'edition' => $ed['nom'], 'delai' => cfg('delai_jours'), 'site' => url()])) ?></pre>
    <p><a href="mails.php#invitation">Modifier ce modèle</a></p>
  </div>
  <form method="post" enctype="multipart/form-data" class="form panneau"><?= csrf_field() ?><input type="hidden" name="action" value="import">
    <h2 style="margin:0">Importer d'anciens participants</h2>
    <p class="petit" style="margin:0">CSV avec en-tête Nom ; Prenom ; Mail ; Club (par ex. l'export de l'ancien logiciel).
      Ils sont rangés dans « Anciens participants » et ne reçoivent aucun mail à l'import.</p>
    <input type="file" name="csv" accept=".csv,text/csv" required>
    <button class="btn sec">Importer</button>
    <p class="petit" style="margin:0">Les participants des éditions précédentes faites dans cette appli apparaissent automatiquement.</p>
  </form>
</div>

<h2>Destinataires (<?= count($liste) ?> adresses, <?= count($a_inviter) ?> à inviter)</h2>
<?php if (!$liste): ?><p class="petit">Aucun ancien participant : importez la liste ci-dessus.</p><?php else: ?>
<form method="post" id="fenv" onsubmit="const n=this.querySelectorAll('input[name=&quot;mails[]&quot;]:checked').length; if(!n){alert('Aucun destinataire coché.');return false;} if(!confirm('Envoyer l\'invitation à '+n+' personne(s) ?'))return false; this.querySelector('button').disabled=true; this.querySelector('button').textContent='Envoi en cours… (ne pas fermer la page)';">
  <?= csrf_field() ?><input type="hidden" name="action" value="envoyer">
  <div class="actions">
    <button class="btn">✉ Envoyer l'invitation aux personnes cochées</button>
    <span class="petit">Mises en file d'envoi : la tâche cron envoie <?= (int) cfg('cron_mails_par_passage', 1) ?> mail toutes les <?= (int) cfg('cron_minutes', 5) ?> min. Les personnes déjà invitées sont décochées.</span>
    <label class="case"><input type="checkbox" checked onchange="document.querySelectorAll('.cb:not(:disabled)').forEach(c=>c.checked=this.checked)"> Tout cocher</label>
  </div>
  <table class="liste">
    <tr><th></th><th>Nom</th><th>Prénom</th><th>Club</th><th>Mail</th><th>Dernière édition</th><th>État</th><th></th></tr>
    <?php foreach ($liste as $p): $dispo = !$p['inscrit'] && !$p['stop']; ?>
    <tr>
      <td><input type="checkbox" class="cb" name="mails[]" value="<?= h($p['mail']) ?>" <?= $dispo ? '' : 'disabled' ?> <?= $dispo && !$p['invite_le'] ? 'checked' : '' ?>></td>
      <td><strong><?= h($p['nom']) ?></strong></td><td><?= h($p['prenom']) ?></td><td><?= h($p['club']) ?></td><td><?= h($p['mail']) ?></td>
      <td><?= h($p['edition_nom']) ?></td>
      <td><?= $p['stop'] ? badge('passe', 'ne plus inviter') : ($p['inscrit'] ? badge('rendu', 'déjà inscrit') : ($p['invite_le'] ? badge('attente', 'invité le ' . fdate($p['invite_le']) . (qv("SELECT COUNT(*) FROM file_envoi WHERE statut = 'attente' AND code = 'invitation' AND destinataire = ?", [$p['mail']]) ? ' (en file)' : '')) : badge('close', 'pas encore invité'))) ?></td>
      <td><button class="btn petit sec" form="f-<?= md5($p['mail']) ?>"><?= $p['stop'] ? 'Réinviter' : 'Ne plus inviter' ?></button></td>
    </tr>
    <?php endforeach ?>
  </table>
</form>
<?php foreach ($liste as $p): ?>
  <form method="post" id="f-<?= md5($p['mail']) ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="<?= $p['stop'] ? 'reprendre' : 'stop' ?>"><input type="hidden" name="mail" value="<?= h($p['mail']) ?>"></form>
<?php endforeach ?>
<?php endif ?>
<?php pied();
