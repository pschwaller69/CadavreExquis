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

    // Règles du jeu
    'delai_jours'        => 14, // délai pour rendre sa photo
    'relance_jours'      => 10, // relance automatique à J+10
    'auto_passer_jours'  => 0,  // 0 = jamais ; sinon passe automatiquement au suivant N jours après la date limite

    // Photos
    'upload_max_mo'      => 30,
    'reduite_px'         => 2000, // plus grand côté de la version réduite (envoyée au suivant / projection)

    // Clé pour lancer cron.php par URL (https://cadavreexquis.schwaller.art/cron.php?key=...) si pas de cron en ligne de commande
    'cron_key'           => 'changer-cette-cle-' . 'aleatoire',

    'timezone'           => 'Europe/Paris',
];
