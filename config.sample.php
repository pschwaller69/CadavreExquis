<?php
// Copier ce fichier en config.php et l'adapter.
return [
    // URL publique de l'application, sans / final
    'base_url' => 'https://cadavreexquis.schwaller.art',

    // Base de données : 'mysql' (o2switch) ou 'sqlite' (tests)
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'name'     => 'xxxx_cadavre',
        'user'     => 'xxxx_cadavre',
        'pass'     => 'MOT_DE_PASSE_MYSQL',
        'sqlite'   => __DIR__ . '/data/cadavre.sqlite',
    ],

    // Envoi des mails via Gmail (SMTP). Nécessite la validation en 2 étapes
    // sur le compte Google + un "mot de passe d'application" (16 caractères).
    'mail' => [
        'mode'      => 'smtp',              // 'smtp' ou 'log' (n'envoie rien, journalise seulement)
        'host'      => 'smtp.gmail.com',
        'port'      => 587,
        'secure'    => 'tls',               // 'tls' (587) ou 'ssl' (465)
        'user'      => 'cadavre.exquis@gmail.com',
        'pass'      => 'xxxx xxxx xxxx xxxx',
        'from'      => 'cadavre.exquis@gmail.com', // Gmail impose l'adresse du compte
        'from_name' => 'Cadavre Exquis Photographique',
        'admin'     => 'patrick@schwaller.fr', // reçoit les alertes (retards, chaînes en attente)
    ],
    // Mails groupés (invitations, points de file d'attente, mail de fin) : envoyés par la tâche cron
    'cron_minutes'           => 5, // fréquence de la tâche cron (pour l'estimation affichée)
    'cron_mails_par_passage' => 1, // mails de la file envoyés à chaque passage
    'mail_pause_sec'         => 4, // pause si plusieurs mails par passage

    // Règles du jeu
    'delai_jours'        => 14, // délai pour rendre sa photo
    'relance_jours'      => 10, // relance automatique à J+10
    'point_attente_jours' => 14, // mail « il reste N personnes avant toi » tous les N jours (0 = jamais)
    'auto_passer_jours'  => 2,  // sans photo N jours après la date limite : sorti de la chaîne (0 = jamais, l'admin décide)
    'max_chances'        => 3,  // nombre d'essais : sans réponse, remis en liste d'attente pour une autre chaîne, puis retiré

    // Photos
    'upload_max_mo'      => 30,
    'reduite_px'         => 2000, // plus grand côté de la version réduite (envoyée au suivant / projection)

    // Clé pour lancer cron.php par URL (https://cadavreexquis.schwaller.art/cron.php?key=...) si pas de cron en ligne de commande
    'cron_key'           => 'changer-cette-cle-' . 'aleatoire',

    'timezone'           => 'Europe/Paris',
];
