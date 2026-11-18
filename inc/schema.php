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
        "CREATE TABLE IF NOT EXISTS clubs (
            id $pk, nom VARCHAR(190) NOT NULL UNIQUE, actif INT NOT NULL DEFAULT 1, date_creation DATETIME)$eng",
        "CREATE TABLE IF NOT EXISTS file_envoi (
            id $pk, code VARCHAR(30), destinataire VARCHAR(190) NOT NULL, nom VARCHAR(190), sujet VARCHAR(255) NOT NULL,
            corps $txt NOT NULL, desinscription INT NOT NULL DEFAULT 0, statut VARCHAR(20) NOT NULL DEFAULT 'attente',
            tentatives INT NOT NULL DEFAULT 0, erreur VARCHAR(500), date_creation DATETIME, date_envoi DATETIME NULL)$eng",
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
        'invitation' => ['Invitation des anciens participants', '{prenom}, un nouveau cadavre exquis photo se prépare',
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
        'file_attente' => ['Point sur la file d\'attente', 'Cadavre exquis : où en es-tu dans la file d\'attente ?',
"Bonjour {prenom},

Petit point sur le {edition} : tu es toujours bien inscrit(e) !

{phrase_rang}
À titre indicatif, ton tour devrait arriver {estimation}.

Pour l'instant, {nb_photos} photos ont déjà été réalisées dans les {nb_chaines} chaînes.
Tu recevras un mail avec la photo et ton lien de dépôt dès que ce sera à toi.

À bientôt,
Patrick"],
        'resultats' => ['Résultats en ligne (après la projection)', 'Les cadavres exquis du {edition} sont en ligne',
"Bonjour {prenom},

Merci d'être venu(e) – ou d'avoir suivi de loin – la projection du {edition} !

Toutes les chaînes sont maintenant visibles en ligne, photo par photo, avec les titres et les démarches de chacun :
{lien_resultats}

Tu peux les regarder en diaporama (bouton « Diaporama ») ou les enregistrer en PDF.
Merci d'avoir joué le jeu, et à l'année prochaine !

Patrick"],
        'nouvelle_chance' => ['Sans réponse : replacé plus tard', 'Cadavre exquis : ta photo n\'est pas arrivée à temps',
"Bonjour {prenom},

Nous n'avons pas reçu ta photo pour la chaîne {chaine} avant la date limite : pour ne pas bloquer les autres participants, la chaîne a continué sans toi.

Pas d'inquiétude, tu restes inscrit(e) au {edition} : tu recevras plus tard la photo d'une autre chaîne (essai {essai} sur {max_essais}).
Pense à vérifier tes courriers indésirables et à ajouter cette adresse à tes contacts.

Si tu préfères ne plus participer, réponds simplement à ce mail.

Patrick"],
        'retire' => ['Sans réponse : retiré du cadavre', 'Cadavre exquis : on te retire de la liste',
"Bonjour {prenom},

Sans nouvelles de ta part après {max_essais} propositions de photo, nous t'avons retiré(e) du {edition} pour que les chaînes continuent d'avancer.

Si c'est une erreur ou si tu veux revenir, réponds simplement à ce mail.
Merci et à une prochaine fois !

Patrick"],
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
    try { db()->exec('ALTER TABLE participants ADD COLUMN dernier_point DATETIME NULL'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE maillons ADD COLUMN demarche TEXT NULL'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE maillons ADD COLUMN type_photo VARCHAR(20) NULL'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE editions ADD COLUMN resultats_token VARCHAR(64) NULL'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE editions ADD COLUMN resultats_publies INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE participants ADD COLUMN mail_resultats INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE participants ADD COLUMN sans_reponse INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE participants ADD COLUMN date_attente DATETIME NULL'); } catch (Throwable $e) {}

    // Liste des clubs : préremplie la première fois, puis complétée avec les clubs déjà saisis
    if (!(int) qv('SELECT COUNT(*) FROM clubs')) {
        foreach (clubs_defaut() as $c) ajouter_club($c);
        foreach (q("SELECT DISTINCT club FROM participants WHERE club IS NOT NULL AND club <> ''")->fetchAll(PDO::FETCH_COLUMN) as $c) ajouter_club($c);
    }
}

/** Clubs connus (cadavre exquis 2026). */
function clubs_defaut(): array
{
    return [
        'ASCMO (Lissieu)',
        'ASCS (Rillieux la Pape)',
        'CLAVI (Villeurbanne)',
        'Club Photo de Dardilly (Dardilly)',
        'Club Photo de Feurs (42)',
        'Club Photo St André de Corcy (St André de Corcy)',
        'Image Contact (Miribel)',
        'Objectifs en Dombes (Ambérieux-en-Dombes)',
        'Photo Passion (les Echets)',
        'SCF',
    ];
}
