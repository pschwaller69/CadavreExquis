# Nouveautés

La version en ligne s'affiche en haut à droite de l'admin, en pied de page
(avec le commit et la date du déploiement) et sur https://cadavreexquis.schwaller.art/version.php

## 1.12.0 – 18/11/2026
- **File d'envoi** : invitations, points de file d'attente et mail de fin sont mis en file et envoyés
  par la tâche cron, 1 mail par passage (`cron_mails_par_passage`) ; la tâche cron passe toutes les 5 minutes
- Page Mails : état de la tâche cron, mails en attente, fin estimée, envoyer le suivant / annuler / réessayer les erreurs
- Les mails individuels (c'est ton tour, merci, bienvenue, relance) restent immédiats

## 1.11.0 – 18/11/2026
- Règle des clubs renforcée : jamais deux photos du même club à la suite, sauf si tous les inscrits en attente sont du même club
  - on évite d'abord le club des 2 dernières photos, puis au minimum celui de la dernière
  - seules les photos réellement déposées comptent (un participant passé ne bloque plus son club)
  - « Indépendant » n'est pas considéré comme un club
  - une nouvelle inscription va dans la chaîne en attente où son club ne suit pas le même club
  - alerte si un placement manuel met deux personnes du même club à la suite

## 1.10.0 – 17/11/2026
- Moins de spam : invitations envoyées par lots (20 par clic, `invitation_lot`) avec une pause de 4 s
  entre chaque mail (`mail_pause_sec`), y compris pour le point de file d'attente et le mail de fin
- En-tête standard de désinscription (List-Unsubscribe) sur les mails envoyés à beaucoup de monde

## 1.9.1 – 17/11/2026
- Inscription : le champ « Nom du club » n'apparaît plus que si « Autre club… » est choisi

## 1.9.0 – 17/11/2026
- Présentation et export PDF au format **16/9** (13,33 × 7,5 pouces, comme PowerPoint) ; diaporama inchangé
- Dépôt : texte facultatif « Ta démarche » (600 caractères), affiché sous la photo précédente dans la présentation,
  modifiable par l'admin sur la page de la chaîne, inclus dans l'export CSV

## 1.8.0 – 17/11/2026
- Liste des clubs préenregistrée (clubs de 2026) proposée dans le formulaire d'inscription
- « Autre club… » à l'inscription ajoute automatiquement le club à la liste
- Page **Clubs** : ajouter, renommer, fusionner deux écritures, masquer du formulaire, supprimer

## 1.7.1 – 17/11/2026
- L'admin (« Cadavre Admin ») et le suivi (« Suivi Cadavre ») s'installent comme deux applications distinctes
  sur le même téléphone (portées séparées : /admin/ et /suivi.php)

## 1.7.0 – 17/11/2026
- Suppression d'une chaîne terminée (page de la chaîne ou page Édition), avec ses photos et ses participants
- Page de suivi : uniquement les éditions et les chaînes en cours

## 1.6.0 – 17/11/2026
- Page publique **suivi.php** (sans mot de passe, sans nom ni photo) : chiffres clés, avancement de chaque
  chaîne, photos par semaine, clubs participants ; installable sur téléphone comme application séparée
- Liens vers le suivi depuis le formulaire d'inscription et le tableau de bord

## 1.5.0 – 17/11/2026
- Rappel sur le tableau de bord quand la photo de départ manque (ajout ou remplacement dans Édition)
- Suppression d'une édition (essais) avec ses participants et ses photos, après saisie de son nom

## 1.4.1 – 17/11/2026
- La redirection vers la connexion ne dépend plus de base_url
- Alerte dans l'admin et à l'installation si base_url (config.php) ne correspond pas à l'adresse du site

## 1.4.0 – 17/11/2026
- Mail « point sur la file d'attente » : nombre de personnes avant soi + estimation indicative,
  envoyé automatiquement tous les 14 jours (`point_attente_jours`) et via un bouton du tableau de bord
- Tableau de bord : rang, tour estimé et date du dernier point pour chaque inscrit en attente

## 1.3.1 – 17/11/2026
- Remplacement des dernières mentions de cadavre.ascs.fr par cadavreexquis.schwaller.art (README, cron)

## 1.3.0 – 17/11/2026
- Numéro de version affiché dans l'admin, sur la page de connexion et dans `version.php`
- Commit et date du déploiement écrits automatiquement par `.cpanel.yml`

## 1.2.0 – 17/11/2026
- Page **Invitations** : import des anciens participants, envoi groupé du lien d'inscription, suivi, « ne plus inviter »
- Mise à jour automatique de la base à la connexion admin (plus besoin de relancer install.php)

## 1.1.0 – 17/11/2026
- Application installable sur téléphone (PWA) : icône, plein écran, raccourcis, page hors ligne
- Admin adapté aux petits écrans ; session conservée 30 jours
- Déploiement cPanel (`.cpanel.yml`)

## 1.0.0 – 17/11/2026
- Première version : inscription publique, dépôt des photos par lien personnel, enchaînement et mails
  automatiques (Gmail), relances, présentation / diaporama, exports
