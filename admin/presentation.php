<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

$ed = edition_admin();
$chaines = get('chaine') !== ''
    ? q('SELECT * FROM chaines WHERE id = ? AND edition_id = ?', [(int) get('chaine'), $ed['id']])->fetchAll()
    : q('SELECT * FROM chaines WHERE edition_id = ? ORDER BY numero', [$ed['id']])->fetchAll();

$pages = [];
foreach ($chaines as $ch) {
    $prec = '../img.php?base=' . $ed['id'];
    $rendus = array_values(array_filter(maillons_chaine((int) $ch['id']), fn($m) => $m['statut'] === 'rendu'));
    if (!$rendus) continue;
    $pages[] = ['type' => 'titre', 'chaine' => $ch, 'img' => $prec, 'nb' => count($rendus)];
    foreach ($rendus as $m) {
        $img = '../img.php?m=' . $m['id'];
        $pages[] = ['type' => 'photo', 'chaine' => $ch, 'prec' => $prec, 'img' => $img, 'm' => $m];
        $prec = $img;
    }
    $pages[] = ['type' => 'mosaique', 'chaine' => $ch, 'imgs' => array_merge(['../img.php?base=' . $ed['id']], array_map(fn($m) => '../img.php?m=' . $m['id'], $rendus))];
}
$n = count($pages);
?><!doctype html><html lang="fr"><meta charset="utf-8">
<?= pwa_head() ?>
<title>Présentation – <?= h($ed['nom']) ?></title>
<style>
  @page { size: A4 landscape; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; background: #000; color: #fff; font-family: "Segoe UI", system-ui, sans-serif; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page { position: relative; width: 297mm; height: 210mm; overflow: hidden; background: #000; page-break-after: always; break-after: page; margin: 0 auto 10px; }
  .page .titre { position: absolute; top: 4.5%; left: 3.5%; font-weight: 600; font-size: 2.1vmin; }
  .page .num { position: absolute; top: 3%; right: 3%; font-size: 1.4vmin; color: #aaa; }
  .page .prec { position: absolute; left: 3%; top: 13%; width: 27%; height: 38%; display: flex; align-items: center; justify-content: center; }
  .page .cour { position: absolute; left: 34%; top: 11%; width: 63%; height: 76%; display: flex; align-items: center; justify-content: center; }
  .page img { max-width: 100%; max-height: 100%; border: 2px solid #fff; display: block; }
  .page .pied { position: absolute; bottom: 4%; left: 3.5%; right: 3%; display: grid; grid-template-columns: 10% 1fr 1fr 1fr 1.6fr; font-size: 1.6vmin; }
  .page .pied i { color: #ccc; }
  .page.titre-ch { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4%; }
  .page.titre-ch h1 { font-size: 7vmin; margin: 0; font-weight: 300; letter-spacing: 2px; }
  .page.titre-ch h1 b { color: #a774ff; font-weight: 700; }
  .page.titre-ch img { max-height: 50%; max-width: 60%; }
  .page.titre-ch p { color: #aaa; font-size: 2vmin; margin: 0; }
  .page.mosa .grille { position: absolute; inset: 12% 3% 6%; display: flex; flex-wrap: wrap; gap: 1.2%; align-content: center; justify-content: center; }
  .page.mosa .grille img { height: 30%; max-width: 19%; object-fit: contain; border-width: 1px; }
  .barre { position: fixed; top: 10px; right: 10px; z-index: 9; display: flex; gap: 6px; }
  .barre button, .barre a { background: #8b45ff; color: #fff; border: 0; border-radius: 6px; padding: 8px 12px; font: 14px sans-serif; cursor: pointer; text-decoration: none; }
  .barre label { background: #222; padding: 8px 10px; border-radius: 6px; font: 14px sans-serif; }
  body.sans-noms .pied .nom { visibility: hidden; }
  /* écran : pages mises à l'échelle */
  @media screen { body:not(.diapo) { padding: 60px 0 20px; } .page { width: min(96vw, 136vh); height: calc(min(96vw, 136vh) * 0.707); } }
  /* diaporama */
  body.diapo .barre { display: none; }
  body.diapo .page { display: none; position: fixed; inset: 0; width: 100vw; height: 100vh; margin: 0; }
  body.diapo .page.on { display: block; }
  body.diapo .page.titre-ch.on { display: flex; }
  @media print { .barre { display: none; } .page { width: 297mm; height: 210mm; margin: 0; }
    .page .titre { font-size: 16pt } .page .num { font-size: 9pt } .page .pied { font-size: 11pt } .page.titre-ch h1 { font-size: 48pt } .page.titre-ch p { font-size: 14pt } }
</style>
<body>
<div class="barre">
  <a href="index.php">← Retour</a>
  <label><input type="checkbox" onchange="document.body.classList.toggle('sans-noms', !this.checked)" checked> Noms</label>
  <button onclick="diapo()">▶ Diaporama</button>
  <button onclick="print()">🖶 Imprimer / PDF</button>
</div>
<?php if (!$pages): ?><p style="padding:80px 20px">Aucune photo rendue pour l'instant.</p><?php endif ?>
<?php foreach ($pages as $i => $pg): $num = '<div class="num">' . ($i + 1) . '/' . $n . '</div>'; ?>
  <?php if ($pg['type'] === 'titre'): ?>
    <section class="page titre-ch"><?= $num ?><h1>Chaîne <b><?= h($pg['chaine']['numero']) ?></b></h1><img src="<?= h($pg['img']) ?>"><p>Photo de départ · <?= $pg['nb'] ?> photographes</p></section>
  <?php elseif ($pg['type'] === 'photo'): $m = $pg['m']; ?>
    <section class="page"><div class="titre"><?= h($ed['nom'] ?: 'Cadavre Exquis') ?></div><?= $num ?>
      <div class="prec"><img src="<?= h($pg['prec']) ?>" loading="lazy"></div>
      <div class="cour"><img src="<?= h($pg['img']) ?>" loading="lazy"></div>
      <div class="pied"><span><?= h($pg['chaine']['numero']) ?></span><span class="nom"><?= h($m['nom']) ?></span><span class="nom"><?= h($m['prenom']) ?></span><span class="nom"><?= h($m['club']) ?></span><span><?= $m['titre'] ? '<i>« ' . h($m['titre']) . ' »</i>' : '' ?></span></div>
    </section>
  <?php else: ?>
    <section class="page mosa"><div class="titre">Chaîne <?= h($pg['chaine']['numero']) ?> – la chaîne complète</div><?= $num ?>
      <div class="grille"><?php foreach ($pg['imgs'] as $src): ?><img src="<?= h($src) ?>" loading="lazy"><?php endforeach ?></div></section>
  <?php endif ?>
<?php endforeach ?>
<script>
  const pages = [...document.querySelectorAll('.page')]; let k = 0;
  function montre(i) { k = Math.max(0, Math.min(pages.length - 1, i)); pages.forEach((p, j) => p.classList.toggle('on', j === k)); }
  function diapo() { document.body.classList.add('diapo'); montre(0); document.documentElement.requestFullscreen?.(); }
  document.addEventListener('keydown', e => {
    if (!document.body.classList.contains('diapo')) return;
    if (['ArrowRight', 'PageDown', ' ', 'Enter'].includes(e.key)) { e.preventDefault(); montre(k + 1); }
    if (['ArrowLeft', 'PageUp', 'Backspace'].includes(e.key)) { e.preventDefault(); montre(k - 1); }
    if (e.key === 'Home') montre(0); if (e.key === 'End') montre(pages.length - 1);
    if (e.key === 'Escape') document.body.classList.remove('diapo');
  });
  document.addEventListener('click', e => { if (document.body.classList.contains('diapo')) montre(k + (e.clientX > innerWidth / 3 ? 1 : -1)); });
  document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) document.body.classList.remove('diapo'); });
</script>
</body></html>
