<?php
/*
 * Tâche planifiée : file d'envoi des mails groupés, relances, alertes de retard, passage automatique, réparation des chaînes.
 * cPanel o2switch > Tâches Cron, toutes les 5 minutes (la file d'envoi part 1 mail par passage) :
 *   0-59/5 * * * * /usr/local/bin/php /home/VOTRE_COMPTE/cadavreexquis.schwaller.art/cron.php >/dev/null 2>&1
 * Les relances, alertes et points ne partent qu'une fois (dates enregistrées), même si le cron tourne souvent.
 * ou par URL : https://cadavreexquis.schwaller.art/cron.php?key=VOTRE_CLE
 */
require __DIR__ . '/inc/bootstrap.php';

if (PHP_SAPI !== 'cli' && !hash_equals((string) cfg('cron_key'), get('key'))) {
    http_response_code(403);
    exit('Accès refusé');
}
header('Content-Type: text/plain; charset=utf-8');
@file_put_contents(DATA_DIR . '/cron_dernier.txt', now());

$log = [];
$retards = [];
$n = now();
$rel = (int) cfg('relance_jours', 10);
$auto = (int) cfg('auto_passer_jours', 2);

$ms = q("SELECT m.* FROM maillons m JOIN chaines c ON c.id = m.chaine_id JOIN editions e ON e.id = c.edition_id
         WHERE m.statut = 'en_cours' ORDER BY m.date_limite")->fetchAll();

foreach ($ms as $m) {
    $p = participant((int) $m['participant_id']);
    $ch = chaine((int) $m['chaine_id']);
    $qui = "{$p['prenom']} {$p['nom']} (chaîne {$ch['numero']})";

    // 1. Passage automatique au suivant
    if ($auto > 0 && plus_jours($m['date_limite'], $auto) < $n) {
        $r = passer_maillon($m, "Sans réponse ($auto j après la date limite)");
        $log[] = "Sans réponse : $qui " . ($r === 'remis' ? 'remis en liste d\'attente' : 'retiré du cadavre');
        mail_admin('Sans réponse', "$qui n'a pas déposé sa photo : la chaîne est passée au participant suivant.\n"
            . ($r === 'remis' ? "Il/elle est remis(e) en fin de liste d'attente pour une autre chaîne (essai " . (participant((int) $p['id'])['sans_reponse'] + 1) . ' sur ' . (int) cfg('max_chances', 3) . ').'
                              : 'Sans réponse après ' . (int) cfg('max_chances', 3) . ' essais : retiré(e) du cadavre.')
            . "\n" . url('admin/chaine.php?id=' . $ch['id']));
        continue;
    }
    // 2. Relance à J+N
    if (!$m['relance_envoyee'] && plus_jours($m['date_envoi'], $rel) <= $n && $m['date_limite'] > $n) {
        envoyer_modele('relance', $p['mail'], vars_mail($p, $m), $p['prenom'] . ' ' . $p['nom']);
        update('maillons', (int) $m['id'], ['relance_envoyee' => 1]);
        $log[] = "Relance : $qui";
    }
    // 3. Date limite dépassée : alerte (une seule fois)
    if (!$m['alerte_envoyee'] && $m['date_limite'] < $n) {
        update('maillons', (int) $m['id'], ['alerte_envoyee' => 1]);
        $retards[] = "- $qui, limite le " . fdate($m['date_limite']) . " : " . url('admin/chaine.php?id=' . $ch['id']);
    }
}
if ($retards) {
    mail_admin(count($retards) . ' photo(s) en retard',
        "Date limite dépassée pour :\n" . implode("\n", $retards)
        . "\n\nDepuis la page de la chaîne vous pouvez relancer, prolonger de 7 jours, déposer la photo reçue par mail ou passer au suivant.");
    $log[] = count($retards) . ' alerte(s) de retard envoyée(s) à l\'admin';
}

// 4. Point régulier aux inscrits en liste d'attente (« il reste N personnes avant toi »)
foreach (q("SELECT * FROM editions WHERE statut = 'en_cours'")->fetchAll() as $ed) {
    if ($n = envoyer_points_attente($ed)) $log[] = "Point file d'attente envoyé à $n inscrit(s) ({$ed['nom']})";
}

// 5. Chaînes sans participant en cours (fin de délai d'envoi, réparation)
foreach (q("SELECT c.* FROM chaines c JOIN editions e ON e.id = c.edition_id WHERE e.statut = 'en_cours' AND c.statut <> 'close'")->fetchAll() as $ch) {
    if (qv("SELECT COUNT(*) FROM maillons WHERE chaine_id = ? AND statut = 'en_cours'", [$ch['id']])) continue;
    if (chaine_doit_fermer($ch)) { update('chaines', (int) $ch['id'], ['statut' => 'close']); $log[] = "Chaîne {$ch['numero']} terminée"; continue; }
    // chaîne active sans participant, ou chaîne en attente alors que quelqu'un est revenu en liste d'attente
    if (avancer_chaine((int) $ch['id'])) $log[] = "Chaîne {$ch['numero']} relancée";
}

// 6. File d'envoi des mails groupés : quelques mails par passage
if ($n = traiter_file((int) cfg('cron_mails_par_passage', 1))) $log[] = "$n mail(s) de la file envoyé(s), " . file_en_attente() . ' restant(s)';

echo date('d/m/Y H:i') . " – " . ($log ? implode("\n", $log) : 'rien à faire') . "\n";
