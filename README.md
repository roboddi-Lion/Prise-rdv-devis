# Lion Rénovation – Prise de RDV Devis

Plugin WordPress permettant aux visiteurs du site de réserver eux-mêmes une
**visite technique / devis**, en fonction des disponibilités réelles des
agendas **Google Calendar** de Romain Bonnevie (gérant) et Emmanuel Bonnevie
(co-directeur).

Ce plugin est indépendant du plugin **« Prise de RDV Dépannage & Entretien »**
(connecté à InterFast) : il couvre uniquement les rendez-vous de visite
technique / devis, jamais le dépannage.

## Routage selon le type de projet

| Type de projet choisi par le client | Agenda(s) consulté(s) |
|---|---|
| Rénovation énergétique, Cuisine, Rénovation complète, Locaux professionnels | Romain uniquement |
| Chauffage / Climatisation (chauffage, chaudière, climatisation, PAC) | Emmanuel uniquement |
| Salle de bain | Les deux — premier créneau libre entre les deux agendas |

Quand les deux agendas sont éligibles, le plugin propose le premier créneau
où **au moins l'un des deux** est libre, et assigne le rendez-vous à celui
qui est effectivement disponible **sans jamais l'indiquer au client**.

Ce tableau (labels, descriptions, durées, préavis, agendas consultés) est
entièrement modifiable dans **Réglages > Prise de RDV Devis Lion**.

## Installation

1. Copiez le dossier `lion-rdv-devis/` dans `wp-content/plugins/` de votre
   site WordPress (ou zippez-le et installez-le depuis Extensions > Ajouter).
2. Activez l'extension **« Lion Rénovation - Prise de RDV Devis »**.
3. Créez un projet Google Cloud et connectez les deux agendas (voir
   ci-dessous).
4. Allez dans **Réglages > Général > Fuseau horaire** et vérifiez qu'il est
   réglé sur **« Paris »** (pas un décalage UTC fixe type « UTC+1 » ou
   « UTC+2 », qui ne suit pas le changement d'heure été/hiver). Les
   horaires d'ouverture et le calcul des créneaux libres se basent sur ce
   réglage : s'il est resté sur UTC, les créneaux affichés seront décalés
   d'1h ou 2h par rapport à l'heure réelle, et les événements déjà présents
   dans les agendas Google Calendar ne bloqueront pas les bons créneaux. La
   page **Réglages > Prise de RDV Devis Lion** affiche un avertissement
   tant que ce réglage reste sur UTC.
5. Allez dans **Réglages > Prise de RDV Devis Lion** et vérifiez :
   - le tableau **« Types de projet proposés »** : les lignes préconfigurées
     selon le tableau de routage ci-dessus
   - les **horaires d'ouverture** (préremplis : lun-jeu 8h-12h/14h-18h,
     ven 8h-12h/14h-17h, fermé sam/dim)
   - l'**email de notification interne** (par défaut : l'email admin du
     site)
6. Ajoutez le shortcode `[lion_rdv_devis]` sur la page « Prise de rendez-vous
   devis » de votre site.

## Connexion Google Calendar (OAuth2 individuel)

Ce plugin utilise l'**autorisation OAuth2 individuelle** : Romain et
Emmanuel autorisent chacun l'accès à leur propre agenda avec leur compte
Google personnel, plutôt qu'un compte de service avec délégation à l'échelle
du domaine. Ce choix évite de dépendre d'un accès administrateur Google
Workspace (l'entreprise peut très bien utiliser des comptes Gmail standards)
; si Lion Rénovation dispose d'un accès administrateur Google Workspace et
préfère une délégation à l'échelle du domaine (plus robuste, sans
ré-autorisation périodique), voir la section **« Alternative : compte de
service »** en bas de ce document.

### 1. Créer le projet Google Cloud

1. Sur [console.cloud.google.com](https://console.cloud.google.com), créez
   un projet dédié (ex. « Lion Rénovation - RDV Devis »).
2. Dans **API et services > Bibliothèque**, activez l'API **« Google
   Calendar API »**.
3. Dans **API et services > Écran de consentement OAuth** :
   - Type d'utilisateur : **Externe** suffit (pas besoin d'un espace
     Google Workspace pour ce type d'application).
   - Renseignez le nom de l'application, l'email de support et l'email de
     contact développeur.
   - Ajoutez le scope `.../auth/calendar` (accès complet à l'agenda,
     nécessaire pour lire les disponibilités ET créer des événements).
   - Tant que l'application reste en mode **« Test »** (pas de validation
     Google requise pour un usage interne à 2 comptes), ajoutez les adresses
     Gmail/Workspace de Romain et Emmanuel comme **utilisateurs test** — sans
     quoi Google refusera leur connexion.
4. Dans **API et services > Identifiants**, créez un **ID client OAuth** de
   type **« Application Web »**, et ajoutez comme **URI de redirection
   autorisée** exactement l'URL affichée dans **Réglages > Prise de RDV
   Devis Lion** (de la forme
   `https://votre-site.fr/wp-admin/admin-post.php?action=lion_rdv_devis_oauth_callback`).
5. Copiez l'**ID client** et le **secret client** obtenus dans les champs
   correspondants de **Réglages > Prise de RDV Devis Lion**, puis
   enregistrez.

### 2. Connecter les deux agendas

Dans **Réglages > Prise de RDV Devis Lion**, section **« Agendas
connectés »** :

1. Romain clique sur **« Connecter »** en face de son nom, se connecte avec
   son compte Google et autorise l'accès à son agenda.
2. Emmanuel fait de même avec son propre compte Google, sur sa propre ligne.

Une fois connecté, chaque ligne affiche l'adresse Gmail/Workspace du compte
autorisé. Le bouton **« Déconnecter »** révoque la connexion stockée côté
WordPress (à utiliser par exemple si Romain ou Emmanuel change de compte
Google).

Le bouton **« Vérifier l'agenda »** interroge Google Calendar en direct et
affiche la liste des créneaux occupés détectés sur les 14 prochains jours
pour cette personne. Comparez cette liste avec le contenu réel de son
agenda Google : si un rendez-vous existant n'y apparaît pas, la cause est
côté Google (mauvais compte connecté, événement marqué « Disponible » au
lieu de « Occupé », ou agenda secondaire non couvert — seul l'agenda
principal du compte connecté est consulté) plutôt que côté plugin.

### Stockage des jetons

Les jetons de rafraîchissement et d'accès (`refresh_token` / `access_token`)
sont chiffrés (AES-256-CBC, clé dérivée des sels WordPress `AUTH_KEY`) avant
d'être enregistrés dans la table `wp_options`, et déchiffrés à la volée
uniquement au moment d'appeler l'API Google. Le `refresh_token` n'expire pas
(sauf révocation manuelle par Romain/Emmanuel sur
[myaccount.google.com/permissions](https://myaccount.google.com/permissions)
ou déconnexion depuis les réglages) : l'`access_token`, lui, est rafraîchi
automatiquement toutes les ~heure sans action de leur part.

## Fonctionnement

1. Le visiteur choisit son type de projet (Rénovation énergétique, Cuisine,
   Chauffage, Salle de bain...).
2. Le plugin détermine le ou les agendas concernés, interroge l'API Google
   Calendar (**FreeBusy**) sur les horaires d'ouverture configurés, et
   calcule les créneaux encore libres. Si les deux agendas sont concernés,
   les disponibilités sont fusionnées : un créneau est proposé dès qu'au
   moins l'un des deux est libre.
3. Le visiteur choisit un jour puis un horaire, renseigne ses coordonnées
   **et répond à quelques questions courtes propres à son type de projet**
   (voir ci-dessous), puis valide.
4. Le plugin revérifie que le créneau est toujours libre (pour limiter les
   doubles réservations), détermine qui est effectivement disponible, puis
   crée l'événement dans l'agenda Google Calendar de la bonne personne, avec
   les coordonnées du client dans la description. Le client est ajouté comme
   participant (`attendee`) : **Google Calendar lui envoie automatiquement
   une invitation par email**, sans action supplémentaire du plugin.
5. Une notification interne (avec le nom de la personne effectivement
   assignée) est envoyée à l'adresse configurée. En cas d'échec de création
   de l'événement, le client est informé qu'il sera recontacté, et la
   réservation est journalisée avec l'erreur dans **Réglages > Prise de RDV
   Devis Lion > Voir les réservations récentes** pour suivi manuel.
6. Écran de confirmation identique au plugin de dépannage.

## Questions "entonnoir" par type de projet

En plus des coordonnées, l'étape finale pose quelques questions courtes
pour dégrossir le dossier avant la visite technique — reprenant l'esprit
du formulaire de contact déjà présent sur le site (budget, délai...) :

- **Communes à tous les projets** : budget estimatif, délai souhaité.
- **Spécifiques au type de projet**, par exemple :
  - *Rénovation énergétique* : surface, année de construction, chauffage
    actuel, accompagnement souhaité pour les aides (MaPrimeRénov', CEE...).
  - *Cuisine* : surface, configuration (ouverte/fermée), ampleur des
    travaux.
  - *Rénovation complète* : surface, nombre de pièces, ampleur, occupation
    pendant les travaux.
  - *Locaux professionnels* : type d'activité, surface, date d'ouverture
    souhaitée.
  - *Chauffage / Climatisation* : nature du besoin (neuf/remplacement),
    type d'équipement, nombre de pièces à équiper.
  - *Salle de bain* : surface, douche ou baignoire, accès PMR.

Aucune de ces questions n'est obligatoire par défaut (comme sur le
formulaire de contact du site), pour ne pas ajouter de friction avant la
prise de rendez-vous.

Les réponses apparaissent :
- dans la description de l'événement créé sur l'agenda Google Calendar de
  la personne qui se rend chez le client ;
- dans l'email de notification interne ;
- dans **Réglages > Prise de RDV Devis Lion > Voir les réservations
  récentes**, colonne « Détails du projet ».

Cette liste de questions est définie dans le code
(`Lion_RDV_Devis_Settings::common_questions()` et la clé `'questions'` de
chaque service dans `default_services()`, dans
`includes/class-lion-rdv-devis-settings.php`) plutôt que dans
l'administration WordPress — demandez à Claude d'en ajouter, retirer ou
reformuler à tout moment.

## Anti-spam

Le formulaire de réservation étant public, il inclut un champ piège
(honeypot) invisible ainsi qu'une limite de 5 réservations par heure et par
adresse IP.

## Personnalisation visuelle

Le style est calé sur la charte actuelle de **lion-renovation.fr** (bleu
`#0d4995`, polices Outfit/Montserrat, rayon 8px, ombres teintées bleu,
petite barre de marque ambre/terracotta/bleu/marine), plutôt que sur des
couleurs génériques. Le widget est présenté comme un encart blanc arrondi
avec ombre, sur le même modèle que le formulaire de contact existant du
site (`.request-form`), et les messages de succès/erreur reprennent les
teintes déjà utilisées ailleurs sur le site (bleu pour la confirmation,
terracotta pour l'alerte).

Le CSS réutilise **exactement les mêmes noms de variables** que le plugin
« Prise de RDV Dépannage & Entretien » (`--lion-rdv-primary`,
`--lion-rdv-primary-dark`, `--lion-rdv-text`, `--lion-rdv-muted`,
`--lion-rdv-border`, `--lion-rdv-bg`, `--lion-rdv-bg-soft`,
`--lion-rdv-radius`), définies en haut de `assets/css/devis-widget.css` :
si la charte du site venait à changer, reportez les nouvelles valeurs de
`:root` dans ce fichier (et dans celui du plugin dépannage, pour que les
deux widgets restent visuellement identiques).

## Alternative : compte de service avec délégation à l'échelle du domaine

Si Lion Rénovation dispose d'un accès **administrateur Google Workspace**,
une délégation à l'échelle du domaine évite à Romain et Emmanuel de
ré-autoriser périodiquement l'accès et centralise la configuration côté
admin. Cette approche demande cependant de modifier le client Google
(`class-lion-rdv-devis-google-client.php`) pour signer les requêtes avec un
compte de service (JWT) plutôt que le flux OAuth2 "Application Web" utilisé
ici, et un administrateur Workspace doit déléguer les scopes Calendar au
compte de service dans la console d'administration
(admin.google.com > Sécurité > Contrôle des API > Délégation à l'échelle du
domaine). Non implémenté par défaut dans ce plugin car cela suppose un accès
administrateur Workspace qui n'est pas garanti ; à évaluer séparément si
souhaité.
