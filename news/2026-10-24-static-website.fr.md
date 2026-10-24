---
title: Ce site est désormais statique
summary: Guides, actualités, catalogue et manuel de chaque paquet, construits depuis des fichiers et servis sans application côté serveur.
tags: [site]
---

Le site Omniscient est désormais un ensemble de pages statiques, exportées
par `omnibridge/static` depuis la même application Symfony sur
`omniscient/omnibase`. Ce qui change pour le lecteur :

- **Le contenu, ce sont des fichiers.** Les guides (`docs/`), les
  actualités (`news/`), le catalogue (`catalog/`, un fichier par famille) et
  la liste des manuels (`config/documentation.yaml`) sont dans le dépôt du
  site ; une correction est une demande de fusion, et chaque demande de
  fusion construit un aperçu.
- **Le manuel de chaque paquet est épinglé.** Il est lu dans le dossier
  `docs/` du paquet à un commit exact, inscrit dans le manifeste, et la page
  dit lequel. Chaque paquet a ses propres séries ; `/docs/<paquet>/latest`
  redirige vers la série actuelle.
- **La recherche se fait dans votre navigateur**, depuis un index JSON écrit
  avec le site.
- **Aucun compte, aucun cookie.** Suivre un paquet était la seule raison de
  se connecter ; le « Watch » de GitHub le fait mieux.

Le sélecteur d'installation, qui composait les commandes sur le serveur, est
retiré pour l'instant : la page de chaque paquet donne sa ligne
`composer require`, et les guides expliquent les contraintes.
