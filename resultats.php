<?php
/*
 * Page publique des résultats : la présentation complète d'une édition, accessible par un lien secret
 * (resultats.php?k=...) une fois que l'administrateur l'a publiée (tableau de bord).
 */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/presentation.php';
header('X-Robots-Tag: noindex, nofollow');

$k = get('k');
$ed = $k !== '' ? q1('SELECT * FROM editions WHERE resultats_token = ?', [$k]) : null;
$apercu = $ed && !$ed['resultats_publies'] && admin(); // l'admin peut prévisualiser avant publication

if (!$ed || (!$ed['resultats_publies'] && !$apercu)) {
    http_response_code(404); ?>
<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Résultats – Cadavre Exquis</title><link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
<body class="public"><main class="carte"><h1>Cadavre Exquis <span>Photographique</span></h1>
<p>Les résultats ne sont pas (encore) disponibles à cette adresse.</p><p><a href="suivi.php">Suivre l'avancement</a></p></main></body></html>
<?php exit;
}

$chaines = q('SELECT * FROM chaines WHERE edition_id = ? ORDER BY numero', [$ed['id']])->fetchAll();
$q = $apercu ? '' : 'r=' . urlencode($k) . '&';
afficher_presentation($ed, $chaines,
    fn(string $type, ?int $id) => 'img.php?' . $q . ($type === 'base' ? 'base=' . $ed['id'] : 'm=' . $id),
    ['titre' => 'Résultats', 'head' => '<meta name="robots" content="noindex"><meta name="viewport" content="width=device-width,initial-scale=1">',
     'entete' => $apercu ? '<p style="padding:70px 20px 0;color:#ffb347">Aperçu administrateur : ces résultats ne sont pas encore publiés.</p>' : '']);
