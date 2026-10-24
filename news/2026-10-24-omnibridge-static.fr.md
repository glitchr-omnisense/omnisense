---
title: omnibridge/static — un export statique pour toute application
summary: La famille omnibridge gagne un exporteur générique — les pages publiques d'une application, rendues à la construction par son propre code, pour tout hébergement statique.
tags: [omnibridge, version]
---

`omnibridge/static` énumère les adresses publiques d'une application —
d'une liste, des routes de son routeur et des valeurs que prennent leurs
paramètres, ou de son plan du site —, rend chacune à la construction avec
le code de l'application, et écrit un dossier que tout hébergement statique
ou CDN sert sans PHP.

Ce que l'export écrit : les pages comme un hébergement statique les sert,
les fichiers dont elles ont besoin (le build de l'empaqueteur, les fichiers
publics vers lesquels elles pointent), les redirections (`_redirects` et
`redirects.json`), `404.html`, `sitemap.xml` avec les langues de chaque page,
et `omnibridge-static.json` — la somme de contrôle de chaque fichier, triée
et sans date, pour que deux constructions se comparent. Les liens internes
cassés, les sorties en double et les pages qui ne peuvent être statiques
sont signalés ; les pages qui doivent rester dynamiques (connexion,
formulaires envoyés) sont nommées et leurs liens envoyés à l'hôte dynamique.

Il ne requiert que PHP 8.2 ; les commandes Symfony et Laravel sont de minces
enveloppes dans leurs ponts. Ses deux premiers utilisateurs : Les Ringo
Stars, et ce site.
