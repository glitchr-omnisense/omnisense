---
title: Six nouvelles familles, GitHub Pages, des manuels par série
summary: Le site est publié sur GitHub Pages par omnibridge/static ; omnifund, omnidns, omniticket, omnireach, omnifilm et omnigame entrent au catalogue ; omnidrive gagne quatre fournisseurs ; flowjs 1.5.2 ; le manuel d'omnibase pour 4.x et 3.x côte à côte.
tags: [site, omnibridge, version]
---

**GitHub Pages.** `omnibridge/static` a une cible GitHub Pages : les pages
sont rendues à la racine et chaque adresse interne est déplacée sous le
dossier où le site est servi (ici `/omniscient/`), avec un fichier
`.nojekyll`, un `CNAME` pour un domaine personnalisé, les redirections
écrites en petites pages, et une construction en échec pour toute adresse
restée hors du dossier. Ce site est construit ainsi par son propre
workflow : une pull request construit tout le site en aperçu ; une fusion
dans `main` le construit deux fois (les deux dossiers doivent être
identiques), le vérifie dans un Chrome sans interface et le publie. Cette
actualité a suivi ce chemin.

**Six nouvelles familles.** Chacune est un paquet cœur de contrats, ses
fournisseurs étant des paquets à part, sous tout hôte PHP :

- **omnifund** — qui finance un projet : GitHub Sponsors, Open Collective,
  Liberapay.
- **omnidns** — un nom de domaine là où il est enregistré (disponibilité,
  enregistrement, renouvellement, serveurs de noms, enregistrements de
  zone) : OVH, Gandi, Cloudflare, Scaleway.
- **omniticket** — des billets vendus ailleurs (événements, participants,
  un code de billet vérifié à l'entrée) : Weezevent, Billetweb, HelloAsso,
  Eventbrite, Ticketmaster.
- **omnireach** — ce que les outils extérieurs savent de l'audience d'un
  site : Search Console, Bing Webmaster Tools, Matomo, Plausible, Google
  Analytics 4.
- **omnifilm** — la fiche d'un film, d'une série ou d'un épisode : TMDB,
  OMDb, TheTVDB, Trakt, Wikidata.
- **omnigame** — la présence d'un jeu sur les boutiques, en lecture seule :
  Steam, GOG, itch.io, IGDB, RAWG.

**omnidrive** gagne quatre fournisseurs — WebDAV (Nextcloud, ownCloud, tout
serveur), Azure Blob Storage, Dropbox et Google Drive — à côté du dossier
local, de S3, SFTP et FTP. OneDrive attend un adaptateur Flysystem
maintenu.

**flowjs 1.5.2.** Un arrêt au plus d'un quart d'écran plus haut que l'écran
(la nouvelle option `shortEnd`) ne se pose qu'en haut : ni une touche ni un
défilement qui s'arrête ne s'immobilise quelques pixels plus loin, à sa
fin. La page d'accueil de ce site lit ainsi ses sections.

**Des manuels par série.** Un paquet qui maintient plusieurs séries publie
chacune : le manuel d'omnibase se lit pour 4.x, la série actuelle, et pour
3.x, chacune à la tête de sa branche. Un sélecteur sur chaque page de manuel
mène à la même page dans l'autre série ; les pages d'une série plus
ancienne disent laquelle est actuelle et restent hors des index des moteurs
de recherche.
