<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
$a = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $code = post('code');
    if (post('action') === 'file_suivant') {
        $n = traiter_file(1);
        flash($n ? 'Mail suivant de la file envoyé.' : 'Envoi impossible : voir l\'erreur dans la file.', $n ? 'ok' : 'ko');
    } elseif (post('action') === 'file_annuler') {
        $n = q("DELETE FROM file_envoi WHERE statut = 'attente'")->rowCount();
        flash("$n mail(s) retiré(s) de la file.");
    } elseif (post('action') === 'file_reessayer') {
        $n = q("UPDATE file_envoi SET statut = 'attente', tentatives = 0 WHERE statut = 'erreur'")->rowCount();
        flash("$n mail(s) remis en file.");
    } elseif (post('action') === 'enregistrer') {
        q('UPDATE modeles_mail SET sujet = ?, corps = ? WHERE code = ?', [post('sujet'), str_replace("\r\n", "\n", post('corps')), $code]);
        flash('Modèle enregistré.');
    } elseif (post('action') === 'test') {
        $mod = q1('SELECT * FROM modeles_mail WHERE code = ?', [$code]);
        $vars = ['prenom' => 'Patrick', 'nom' => 'TEST', 'club' => 'Club test', 'mail' => $a['mail'], 'edition' => edition_admin()['nom'] ?? 'Cadavre Exquis',
                 'delai' => cfg('delai_jours'), 'date_fin' => '', 'site' => url(), 'chaine' => '01',
                 'lien' => url('depot.php?t=exemple'), 'rang' => 4, 'devant' => 3,
                 'phrase_rang' => 'Il y a encore 3 personnes avant toi.', 'estimation' => 'dans 2 semaines environ', 'nb_photos' => 12, 'nb_chaines' => 6, 'lien_resultats' => url('resultats.php?k=exemple'), 'essai' => 2, 'max_essais' => 3, 'date_limite' => fdate(plus_jours(now(), (int) cfg('delai_jours'))), 'position' => 3];
        $ok = envoyer_mail($a['mail'] ?: cfg('mail.admin'), '[TEST] ' . remplir($mod['sujet'], $vars), remplir($mod['corps'], $vars));
        flash($ok ? 'Mail de test envoyé à ' . ($a['mail'] ?: cfg('mail.admin')) . '.' : 'Échec de l\'envoi : voir le journal ci-dessous.', $ok ? 'ok' : 'ko');
    }
    redirect('mails.php' . ($code ? '#' . $code : ''));
}

$mods = q("SELECT * FROM modeles_mail ORDER BY CASE code WHEN 'bienvenue' THEN 1 WHEN 'a_toi' THEN 2 WHEN 'relance' THEN 3 WHEN 'merci' THEN 4 WHEN 'file_attente' THEN 5 WHEN 'nouvelle_chance' THEN 5 WHEN 'retire' THEN 5 WHEN 'invitation' THEN 8 WHEN 'resultats' THEN 7 ELSE 6 END")->fetchAll();
$journal = q('SELECT * FROM journal_mails ORDER BY id DESC LIMIT 150')->fetchAll();
$file = q("SELECT * FROM file_envoi WHERE statut IN ('attente','erreur') ORDER BY statut DESC, id LIMIT 100")->fetchAll();
$nb_file = file_en_attente();
$nb_err = (int) qv("SELECT COUNT(*) FROM file_envoi WHERE statut = 'erreur'");
$cron = dernier_cron();
$cron_ok = $cron && strtotime($cron) > time() - 3 * 60 * max(5, (int) cfg('cron_minutes', 5));
$erreurs = (int) qv("SELECT COUNT(*) FROM journal_mails WHERE statut = 'erreur'");

entete('Mails', 'mails');
?>
<h1>Mails automatiques</h1>
<?php if (cfg('mail.mode') !== 'smtp'): ?><p class="alerte info">Mode « log » : aucun mail n'est réellement envoyé (voir config.php).</p><?php endif ?>
<p class="petit">Variables : {prenom} {nom} {club} {mail} {edition} {chaine} {position} {lien} {date_limite} {delai} {date_fin} {site} — point file d'attente : {rang} {devant} {phrase_rang} {estimation} {nb_photos} {nb_chaines} — résultats : {lien_resultats} — sans réponse : {essai} {max_essais}.
  Les liens sont rendus cliquables automatiquement. Expéditeur : <?= h(cfg('mail.from')) ?>.</p>

<h2 style="margin-top:0">File d'envoi (mails groupés)</h2>
<div class="panneau" style="margin-bottom:22px">
  <p style="margin-top:0">Invitations, points de file d'attente et mail de fin partent <strong><?= (int) cfg('cron_mails_par_passage', 1) ?> par passage</strong>
    de la tâche cron (toutes les <?= (int) cfg('cron_minutes', 5) ?> min). Les autres mails partent immédiatement.</p>
  <p><?= $cron_ok ? badge('rendu', 'tâche cron active') : badge('passe', $cron ? 'tâche cron arrêtée ?' : 'tâche cron jamais lancée') ?>
    <span class="petit">Dernier passage : <?= $cron ? fdate($cron, true) : 'aucun' ?>
    · <?= $nb_file ?> mail(s) en attente<?= $nb_file ? ', fin estimée ' . h(fin_estimee_file()) : '' ?><?= $nb_err ? " · $nb_err en erreur" : '' ?></span></p>
  <?php if (!$cron_ok && $nb_file): ?><p class="alerte ko">La tâche cron ne tourne pas : les mails en file ne partent pas. Vérifiez cPanel > Tâches Cron (toutes les 5 minutes).</p><?php endif ?>
  <?php if ($nb_file || $nb_err): ?>
  <div class="actions">
    <?php if ($nb_file): ?>
    <form method="post"><?= csrf_field() ?><button class="btn sec" name="action" value="file_suivant">Envoyer le suivant maintenant</button></form>
    <form method="post" onsubmit="return confirm('Retirer les <?= $nb_file ?> mail(s) en attente ? Ils ne seront pas envoyés.')"><?= csrf_field() ?><button class="btn danger" name="action" value="file_annuler">Annuler la file</button></form>
    <?php endif ?>
    <?php if ($nb_err): ?><form method="post"><?= csrf_field() ?><button class="btn sec" name="action" value="file_reessayer">Réessayer les erreurs</button></form><?php endif ?>
  </div>
  <details><summary class="petit" style="cursor:pointer">Voir la file (<?= count($file) ?>)</summary>
  <table class="liste" style="margin-top:8px"><tr><th>Ajouté le</th><th>Destinataire</th><th>Sujet</th><th>État</th></tr>
  <?php foreach ($file as $f): ?><tr><td><?= fdate($f['date_creation'], true) ?></td><td><?= h($f['destinataire']) ?></td><td><?= h($f['sujet']) ?></td>
    <td><?= $f['statut'] === 'erreur' ? badge('passe', 'erreur') . '<br><span class="petit">' . h($f['erreur']) . '</span>' : badge('attente', 'en attente') ?></td></tr><?php endforeach ?>
  </table></details>
  <?php endif ?>
</div>

<?php foreach ($mods as $m): ?>
<form method="post" class="form panneau" id="<?= h($m['code']) ?>" style="margin-bottom:16px"><?= csrf_field() ?>
  <input type="hidden" name="code" value="<?= h($m['code']) ?>">
  <h2 style="margin:0"><?= h($m['libelle']) ?></h2>
  <label>Sujet<input name="sujet" value="<?= h($m['sujet']) ?>"></label>
  <label>Message<textarea name="corps"><?= h($m['corps']) ?></textarea></label>
  <div class="actions"><button class="btn" name="action" value="enregistrer">Enregistrer</button>
    <button class="btn sec" name="action" value="test">M'envoyer un test</button></div>
</form>
<?php endforeach ?>

<h2>Journal des envois <?= $erreurs ? badge('passe', "$erreurs erreur(s)") : '' ?></h2>
<table class="liste"><tr><th>Date</th><th>Destinataire</th><th>Sujet</th><th>État</th></tr>
<?php foreach ($journal as $j): ?>
<tr><td><?= fdate($j['date_envoi'], true) ?></td><td><?= h($j['destinataire']) ?></td>
  <td><details><summary><?= h($j['sujet']) ?></summary><pre style="white-space:pre-wrap;font-size:13px"><?= h($j['corps']) ?></pre></details></td>
  <td><?= $j['statut'] === 'erreur' ? badge('passe', 'erreur') . '<br><span class="petit">' . h($j['erreur']) . '</span>' : badge($j['statut'] === 'envoye' ? 'rendu' : 'attente', $j['statut']) ?></td></tr>
<?php endforeach ?></table>
<?php pied();
