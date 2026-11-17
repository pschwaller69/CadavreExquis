<?php
/** Schéma compatible MySQL et SQLite. */
function schema_sql(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $eng = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $txt = $driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT';
    return [
        "CREATE TABLE IF NOT EXISTS admins (
            id $pk, login VARCHAR(60) NOT NULL UNIQUE, nom VARCHAR(120), mail VARCHAR(190),
            pass_hash VARCHAR(255) NOT NULL, derniere_connexion DATETIME NULL)$eng",
        "CREATE TABLE IF NOT EXISTS editions (
            id $pk, nom VARCHAR(120) NOT NULL, annee INT, photo_base VARCHAR(255),
            statut VARCHAR(20) NOT NULL DEFAULT 'preparation',
            inscriptions_ouvertes INT NOT NULL DEFAULT 1,
            date_fin_envois DATE NULL, max_maillons INT NOT NULL DEFAULT 0,
            texte_accueil $txt, date_creation DATETIME)$eng",
        "CREATE TABLE IF NOT EXISTS chaines (
            id $pk, edition_id INT NOT NULL, numero VARCHAR(5) NOT NULL,
            statut VARCHAR(20) NOT NULL DEFAULT 'active', attente_depuis DATETIME NULL,
            FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE)$eng",
        "CREATE TABLE IF NOT EXISTS participants (
            id $pk, edition_id INT NOT NULL, nom VARCHAR(120) NOT NULL, prenom VARCHAR(120) NOT NULL,
            mail VARCHAR(190) NOT NULL, club VARCHAR(190), statut VARCHAR(20) NOT NULL DEFAULT 'attente',
            date_inscription DATETIME, mail_bienvenue INT NOT NULL DEFAULT 0, mail_fin INT NOT NULL DEFAULT 0,
            remarque VARCHAR(255),
            FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE)$eng",
        "CREATE TABLE IF NOT EXISTS maillons (
            id $pk, chaine_id INT NOT NULL, participant_id INT NOT NULL, position INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE, statut VARCHAR(20) NOT NULL DEFAULT 'en_cours',
            date_envoi DATETIME, date_limite DATETIME, date_retour DATETIME NULL,
            relance_envoyee INT NOT NULL DEFAULT 0, alerte_envoyee INT NOT NULL DEFAULT 0,
            photo VARCHAR(255) NULL, photo_reduite VARCHAR(255) NULL, titre VARCHAR(200) NULL, note VARCHAR(255) NULL,
            FOREIGN KEY (chaine_id) REFERENCES chaines(id) ON DELETE CASCADE,
            FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE)$eng",
        "CREATE TABLE IF NOT EXISTS modeles_mail (
            code VARCHAR(30) PRIMARY KEY, libelle VARCHAR(120), sujet VARCHAR(255) NOT NULL, corps $txt NOT NULL)$eng",
        "CREATE TABLE IF NOT EXISTS invitations (
            id $pk, edition_id INT NOT NULL, mail VARCHAR(190) NOT NULL, date_envoi DATETIME,
            FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE)$eng",
        "CREATE TABLE IF NOT EXISTS journal_mails (
            id $pk, date_envoi DATETIME, destinataire VARCHAR(190), sujet VARCHAR(255), corps $txt,
            statut VARCHAR(20), erreur VARCHAR(500))$eng",
    ];
}

function modeles_defaut(): array
{
    return [
        'bienvenue' => ['Bienvenue (inscription)', 'Bienvenue dans le {edition} !',
"Bonjour {prenom},

Merci pour ton inscription au {edition} !

Le principe : tu vas recevoir la photo du participant précédent de ta chaîne. Tu auras {delai} jours pour réaliser une photo qui s'en inspire (une forme, une couleur, un sujet, une idée… à toi de voir), puis la déposer grâce au lien que tu recevras.
Tu ne verras que la photo qui te précède : le résultat complet sera dévoilé à la fin.

Tu es pour l'instant en liste d'attente ; tu recevras un mail dès que ce sera ton tour.

À bientôt,
Patrick"],
        'a_toi' => ['C\'est ton tour (lien de dépôt)', 'Cadavre exquis : c\'est ton tour ! (chaîne {chaine})',
"Bonjour {prenom},

C'est à toi de jouer dans la chaîne {chaine} du {edition} !

Découvre la photo qui te précède et dépose la tienne ici :
{lien}

Tu as jusqu'au {date_limite} pour déposer ta photo (JPEG, la plus grande résolution possible).
Le même lien te permet de revoir la photo à tout moment.

Si tu ne peux finalement pas participer, réponds simplement à ce mail pour que la chaîne continue sans attendre.

Bonne inspiration !
Patrick"],
        'relance' => ['Relance (J+10)', 'Rappel : ta photo pour le cadavre exquis (chaîne {chaine})',
"Bonjour {prenom},

Petit rappel : il te reste jusqu'au {date_limite} pour déposer ta photo dans la chaîne {chaine}.

Ton lien : {lien}

Si tu as un empêchement, réponds à ce mail, je passerai la main au suivant.

Merci !
Patrick"],
        'merci' => ['Merci (photo reçue)', 'Photo bien reçue, merci {prenom} !',
"Bonjour {prenom},

Ta photo pour la chaîne {chaine} est bien arrivée, merci !
Elle vient d'être transmise au participant suivant.

Le résultat complet sera dévoilé à la fin du {edition}.

Patrick"],
        'invitation' => ['Invitation des anciens participants', 'Le {edition} commence : inscris-toi !',
"Bonjour {prenom},

Tu as participé à un précédent cadavre exquis photographique : merci encore !

Une nouvelle édition se prépare : le {edition}.
Le principe ne change pas : tu reçois la photo de la personne qui te précède, tu as {delai} jours pour créer une photo qui s'en inspire, et tout est dévoilé à la fin.

Pour t'inscrire, c'est ici :
{site}

N'hésite pas à transmettre ce lien aux membres de ton club !

À bientôt,
Patrick

(Tu reçois ce mail car tu as participé à une édition précédente. Si tu ne souhaites plus être invité, réponds simplement « stop ».)"],
        'fin' => ['Cadavre terminé', 'Le {edition} est terminé !',
"Bonjour {prenom},

Le {edition} est terminé : merci pour ta participation !

[Indiquer ici la date, l'heure et le lieu de la projection.]

À très bientôt,
Patrick"],
    ];
}

/** Crée les tables / modèles manquants (mises à jour sans relancer install.php). */
function assurer_schema(): void
{
    foreach (schema_sql(cfg('db.driver')) as $sql) db()->exec($sql);
    foreach (modeles_defaut() as $code => [$lib, $suj, $corps]) {
        if (!qv('SELECT COUNT(*) FROM modeles_mail WHERE code = ?', [$code])) {
            insert('modeles_mail', ['code' => $code, 'libelle' => $lib, 'sujet' => $suj, 'corps' => $corps]);
        }
    }
    try { db()->exec('ALTER TABLE participants ADD COLUMN ne_plus_inviter INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
}
