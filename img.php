<?php
require __DIR__ . '/inc/bootstrap.php';

$rel = null;
$nom = 'photo.jpg';

if (($t = get('t')) !== '') {
    // Accès participant par jeton : photo précédente, ou sa propre photo une fois rendue
    $m = q1('SELECT * FROM maillons WHERE token = ?', [$t]);
    if ($m && $m['statut'] !== 'passe') {
        if (get('q') === 'moi') {
            $rel = $m['photo_reduite'];
        } else {
            $rel = photo_precedente($m)['fichier'];
            $nom = 'cadavre-exquis-photo-precedente.jpg';
        }
    }
} elseif (($r = get('r')) !== '') {
    // Page publique des résultats : seulement si l'édition est publiée
    $ed = q1('SELECT * FROM editions WHERE resultats_token = ? AND resultats_publies = 1', [$r]);
    if ($ed) {
        if (get('base') !== '') {
            $rel = $ed['photo_base'];
        } elseif (get('m') !== '') {
            $m = q1("SELECT m.* FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE m.id = ? AND c.edition_id = ? AND m.statut = 'rendu'", [(int) get('m'), $ed['id']]);
            $rel = $m['photo_reduite'] ?? null;
        }
    }
} elseif (admin()) {
    if (get('base') !== '') {
        $ed = edition((int) get('base'));
        $rel = $ed['photo_base'] ?? null;
    } elseif (get('m') !== '') {
        $m = maillon((int) get('m'));
        $rel = get('v') === 'o' ? ($m['photo'] ?? null) : ($m['photo_reduite'] ?? null);
    }
}

$path = chemin_photo($rel);
if (!$path) { http_response_code(404); exit; }

$mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
if (get('dl')) {
    $nom = get('t') ? $nom : basename($path);
    header('Content-Disposition: attachment; filename="' . addslashes($nom) . '"');
}
readfile($path);
