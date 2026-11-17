<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/layout.php';
$a = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $code = post('code');
    if (post('action') === 'enregistrer') {
        q('UPDATE modeles_mail SET sujet = ?, corps = ? WHERE code = ?', [post('sujet'), str_replace("\r\n", "\n", post('corps')), $code]);
        flash('Modèle enregistré.');
    } elseif (post('action') === 'test') {
        $mod = q1('SELECT * FROM modeles_mail WHERE code = ?', [$code]);
        $vars = ['prenom' => 'Patrick', 'nom' => 'TEST', 'club' => 'Club test', 'mail' => $a['mail'], 'edition' => edition_admin()['nom'] ?? 'Cadavre Exquis',
                 'delai' => cfg('delai_jours'), 'date_fin' => '', 'site' => url(), 'chaine' => '01',
                 'lien' => url('depot.php?t=exemple'), 'date_limite' => fdate(plus_jours(now(), (int) cfg('delai_jours'))), 'position' => 3];
        $ok = envoyer_mail($a['mail'] ?: cfg('mail.admin'), '[TEST] ' . remplir($mod['sujet'], $vars), remplir($mod['corps'], $vars));
        flash($ok ? 'Mail de test envoyé à ' . ($a['mail'] ?: cfg('mail.admin')) . '.' : 'Échec de l\'envoi : voir le journal ci-dessous.', $ok ? 'ok' : 'ko');
    }
    redirect('mails.php' . ($code ? '#' . $code : ''));
}

$mods = q("SELECT * FROM modeles_mail ORDER BY CASE code WHEN 'bienvenue' THEN 1 WHEN 'a_toi' THEN 2 WHEN 'relance' THEN 3 WHEN 'merci' THEN 4 WHEN 'invitation' THEN 6 ELSE 5 END")->fetchAll();
$journal = q('SELECT * FROM journal_mails ORDER BY id DESC LIMIT 150')->fetchAll();
$erreurs = (int) qv("SELECT COUNT(*) FROM journal_mails WHERE statut = 'erreur'");

entete('Mails', 'mails');
?>
<h1>Mails automatiques</h1>
<?php if (cfg('mail.mode') !== 'smtp'): ?><p class="alerte info">Mode « log » : aucun mail n'est réellement envoyé (voir config.php).</p><?php endif ?>
<p class="petit">Variables : {prenom} {nom} {club} {mail} {edition} {chaine} {position} {lien} {date_limite} {delai} {date_fin} {site}.
  Les liens sont rendus cliquables automatiquement. Expéditeur : <?= h(cfg('mail.from')) ?>.</p>

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
