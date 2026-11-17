# Cadavre Exquis Photographique – application web

Application PHP/MySQL pour organiser le cadavre exquis photographique inter-clubs.
Elle remplace le logiciel WinDev et le formulaire Google. Comme elle tourne sur l'hébergement,
il n'y a pas de licence par utilisateur et plusieurs administrateurs peuvent l'utiliser.

## Ce qui est automatique

| Avant (WinDev + Google Form)        | Maintenant                                                                    |
|-------------------------------------|-------------------------------------------------------------------------------|
| Formulaire Google                   | Formulaire d'inscription public (page d'accueil), avec la liste des clubs      |
| Cadavre « 99 »                      | Liste d'attente (par ordre d'inscription)                                     |
| Envoi Mail Bienvenue                | Envoyé automatiquement à l'inscription                                        |
| Envoi Mail Suivant / Ajout du lien précédent | Automatique : quand une photo est déposée, le premier de la liste d'attente reçoit son lien (en évitant le club des 2 derniers maillons de la chaîne) |
| Photos reçues par mail, rangées dans OneDrive | Chaque participant dépose sa photo via son lien personnel (version réduite générée automatiquement) |
| Envoi Mail Relance                  | Relance automatique à J+10 + bouton « Relancer »                               |
| Retards                             | Alerte à l'administrateur à l'échéance ; boutons +7 j / Passer / Déposer pour lui |
| Imprimer Présentation               | Page Présentation : impression PDF A4 paysage et **mode diaporama** pour la projection |
| Envoi Mail Cadavres Terminé         | Bouton après la clôture de l'édition                                          |

Autres améliorations : chaîne sans participant mise « en attente » puis relancée dès la prochaine
inscription, désistement du participant en un clic, modèles de mails modifiables avec journal des envois,
import CSV, harmonisation des noms de clubs, export CSV et zip des originaux, plusieurs éditions (années).

## Installation sur o2switch

### 1. Préparer le compte Gmail
1. Sur le compte Gmail utilisé pour les envois, activez la **validation en deux étapes**.
2. Créez un **mot de passe d'application** : https://myaccount.google.com/apppasswords
   (nom : « Cadavre exquis »). Gardez les 16 caractères.
3. Limite Gmail : environ 500 mails par jour, ce qui est largement suffisant.

### 2. cPanel o2switch
1. **Domaines** : créez le sous-domaine `cadavreexquis.schwaller.art` (dossier par ex. `cadavreexquis.schwaller.art`).
   Vérifiez que le certificat SSL est actif (AutoSSL / Let's Encrypt).
2. **Bases de données MySQL** : créez une base, un utilisateur, et donnez-lui tous les privilèges sur la base.
3. **Git Version Control** : clonez `https://github.com/pschwaller69/CadavreExquis`, puis « Update from Remote »
   et « Deploy HEAD Commit » (le fichier `.cpanel.yml` copie les fichiers dans le dossier du sous-domaine).
   Même manipulation à chaque mise à jour ; la version déployée s'affiche dans l'admin et sur `/version.php`.
4. Copiez `config.sample.php` en `config.php` et remplissez : `base_url`, la base MySQL, le bloc `mail`
   (adresse Gmail + mot de passe d'application), `mail.admin`, et changez `cron_key`.
5. Ouvrez `https://cadavreexquis.schwaller.art/install.php` : contrôles, création des tables et du premier compte admin.
6. **Supprimez `install.php`.**
7. Vérifiez que `https://cadavreexquis.schwaller.art/data/photos/` et `https://cadavreexquis.schwaller.art/config.php` répondent « 403 Forbidden ».

### 3. Tâche cron (relances et alertes)
cPanel > **Tâches Cron**, une fois par jour, par exemple à 9h :
```
0 9 * * * /usr/local/bin/php /home/VOTRE_COMPTE/cadavreexquis.schwaller.art/cron.php
```
(Si la ligne de commande n'est pas possible : `https://cadavreexquis.schwaller.art/cron.php?key=VOTRE_CLE`.)

### 4. Tester les mails
Admin > **Mails** > « M'envoyer un test ». En cas d'échec, l'erreur s'affiche dans le journal en bas de page.
Si o2switch bloquait la connexion SMTP vers Gmail, créez une boîte `cadavre@schwaller.art` dans cPanel et
mettez ses paramètres SMTP (`mail.schwaller.art`, port 465, `ssl`) dans `config.php`.

## Déroulement d'une édition
1. **Édition** > nouvelle édition : nom, nombre de chaînes (6), photo de départ, date de dernier envoi
   ou nombre maximum de photos par chaîne.
2. Diffusez le lien `https://cadavreexquis.schwaller.art` aux clubs. Les inscrits arrivent en liste d'attente.
3. **Tableau de bord** > « Démarrer l'édition » : chaque chaîne reçoit son premier participant.
4. Ensuite tout s'enchaîne seul. Sur le tableau de bord, vous voyez les vignettes de chaque chaîne,
   qui est en cours et les retards. Sur la page d'une chaîne : relancer, prolonger, passer, déposer
   une photo reçue par mail, remplacer une photo.
5. « Clôturer l'édition » : plus aucun nouvel envoi. Les photos en cours peuvent encore être déposées.
6. **Présentation** : imprimer en PDF (Ctrl+P, A4 paysage, « graphiques d'arrière-plan » cochés)
   ou lancer le **diaporama** (flèches / clic ; Échap pour sortir ; case « Noms » pour masquer les auteurs).
7. Bouton « Mail Cadavre terminé » : à personnaliser d'abord dans Mails (date et lieu de la projection).

## Réglages (config.php)
- `delai_jours` (14) : délai pour rendre sa photo.
- `relance_jours` (10) : relance automatique.
- `point_attente_jours` (14) : mail « il reste N personnes avant toi » aux inscrits en attente (0 = jamais).
- `auto_passer_jours` (0) : 0 = vous décidez ; N = passe automatiquement au suivant N jours après l'échéance.
- `upload_max_mo` (30) : taille maximale d'une photo (voir aussi `.user.ini`).
- `reduite_px` (2000) : taille de la version transmise au suivant et projetée.

## Sauvegarde
La base MySQL et le dossier `data/photos/` (originaux et versions réduites).
Les sauvegardes JetBackup d'o2switch couvrent les deux.

## Structure
```
index.php        inscription publique
depot.php        page personnelle de dépôt (lien unique par participant)
img.php          affichage protégé des photos
cron.php         relances / alertes quotidiennes
admin/           administration (tableau de bord, chaînes, participants, édition, mails, présentation, export)
inc/             code (logique, mails, PHPMailer) – accès web interdit
data/photos/     photos – accès web interdit
```
