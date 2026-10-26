---
title: Conventions
description: Noms, versions et contraintes Composer, la licence MIT, « vérifié en réel ».
order: 4
---

# Conventions

Ce que tous les paquets ont en commun : leurs noms, leurs versions, leur
licence, et ce que leur README dit de leur vérification.

## Les noms

Un paquet se nomme par son nom Composer, jamais par son dossier :
`omnisense/omnistate`, pas « omnistate/core ». Le cœur et le paquet propre
de chaque famille sont sous le vendeur `omnisense/` ; l'ancien nom
`glitchr/…` est déclaré en `replace`, si bien qu'un projet qui le requiert
encore reçoit le nouveau paquet. Les autres noms se lisent « la famille / un
de ses membres » : `omnitrade/stripe` est un fournisseur d'un service
extérieur, `omnibase/marketplace` une structure préparée — facultative :
rien dans le cœur ni dans les familles n'en dépend.

## Les versions

Chaque paquet garde son historique dans son dépôt git. Le développement se
fait sur des branches de version nommées `1.x`, `2.x` (`main` pour quelques
structures préparées) ; aucun paquet n'a encore de version étiquetée.

| Le dépôt a… | Contrainte |
|---|---|
| une branche `1.x` | `1.x-dev` |
| une branche `2.0` | `2.0.x-dev` |
| une branche `main` | `dev-main` |
| (plus tard) une version `v1.4.2` | `^1.4` |

Dès qu'une contrainte prend une version de développement, le projet a besoin
de `"minimum-stability": "dev"` — avec `"prefer-stable": true` pour que tout
le reste reste en versions stables. Une branche de développement requise par
un paquet que vous requérez ne se résout que si votre `composer.json` racine
la nomme aussi.

## Les versions de la documentation

Chaque paquet a ses propres séries de documentation — une par branche qu'il
maintient — et ce site lit chaque série à un commit exact, inscrit dans son
`config/documentation.yaml`. Une série passe à un commit plus récent par une
modification explicite de ce fichier, relue comme une autre : une branche
qui avance ne réécrit jamais d'elle-même une documentation publiée.
`/docs/<paquet>/latest` est une redirection vers la série par défaut, pas
une page à part.

## La licence

Tous les paquets sont publiés sous licence **MIT**. Les versions parues plus
tôt sous LGPL-3.0-or-later restent disponibles sous cette licence ; le
README de chaque paquet le dit (« MIT since… »).

## « Vérifié en réel »

L'état affiché sur la page d'un paquet vient de son README, jamais d'une
supposition : *vérifié* quand il dit avoir été essayé contre le vrai service
(« Verified against the real service on… »), *non vérifié* quand il dit que
non, *en partie* quand il dit les deux. La phrase lue est citée telle quelle
sur la page, pour que le lecteur juge.

## Les dépendances d'une famille

Un paquet requiert ce sans quoi son code ne tourne pas ; le facultatif va
dans `suggest`. Jamais `symfony/framework-bundle`, `dependency-injection`,
`config`, `http-kernel`, Doctrine ni Twig dans le `require` d'une famille.
Le pont Symfony reste dans le paquet de la famille, sous `Bridge/Symfony`,
ses composants non requis.
