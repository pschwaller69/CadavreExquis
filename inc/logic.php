<?php
/*
 * Logique métier du cadavre exquis.
 *
 * Édition  : une année de jeu (photo de base commune, N chaînes).
 * Chaîne   : 01, 02, ... ; statut 'active' | 'attente' (plus personne en liste d'attente) | 'close'.
 * Maillon  : un participant placé dans une chaîne ; statut 'en_cours' | 'rendu' | 'passe'.
 * Participant : statut 'attente' (liste d'attente, l'ancien "99") | 'affecte' | 'abandon'.
 */

// ---------------------------------------------------------------- éditions
function edition(int $id): ?array { return q1('SELECT * FROM editions WHERE id = ?', [$id]); }

/** Édition ouverte aux inscriptions publiques (la plus récente). */
function edition_inscriptions(): ?array
{
    return q1("SELECT * FROM editions WHERE inscriptions_ouvertes = 1 AND statut <> 'terminee' ORDER BY id DESC LIMIT 1");
}

/** Édition sélectionnée dans l'admin (session) ou la plus récente. */
function edition_admin(): ?array
{
    start_session();
    if (!empty($_SESSION['edition_id']) && ($e = edition((int) $_SESSION['edition_id']))) return $e;
    return q1('SELECT * FROM editions ORDER BY id DESC LIMIT 1');
}

function chaine(int $id): ?array { return q1('SELECT * FROM chaines WHERE id = ?', [$id]); }
function maillon(int $id): ?array { return q1('SELECT * FROM maillons WHERE id = ?', [$id]); }
function participant(int $id): ?array { return q1('SELECT * FROM participants WHERE id = ?', [$id]); }

function maillons_chaine(int $chaine_id): array
{
    return q('SELECT m.*, p.nom, p.prenom, p.mail, p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
              WHERE m.chaine_id = ? ORDER BY m.position', [$chaine_id])->fetchAll();
}

// ---------------------------------------------------------------- photos
/** Photo que voit le participant de ce maillon : dernière photo rendue avant lui, sinon photo de base. */
function photo_precedente(array $maillon): array
{
    $prev = q1("SELECT m.*, p.nom, p.prenom, p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
                WHERE m.chaine_id = ? AND m.position < ? AND m.statut = 'rendu' ORDER BY m.position DESC LIMIT 1",
               [$maillon['chaine_id'], $maillon['position']]);
    if ($prev) return ['type' => 'maillon', 'maillon' => $prev, 'fichier' => $prev['photo_reduite']];
    $ch = chaine((int) $maillon['chaine_id']);
    $ed = edition((int) $ch['edition_id']);
    return ['type' => 'base', 'maillon' => null, 'fichier' => $ed['photo_base']];
}

function chemin_photo(?string $rel): ?string
{
    if (!$rel) return null;
    $p = PHOTO_DIR . '/' . $rel;
    return is_file($p) ? $p : null;
}

function nom_fichier(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    return trim(preg_replace('/[^A-Za-z0-9 _\-]+/', '', $s));
}

/**
 * Vérifie et enregistre une image envoyée ($_FILES[...]) : original + version réduite JPEG.
 * Retourne [chemin_original_relatif, chemin_reduite_relatif]. Lance une Exception en cas de problème.
 */
function enregistrer_image(array $file, string $dossier_rel, string $base_nom): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = [
            UPLOAD_ERR_INI_SIZE => 'fichier trop lourd pour le serveur',
            UPLOAD_ERR_FORM_SIZE => 'fichier trop lourd',
            UPLOAD_ERR_PARTIAL => 'envoi interrompu, réessayez',
            UPLOAD_ERR_NO_FILE => 'aucun fichier choisi',
        ][$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'erreur d\'envoi';
        throw new RuntimeException('Photo non reçue : ' . $msg . '.');
    }
    if ($file['size'] > cfg('upload_max_mo', 30) * 1024 * 1024) {
        throw new RuntimeException('La photo dépasse ' . cfg('upload_max_mo', 30) . ' Mo.');
    }
    $info = @getimagesize($file['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) throw new RuntimeException('Format non accepté : envoyez un JPEG (ou PNG/WebP).');

    @ini_set('memory_limit', '1024M');
    $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$src) throw new RuntimeException('Image illisible.');

    // Orientation EXIF (photos de smartphone)
    if ($ext === 'jpg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }

    $dir = PHOTO_DIR . '/' . $dossier_rel;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $base = nom_fichier($base_nom) ?: 'photo';
    $orig_rel = $dossier_rel . '/' . $base . '.' . $ext;
    $red_rel = $dossier_rel . '/' . $base . ' reduite.jpg';

    if (!move_uploaded_file($file['tmp_name'], PHOTO_DIR . '/' . $orig_rel)) {
        if (!copy($file['tmp_name'], PHOTO_DIR . '/' . $orig_rel)) throw new RuntimeException('Impossible d\'enregistrer la photo.');
    }

    $w = imagesx($src); $hgt = imagesy($src);
    $max = (int) cfg('reduite_px', 2000);
    $r = min(1, $max / max($w, $hgt));
    $nw = (int) round($w * $r); $nh = (int) round($hgt * $r);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    imagejpeg($dst, PHOTO_DIR . '/' . $red_rel, 88);
    imagedestroy($src); imagedestroy($dst);

    return [$orig_rel, $red_rel];
}

// ---------------------------------------------------------------- variables de mail
function vars_mail(array $p, ?array $m = null): array
{
    $ed = edition((int) $p['edition_id']);
    $v = [
        'prenom' => $p['prenom'], 'nom' => $p['nom'], 'club' => $p['club'], 'mail' => $p['mail'],
        'edition' => $ed['nom'] ?? '', 'delai' => cfg('delai_jours', 14),
        'date_fin' => fdate($ed['date_fin_envois'] ?? null),
        'site' => url(),
    ];
    if ($m) {
        $ch = chaine((int) $m['chaine_id']);
        $v += [
            'chaine' => $ch['numero'], 'lien' => url('depot.php?t=' . $m['token']),
            'date_limite' => fdate($m['date_limite']), 'position' => $m['position'],
        ];
    }
    return $v;
}

// ---------------------------------------------------------------- moteur
/** Choisit le prochain participant de la liste d'attente pour une chaîne (en évitant les clubs des derniers maillons). */
function choisir_suivant(array $chaine): ?array
{
    $cands = q("SELECT * FROM participants WHERE edition_id = ? AND statut = 'attente' ORDER BY date_inscription, id", [$chaine['edition_id']])->fetchAll();
    if (!$cands) return null;
    $recents = q("SELECT p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
                  WHERE m.chaine_id = ? ORDER BY m.position DESC LIMIT 2", [$chaine['id']])->fetchAll(PDO::FETCH_COLUMN);
    $recents = array_map('mb_strtolower', $recents);
    foreach ($cands as $c) {
        if (!in_array(mb_strtolower($c['club']), $recents, true)) return $c;
    }
    return $cands[0];
}

/** Place un participant dans une chaîne et lui envoie sa photo de départ. */
function lancer_maillon(array $chaine, array $p): array
{
    $pos = (int) qv('SELECT COALESCE(MAX(position),0) FROM maillons WHERE chaine_id = ?', [$chaine['id']]) + 1;
    $n = now();
    $id = insert('maillons', [
        'chaine_id' => $chaine['id'], 'participant_id' => $p['id'], 'position' => $pos,
        'token' => token(), 'statut' => 'en_cours', 'date_envoi' => $n,
        'date_limite' => plus_jours($n, (int) cfg('delai_jours', 14)),
        'relance_envoyee' => 0, 'alerte_envoyee' => 0,
    ]);
    update('participants', (int) $p['id'], ['statut' => 'affecte']);
    update('chaines', (int) $chaine['id'], ['statut' => 'active', 'attente_depuis' => null]);
    $m = maillon($id);
    envoyer_modele('a_toi', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
    return $m;
}

function chaine_doit_fermer(array $chaine): bool
{
    $ed = edition((int) $chaine['edition_id']);
    if ($ed['statut'] === 'terminee') return true;
    if ($ed['date_fin_envois'] && date('Y-m-d') > substr($ed['date_fin_envois'], 0, 10)) return true;
    if ((int) $ed['max_maillons'] > 0) {
        $rendus = (int) qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'rendu'", [$chaine['id']]);
        if ($rendus >= (int) $ed['max_maillons']) return true;
    }
    return false;
}

/** Après un rendu ou un abandon : passe au participant suivant, ou met la chaîne en attente / la ferme. */
function avancer_chaine(int $chaine_id): ?array
{
    $ch = chaine($chaine_id);
    if (!$ch || $ch['statut'] === 'close') return null;
    if (qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'en_cours'", [$chaine_id])) return null;
    $ed = edition((int) $ch['edition_id']);
    if ($ed['statut'] === 'preparation') return null;

    if (chaine_doit_fermer($ch)) {
        update('chaines', $chaine_id, ['statut' => 'close']);
        return null;
    }
    $p = choisir_suivant($ch);
    if ($p) return lancer_maillon($ch, $p);

    update('chaines', $chaine_id, ['statut' => 'attente', 'attente_depuis' => now()]);
    mail_admin("Chaîne {$ch['numero']} en attente",
        "La chaîne {$ch['numero']} ({$ed['nom']}) n'a plus de participant en liste d'attente.\n"
        . "Elle repartira automatiquement à la prochaine inscription.\n\n" . url('admin/'));
    return null;
}

/** Nouvelle inscription : si une chaîne attend quelqu'un, on la lance tout de suite. */
function placer_si_chaine_en_attente(array $p): ?array
{
    $ed = edition((int) $p['edition_id']);
    if ($ed['statut'] !== 'en_cours') return null;
    $ch = q1("SELECT * FROM chaines WHERE edition_id = ? AND statut = 'attente' ORDER BY attente_depuis, numero LIMIT 1", [$ed['id']]);
    if (!$ch) return null;
    if (chaine_doit_fermer($ch)) { update('chaines', (int) $ch['id'], ['statut' => 'close']); return null; }
    return lancer_maillon($ch, $p);
}

/** Inscrit un participant (formulaire public, saisie admin ou import). */
function inscrire(int $edition_id, string $nom, string $prenom, string $mail, string $club, bool $mail_bienvenue = true): array
{
    $mail = mb_strtolower(trim($mail));
    $exist = q1('SELECT * FROM participants WHERE edition_id = ? AND mail = ?', [$edition_id, $mail]);
    if ($exist) return ['deja' => true, 'participant' => $exist];
    $id = insert('participants', [
        'edition_id' => $edition_id, 'nom' => nom_propre($nom), 'prenom' => prenom_propre($prenom),
        'mail' => $mail, 'club' => club_propre($club) ?: 'Indépendant', 'statut' => 'attente',
        'date_inscription' => now(), 'mail_bienvenue' => 0,
    ]);
    $p = participant($id);
    if ($mail_bienvenue) {
        envoyer_modele('bienvenue', $p['mail'], vars_mail($p), $p['prenom'] . ' ' . $p['nom']);
        update('participants', $id, ['mail_bienvenue' => 1]);
    }
    $m = placer_si_chaine_en_attente($p);
    return ['deja' => false, 'participant' => participant($id), 'maillon' => $m];
}

/** Le participant dépose sa photo. */
function deposer_photo(array $m, array $file): void
{
    if ($m['statut'] !== 'en_cours') throw new RuntimeException('Ce lien n\'attend plus de photo.');
    $p = participant((int) $m['participant_id']);
    $ch = chaine((int) $m['chaine_id']);
    [$orig, $red] = enregistrer_image($file, $ch['edition_id'] . '/' . $ch['numero'],
        sprintf('%02d %s %s', $m['position'], $p['prenom'], $p['nom']));
    update('maillons', (int) $m['id'], [
        'photo' => $orig, 'photo_reduite' => $red, 'statut' => 'rendu', 'date_retour' => now(),
        'titre' => mb_substr((string) post('titre'), 0, 200) ?: null,
    ]);
    envoyer_modele('merci', $p['mail'], vars_mail($p, maillon((int) $m['id'])), $p['prenom'] . ' ' . $p['nom']);
    avancer_chaine((int) $m['chaine_id']);
}

/** Abandon / dépassement : on saute ce participant, le suivant reçoit la dernière photo rendue. */
function passer_maillon(array $m, string $raison = ''): void
{
    if ($m['statut'] !== 'en_cours') return;
    update('maillons', (int) $m['id'], ['statut' => 'passe', 'date_retour' => now(), 'note' => $raison ?: null]);
    update('participants', (int) $m['participant_id'], ['statut' => 'abandon']);
    avancer_chaine((int) $m['chaine_id']);
}

/** Démarre l'édition : chaque chaîne sans maillon en cours reçoit un participant. */
function demarrer_edition(array $ed): int
{
    if ($ed['statut'] === 'preparation') update('editions', (int) $ed['id'], ['statut' => 'en_cours']);
    $n = 0;
    foreach (q("SELECT * FROM chaines WHERE edition_id = ? AND statut <> 'close' ORDER BY numero", [$ed['id']])->fetchAll() as $ch) {
        if (avancer_chaine((int) $ch['id'])) $n++;
    }
    return $n;
}
