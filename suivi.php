<?php
/*
 * Page publique de suivi : uniquement des statistiques, aucun nom ni photo.
 * Sans mot de passe, installable sur téléphone (manifest-suivi.webmanifest).
 */
require __DIR__ . '/inc/bootstrap.php';
header('Cache-Control: no-store');

$editions = q("SELECT * FROM editions WHERE nom <> 'Anciens participants' AND statut <> 'preparation' ORDER BY id DESC")->fetchAll();
$ed = null;
if (get('e') !== '') foreach ($editions as $e) if ((int) $e['id'] === (int) get('e')) $ed = $e;
$ed ??= $editions[0] ?? q1("SELECT * FROM editions WHERE nom <> 'Anciens participants' ORDER BY id DESC LIMIT 1");

$stats = $chaines = $clubs = [];
$semaines = [];
if ($ed) {
    $eid = (int) $ed['id'];
    $rendus = q("SELECT m.date_envoi, m.date_retour, c.numero FROM maillons m JOIN chaines c ON c.id = m.chaine_id
                 WHERE c.edition_id = ? AND m.statut = 'rendu' ORDER BY m.date_retour", [$eid])->fetchAll();
    $debut = qv('SELECT MIN(m.date_envoi) FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE c.edition_id = ?', [$eid]);
    $duree = 0;
    foreach ($rendus as $r) $duree += max(0, strtotime($r['date_retour']) - strtotime($r['date_envoi']));
    $stats = [
        'photos' => count($rendus),
        'inscrits' => (int) qv("SELECT COUNT(*) FROM participants WHERE edition_id = ? AND statut <> 'abandon'", [$eid]),
        'attente' => (int) qv("SELECT COUNT(*) FROM participants WHERE edition_id = ? AND statut = 'attente'", [$eid]),
        'en_cours' => (int) qv("SELECT COUNT(*) FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE c.edition_id = ? AND m.statut = 'en_cours'", [$eid]),
        'semaine' => count(array_filter($rendus, fn($r) => $r['date_retour'] >= plus_jours(now(), -7))),
        'jours' => $debut ? (int) floor((time() - strtotime($debut)) / 86400) : 0,
        'debut' => $debut,
        'moyenne' => $rendus ? round($duree / count($rendus) / 86400, 1) : null,
    ];

    $max = 1;
    foreach (q('SELECT * FROM chaines WHERE edition_id = ? ORDER BY numero', [$eid])->fetchAll() as $ch) {
        $ms = q('SELECT statut, date_envoi, date_limite, date_retour FROM maillons WHERE chaine_id = ? ORDER BY position', [$ch['id']])->fetchAll();
        $r = array_values(array_filter($ms, fn($m) => $m['statut'] === 'rendu'));
        $cours = array_values(array_filter($ms, fn($m) => $m['statut'] === 'en_cours'))[0] ?? null;
        $der = $r ? end($r)['date_retour'] : null;
        $chaines[] = ['numero' => $ch['numero'], 'statut' => $ch['statut'], 'photos' => count($r), 'cours' => $cours, 'derniere' => $der];
        $max = max($max, count($r));
    }
    if ((int) $ed['max_maillons'] > 0) $max = (int) $ed['max_maillons'];

    $clubs = q("SELECT club, COUNT(*) n FROM participants WHERE edition_id = ? AND statut <> 'abandon' GROUP BY club ORDER BY n DESC, club", [$eid])->fetchAll();

    // Photos déposées par semaine (8 dernières semaines)
    for ($i = 7; $i >= 0; $i--) {
        $lundi = strtotime('monday this week -' . $i . ' weeks');
        $semaines[date('Y-m-d', $lundi)] = 0;
    }
    foreach ($rendus as $r) {
        $k = date('Y-m-d', strtotime('monday this week', strtotime($r['date_retour'])));
        if (isset($semaines[$k])) $semaines[$k]++;
    }
}

function il_y_a(?string $d): string
{
    if (!$d) return '';
    $j = (int) floor((time() - strtotime($d)) / 86400);
    return $j <= 0 ? "aujourd'hui" : ($j === 1 ? 'hier' : "il y a $j jours");
}
$statuts = ['active' => 'en cours', 'attente' => 'attend un inscrit', 'close' => 'terminée'];
$etat_ed = ['preparation' => 'en préparation', 'en_cours' => 'en cours', 'terminee' => 'terminé'];
$max_sem = max(1, ...array_values($semaines ?: [0]));
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Suivi – Cadavre Exquis Photographique</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>"><?= pwa_head('manifest-suivi.webmanifest') ?>
<style>
  .suivi .chiffres { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin: 18px 0; }
  .suivi .chiffre { background: var(--fond); border: 1px solid var(--ligne); border-radius: 8px; padding: 12px; }
  .suivi .chiffre b { display: block; font-size: 26px; line-height: 1.2; }
  .suivi .chiffre span { color: var(--doux); font-size: 13px; }
  .suivi .chaine { display: grid; grid-template-columns: 74px 1fr; gap: 4px 14px; align-items: center; padding: 12px 0; border-top: 1px solid var(--ligne); }
  .suivi .chaine .num { font-size: 22px; font-weight: 700; grid-row: span 2; }
  .suivi .barre-prog { height: 12px; background: var(--fond); border: 1px solid var(--ligne); border-radius: 99px; overflow: hidden; display: flex; }
  .suivi .barre-prog i { background: linear-gradient(90deg, #6d28d9, var(--violet2)); display: block; }
  .suivi .barre-prog i.cours { background: repeating-linear-gradient(45deg, #3b2a63 0 6px, #2a1f47 6px 12px); }
  .suivi .info { color: var(--doux); font-size: 13.5px; display: flex; flex-wrap: wrap; gap: 4px 12px; }
  .suivi .info strong { color: var(--texte); }
  .suivi .semaines { display: flex; align-items: flex-end; gap: 6px; height: 110px; margin-top: 8px; }
  .suivi .semaines div { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 3px; font-size: 11px; color: var(--doux); }
  .suivi .semaines div i { width: 100%; background: var(--violet); border-radius: 3px 3px 0 0; min-height: 2px; }
  .suivi .semaines div b { color: var(--texte); font-weight: 600; }
  .suivi .clubs { display: flex; flex-wrap: wrap; gap: 6px; }
  .suivi .clubs span { background: var(--fond3); border: 1px solid var(--ligne); border-radius: 99px; padding: 3px 10px; font-size: 13px; }
  .suivi .maj { color: var(--doux); font-size: 12px; text-align: center; margin-top: 22px; }
  .suivi h2 { font-size: 16px; color: var(--violet2); margin: 26px 0 6px; }
</style>
<body class="public"><main class="carte large suivi">
<h1>Cadavre Exquis <span>Photographique</span></h1>

<?php if (!$ed): ?>
  <p>Aucune édition pour le moment.</p>
<?php else: ?>
  <?php if (count($editions) > 1): ?>
    <p class="petit">Édition : <?php foreach ($editions as $e): ?>
      <?= (int) $e['id'] === (int) $ed['id'] ? '<strong>' . h($e['nom']) . '</strong>' : '<a href="?e=' . (int) $e['id'] . '">' . h($e['nom']) . '</a>' ?>&nbsp; <?php endforeach ?></p>
  <?php endif ?>
  <h2 style="margin-top:6px;font-size:19px;color:var(--texte)"><?= h($ed['nom']) ?> – <?= h($etat_ed[$ed['statut']] ?? $ed['statut']) ?></h2>
  <?php if ($ed['statut'] === 'preparation'): ?>
    <p>Le jeu n'a pas encore commencé.<?= $ed['inscriptions_ouvertes'] ? ' Les inscriptions sont ouvertes : <a href="./">s\'inscrire</a>.' : '' ?></p>
  <?php endif ?>

  <div class="chiffres">
    <div class="chiffre"><b><?= $stats['photos'] ?></b><span>photos réalisées</span></div>
    <div class="chiffre"><b><?= $stats['en_cours'] ?></b><span>photos en préparation</span></div>
    <div class="chiffre"><b><?= $stats['inscrits'] ?></b><span>participants inscrits</span></div>
    <div class="chiffre"><b><?= $stats['attente'] ?></b><span>en attente de leur tour</span></div>
    <div class="chiffre"><b><?= count($clubs) ?></b><span>clubs représentés</span></div>
    <?php if ($stats['debut']): ?><div class="chiffre"><b><?= $stats['jours'] ?> j</b><span>depuis le départ (<?= fdate($stats['debut']) ?>)</span></div><?php endif ?>
    <?php if ($stats['moyenne'] !== null): ?><div class="chiffre"><b><?= str_replace('.', ',', (string) $stats['moyenne']) ?> j</b><span>en moyenne par photo</span></div><?php endif ?>
    <div class="chiffre"><b><?= $stats['semaine'] ?></b><span>photos ces 7 derniers jours</span></div>
  </div>

  <h2>Les chaînes</h2>
  <?php foreach ($chaines as $c): ?>
    <div class="chaine">
      <div class="num"><?= h($c['numero']) ?></div>
      <div class="barre-prog" title="<?= $c['photos'] ?> photo(s)">
        <i style="width:<?= round(100 * $c['photos'] / ($max + 1), 1) ?>%"></i>
        <?php if ($c['cours']): ?><i class="cours" style="width:<?= round(100 / ($max + 1), 1) ?>%"></i><?php endif ?>
      </div>
      <div class="info">
        <span><strong><?= $c['photos'] ?></strong> photo<?= $c['photos'] > 1 ? 's' : '' ?><?= (int) $ed['max_maillons'] > 0 ? ' / ' . (int) $ed['max_maillons'] : '' ?></span>
        <span><?= h($statuts[$c['statut']] ?? $c['statut']) ?></span>
        <?php if ($c['cours']): ?><span>photo attendue avant le <?= fdate($c['cours']['date_limite']) ?></span><?php endif ?>
        <?php if ($c['derniere']): ?><span>dernière photo <?= il_y_a($c['derniere']) ?></span><?php endif ?>
      </div>
    </div>
  <?php endforeach ?>

  <?php if ($stats['photos']): ?>
  <h2>Photos déposées par semaine</h2>
  <div class="semaines">
    <?php foreach ($semaines as $lundi => $n): ?>
      <div title="Semaine du <?= fdate($lundi) ?>"><b><?= $n ?: '' ?></b><i style="height:<?= round(85 * $n / $max_sem) ?>%"></i><?= date('d/m', strtotime($lundi)) ?></div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <?php if ($clubs): ?>
  <h2>Clubs participants</h2>
  <div class="clubs"><?php foreach ($clubs as $c): ?><span><?= h($c['club']) ?> · <?= (int) $c['n'] ?></span><?php endforeach ?></div>
  <?php endif ?>

  <?php if ($ed['inscriptions_ouvertes'] && $ed['statut'] !== 'terminee'): ?>
    <p style="text-align:center;margin-top:24px"><a class="btn" href="./">Participer</a></p>
  <?php endif ?>
  <p class="maj">Mis à jour le <?= date('d/m/Y à H:i') ?> · les photos restent secrètes jusqu'à la projection</p>
<?php endif ?>
</main>
<script>
  // Rafraîchit les chiffres quand on revient sur la page (appli installée)
  let t = Date.now();
  document.addEventListener('visibilitychange', () => { if (!document.hidden && Date.now() - t > 120000) location.reload(); });
</script>
</body></html>
