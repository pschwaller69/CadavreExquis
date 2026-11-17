# Nouveautés

La version en ligne s'affiche en haut à droite de l'admin, en pied de page
(avec le commit et la date du déploiement) et sur https://cadavreexquis.schwaller.art/version.php

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
