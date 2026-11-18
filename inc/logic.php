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
    return q1("SELECT * FROM editions WHERE nom <> 'Anciens participants' ORDER BY id DESC LIMIT 1");
}

function chaine(int $id): ?array { return q1('SELECT * FROM chaines WHERE id = ?', [$id]); }
function maillon(int $id): ?array { return q1('SELECT * FROM maillons WHERE id = ?', [$id]); }
function participant(int $id): ?array { return q1('SELECT * FROM participants WHERE id = ?', [$id]); }

function maillons_chaine(int $chaine_id): array
{
    return q('SELECT m.*, p.nom, p.prenom, p.mail, p.club, p.sans_reponse FROM maillons m JOIN participants p ON p.id = m.participant_id
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

function supprimer_dossier(string $dir): void
{
    if (!is_dir($dir) || !str_starts_with(realpath($dir), realpath(PHOTO_DIR))) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
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
        'lien_resultats' => !empty($ed['resultats_token']) ? url('resultats.php?k=' . $ed['resultats_token']) : '',
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
/**
 * Clubs des dernières photos réellement présentes dans la chaîne (les participants passés ne comptent pas,
 * puisque leur photo n'est pas dans la chaîne), du plus récent au plus ancien. « Indépendant » est ignoré.
 */
function clubs_voisins(int $chaine_id, int $n = 2): array
{
    $clubs = q("SELECT p.club FROM maillons m JOIN participants p ON p.id = m.participant_id
                WHERE m.chaine_id = ? AND m.statut = 'rendu' ORDER BY m.position DESC LIMIT " . (int) $n, [$chaine_id])->fetchAll(PDO::FETCH_COLUMN);
    return array_map(fn($c) => est_independant((string) $c) ? '' : mb_strtolower((string) $c), $clubs);
}

/** Niveau de conflit d'un club avec la fin d'une chaîne : 0 = aucun, 1 = même club que l'avant-dernière, 2 = même club que la dernière photo. */
function conflit_club(?string $club, array $voisins): int
{
    $c = est_independant((string) $club) ? '' : mb_strtolower((string) $club);
    if ($c === '') return 0;
    if (($voisins[0] ?? null) === $c) return 2;
    if (($voisins[1] ?? null) === $c) return 1;
    return 0;
}

/**
 * Prochain participant pour une chaîne : le plus ancien inscrit en attente dont le club n'est pas celui
 * des 2 dernières photos ; à défaut, pas celui de la dernière (jamais deux du même club à la suite) ;
 * à défaut (tous du même club), le plus ancien.
 */
function choisir_suivant(array $chaine): ?array
{
    $cands = q("SELECT * FROM participants WHERE edition_id = ? AND statut = 'attente'
                    AND id NOT IN (SELECT participant_id FROM maillons WHERE chaine_id = ?)
                  ORDER BY COALESCE(date_attente, date_inscription), id", [$chaine['edition_id'], $chaine['id']])->fetchAll();
    if (!$cands) return null;
    $voisins = clubs_voisins((int) $chaine['id']);
    foreach ([0, 1] as $tolere) {
        foreach ($cands as $c) if (conflit_club($c['club'], $voisins) <= $tolere) return $c;
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
    // Parmi les chaînes qui attendent, la plus ancienne où ce club ne suit pas le même club
    $attente = q("SELECT * FROM chaines WHERE edition_id = ? AND statut = 'attente' ORDER BY attente_depuis, numero", [$ed['id']])->fetchAll();
    if (!$attente) return null;
    $ch = null;
    foreach ([0, 1, 2] as $tolere) {
        foreach ($attente as $a) if (!$ch && conflit_club($p['club'], clubs_voisins((int) $a['id'])) <= $tolere) $ch = $a;
        if ($ch) break;
    }
    if (chaine_doit_fermer($ch)) { update('chaines', (int) $ch['id'], ['statut' => 'close']); return null; }
    return lancer_maillon($ch, $p);
}

/** Inscrit un participant (formulaire public, saisie admin ou import). */
/** Club tel qu'enregistré pour un participant : nom de la liste si connu, sinon ajouté à la liste. */
function club_inscription(string $club): string
{
    try { return ajouter_club($club); } catch (Throwable $e) { return club_propre($club) ?: 'Indépendant'; }
}

function inscrire(int $edition_id, string $nom, string $prenom, string $mail, string $club, bool $mail_bienvenue = true): array
{
    $mail = mb_strtolower(trim($mail));
    $exist = q1('SELECT * FROM participants WHERE edition_id = ? AND mail = ?', [$edition_id, $mail]);
    if ($exist) return ['deja' => true, 'participant' => $exist];
    $id = insert('participants', [
        'edition_id' => $edition_id, 'nom' => nom_propre($nom), 'prenom' => prenom_propre($prenom),
        'mail' => $mail, 'club' => club_inscription($club), 'statut' => 'attente',
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
        'demarche' => mb_substr(trim(str_replace("\r\n", "\n", (string) post('demarche'))), 0, 600) ?: null,
        'type_photo' => array_key_exists((string) post('type_photo'), types_photo()) ? post('type_photo') : null,
    ]);
    envoyer_modele('merci', $p['mail'], vars_mail($p, maillon((int) $m['id'])), $p['prenom'] . ' ' . $p['nom']);
    avancer_chaine((int) $m['chaine_id']);
}

/** Abandon / dépassement : on saute ce participant, le suivant reçoit la dernière photo rendue. */
/**
 * Sortir un participant de sa chaîne (la chaîne continue avec le suivant).
 * $mode : 'sans_reponse' -> remis en fin de liste d'attente pour une autre chaîne, jusqu'à max_chances essais,
 *                           puis retiré du cadavre (un mail le prévient dans les deux cas) ;
 *         'retirer'      -> retiré du cadavre tout de suite (désistement, décision de l'admin), sans mail.
 * Retourne 'remis' ou 'retire'.
 */
function passer_maillon(array $m, string $raison = '', string $mode = 'sans_reponse'): string
{
    if ($m['statut'] !== 'en_cours') return '';
    update('maillons', (int) $m['id'], ['statut' => 'passe', 'date_retour' => now(), 'note' => $raison ?: null]);
    $p = participant((int) $m['participant_id']);
    $max = max(1, (int) cfg('max_chances', 3));
    $resultat = 'retire';
    if ($mode === 'sans_reponse') {
        $essais = (int) $p['sans_reponse'] + 1;
        $vars = vars_mail($p, $m) + ['essai' => $essais + 1, 'max_essais' => $max];
        if ($essais < $max) {
            update('participants', (int) $p['id'], ['statut' => 'attente', 'sans_reponse' => $essais, 'date_attente' => now()]);
            envoyer_modele('nouvelle_chance', $p['mail'], $vars, $p['prenom'] . ' ' . $p['nom']);
            $resultat = 'remis';
        } else {
            update('participants', (int) $p['id'], ['statut' => 'abandon', 'sans_reponse' => $essais]);
            envoyer_modele('retire', $p['mail'], $vars, $p['prenom'] . ' ' . $p['nom']);
        }
    } else {
        update('participants', (int) $p['id'], ['statut' => 'abandon']);
    }
    avancer_chaine((int) $m['chaine_id']);
    return $resultat;
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

// ---------------------------------------------------------------- point sur la file d'attente
/** Durée moyenne (jours) entre l'envoi et le rendu d'une photo dans l'édition (10 j par défaut). */
function duree_moyenne_maillon(int $edition_id): float
{
    $ms = q("SELECT m.date_envoi, m.date_retour FROM maillons m JOIN chaines c ON c.id = m.chaine_id
             WHERE c.edition_id = ? AND m.statut IN ('rendu','passe') AND m.date_retour IS NOT NULL", [$edition_id])->fetchAll();
    if (count($ms) < 3) return 10.0;
    $t = 0;
    foreach ($ms as $m) $t += max(0, strtotime($m['date_retour']) - strtotime($m['date_envoi']));
    return max(1.0, $t / count($ms) / 86400);
}

/**
 * File d'attente d'une édition avec rang et estimation pour chacun.
 * Indicatif : la règle « pas le même club que les 2 derniers » peut décaler un peu l'ordre.
 */
function file_attente(int $edition_id): array
{
    $file = q("SELECT * FROM participants WHERE edition_id = ? AND statut = 'attente' ORDER BY COALESCE(date_attente, date_inscription), id", [$edition_id])->fetchAll();
    $nb_ch = max(1, (int) qv("SELECT COUNT(*) FROM chaines WHERE edition_id = ? AND statut <> 'close'", [$edition_id]));
    $duree = duree_moyenne_maillon($edition_id);
    foreach ($file as $i => &$p) {
        $p['rang'] = $i + 1;
        $jours = (int) ceil(($i + 1) / $nb_ch) * $duree; // nombre de « tours » avant lui x durée moyenne d'un tour
        $p['jours_estimes'] = $jours;
    }
    return $file;
}

function texte_estimation(float $jours): string
{
    if ($jours <= 7) return 'dans les prochains jours';
    $sem = (int) round($jours / 7);
    if ($sem <= 1) return 'dans une semaine environ';
    if ($sem <= 8) return "dans $sem semaines environ";
    return 'dans ' . (int) round($jours / 30) . ' mois environ';
}

/**
 * Envoie le point « combien de personnes avant toi » aux inscrits en attente.
 * $force = true : à tous (bouton) ; sinon seulement si le dernier point date de N jours (cron).
 */
function envoyer_points_attente(array $ed, bool $force = false): int
{
    if ($ed['statut'] !== 'en_cours') return 0;
    $freq = (int) cfg('point_attente_jours', 14);
    if (!$force && $freq <= 0) return 0;
    $limite = plus_jours(now(), -$freq);
    $nb_photos = (int) qv("SELECT COUNT(*) FROM maillons m JOIN chaines c ON c.id = m.chaine_id WHERE c.edition_id = ? AND m.statut = 'rendu'", [$ed['id']]);
    $nb_chaines = (int) qv('SELECT COUNT(*) FROM chaines WHERE edition_id = ?', [$ed['id']]);
    $n = 0;
    foreach (file_attente((int) $ed['id']) as $p) {
        $ref = $p['dernier_point'] ?: $p['date_inscription'];
        if (!$force && $ref > $limite) continue;
        $devant = $p['rang'] - 1;
        $vars = vars_mail($p) + [
            'rang' => $p['rang'], 'devant' => $devant,
            'phrase_rang' => $devant === 0 ? 'Bonne nouvelle : tu es le prochain sur la liste !'
                : ($devant === 1 ? 'Il n\'y a plus qu\'une personne avant toi.' : "Il y a encore $devant personnes avant toi."),
            'estimation' => texte_estimation($p['jours_estimes']),
            'nb_photos' => $nb_photos, 'nb_chaines' => $nb_chaines,
        ];
        if (mettre_en_file('file_attente', $p['mail'], $vars, $p['prenom'] . ' ' . $p['nom'])) {
            update('participants', (int) $p['id'], ['dernier_point' => now()]);
            $n++;
        }
    }
    return $n;
}

/**
 * Supprime une chaîne terminée, ses maillons et ses photos.
 * $avec_participants : supprime aussi les participants qui n'étaient que dans cette chaîne (essais).
 */
function supprimer_chaine(array $ch, bool $avec_participants = true): void
{
    if ($ch['statut'] !== 'close') throw new RuntimeException('Terminez d\'abord la chaîne avant de la supprimer.');
    $pids = q('SELECT DISTINCT participant_id FROM maillons WHERE chaine_id = ?', [$ch['id']])->fetchAll(PDO::FETCH_COLUMN);
    q('DELETE FROM maillons WHERE chaine_id = ?', [$ch['id']]);
    q('DELETE FROM chaines WHERE id = ?', [$ch['id']]);
    foreach ($pids as $pid) {
        if (qv('SELECT COUNT(*) FROM maillons WHERE participant_id = ?', [$pid])) continue; // aussi dans une autre chaîne
        if ($avec_participants) q('DELETE FROM participants WHERE id = ?', [$pid]);
        else update('participants', (int) $pid, ['statut' => 'abandon']);
    }
    supprimer_dossier(PHOTO_DIR . '/' . (int) $ch['edition_id'] . '/' . basename($ch['numero']));
}

// ---------------------------------------------------------------- clubs
function est_independant(string $club): bool
{
    return in_array(mb_strtolower(trim($club)), ['', 'indépendant', 'independant', 'individuel', 'aucun'], true);
}

/** Ajoute un club s'il n'existe pas déjà (comparaison sans tenir compte des majuscules). Retourne le nom retenu. */
function ajouter_club(string $nom): string
{
    $nom = club_propre($nom);
    if (est_independant($nom)) return 'Indépendant';
    foreach (q('SELECT nom FROM clubs')->fetchAll(PDO::FETCH_COLUMN) as $c) {
        if (mb_strtolower($c) === mb_strtolower($nom)) return $c;
    }
    insert('clubs', ['nom' => $nom, 'actif' => 1, 'date_creation' => now()]);
    return $nom;
}

function clubs_actifs(): array
{
    try {
        return q('SELECT nom FROM clubs WHERE actif = 1 ORDER BY nom')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { // table pas encore créée
        return q("SELECT DISTINCT club FROM participants WHERE club IS NOT NULL AND club <> '' ORDER BY club")->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ---------------------------------------------------------------- type de photo
function types_photo(): array
{
    return ['occasion' => 'Photo réalisée pour l\'occasion', 'archive' => 'Photo d\'archive'];
}
function libelle_type_photo(?string $t): string { return types_photo()[$t] ?? ''; }

// ---------------------------------------------------------------- résultats publics
/** Lien public vers la présentation d'une édition (créé à la première demande). */
function lien_resultats(array $ed): string
{
    $tok = $ed['resultats_token'] ?? null;
    if (!$tok) {
        $tok = token(12);
        update('editions', (int) $ed['id'], ['resultats_token' => $tok]);
    }
    return url('resultats.php?k=' . $tok);
}
