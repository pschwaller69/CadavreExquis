<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
require_admin();
$ed = edition_admin();
$eid = (int) $ed['id'];

if (get('csv')) {
    $rows = q("SELECT c.numero, m.position, p.nom, p.prenom, p.mail, p.club, p.statut, m.statut AS etat, m.date_envoi, m.date_limite, m.date_retour, m.titre, p.date_inscription
               FROM participants p LEFT JOIN maillons m ON m.participant_id = p.id LEFT JOIN chaines c ON c.id = m.chaine_id
               WHERE p.edition_id = ? ORDER BY c.numero, m.position, p.nom", [$eid])->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="cadavre-exquis-' . $eid . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Chaine', 'Position', 'Nom', 'Prenom', 'Mail', 'Club', 'Statut participant', 'Etat maillon', 'Date envoi', 'Date limite', 'Date retour', 'Titre', 'Inscription'], ';');
    foreach ($rows as $r) {
        foreach (['date_envoi', 'date_limite', 'date_retour', 'date_inscription'] as $k) $r[$k] = fdate($r[$k]);
        fputcsv($out, array_values($r), ';');
    }
    exit;
}

if (get('zip')) {
    @set_time_limit(300);
    $where = get('chaine') !== '' ? ' AND c.id = ' . (int) get('chaine') : '';
    $rows = q("SELECT m.*, c.numero, p.nom, p.prenom FROM maillons m JOIN chaines c ON c.id = m.chaine_id JOIN participants p ON p.id = m.participant_id
               WHERE c.edition_id = ? AND m.statut = 'rendu' $where ORDER BY c.numero, m.position", [$eid])->fetchAll();
    $tmp = tempnam(sys_get_temp_dir(), 'cx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    if ($b = chemin_photo($ed['photo_base'])) $zip->addFile($b, '00 Photo de base.jpg');
    foreach ($rows as $r) {
        $f = chemin_photo($r['photo']);
        if ($f) $zip->addFile($f, $r['numero'] . '/' . basename($f));
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="cadavre-exquis' . (get('chaine') !== '' ? '-chaine-' . ($rows[0]['numero'] ?? '') : '') . '.zip"');
    readfile($tmp);
    unlink($tmp);
    exit;
}

$chaines = q('SELECT * FROM chaines WHERE edition_id = ? ORDER BY numero', [$eid])->fetchAll();
entete('Export', 'index');
?>
<h1>Export – <?= h($ed['nom']) ?></h1>
<div class="panneau" style="max-width:700px">
  <p><a class="btn" href="?csv=1">⇩ Tableau des participants (CSV / Excel)</a></p>
  <p><a class="btn" href="?zip=1">⇩ Toutes les photos originales (zip)</a></p>
  <p>Par chaîne : <?php foreach ($chaines as $c): ?><a class="btn petit sec" href="?zip=1&amp;chaine=<?= $c['id'] ?>"><?= h($c['numero']) ?></a> <?php endforeach ?></p>
</div>
<?php pied();
