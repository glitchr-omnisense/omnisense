---
title: Conventions
order: 3
---

# Conventions

Ce que tous les paquets ont en commun : leurs noms, leurs versions, leur
licence, et ce que leur README dit de leur vérification.

## Les noms

Un paquet se nomme par son nom Composer, jamais par son dossier :
`glitchr/omnistate`, pas « omnistate/core ». Le cœur et le paquet propre de
chaque famille passent sous le vendeur `omniscient/` sur Packagist
(`omniscient/omnilab` d'abord, les autres à leur tour) ; l'ancien nom
`glitchr/…` reste déclaré en `replace`, si bien qu'un projet qui le requiert
encore reçoit le nouveau paquet. Les autres noms se lisent « la famille / un
de ses membres » : `omnitrade/stripe` est un fournisseur d'un service
extérieur, `omnibase/marketplace` une structure préparée — une structure
toute prête pour un métier, assemblée sur le socle, et facultative : rien
dans le cœur ni dans les familles n'en dépend.

Une famille n'existe que pour une question posée à **au moins deux services
réels**, et qu'aucune bibliothèque existante ne couvre déjà. Un nom
seulement annoncé est cité à part sur la page d'accueil, sans fiche.

## Les versions

Chaque paquet garde l'historique de ses versions dans son dépôt git, et la
fiche du paquet le montre : ses étiquettes, ses branches et leurs derniers
commits, lus par `git/git-bundle`.

Le [sélecteur d'installation](/install) en tire la contrainte à écrire dans
un `composer.json` :

| Le dépôt a… | Canal stable | Canal développement |
|---|---|---|
| une version étiquetée `v1.4.2` | `^1.4` (la version mineure) | la branche de développement |
| seulement une branche `1.x` | `1.x-dev` | `1.x-dev` |
| seulement une branche `main` | `dev-main` | `dev-main` |

Dès qu'une contrainte prend une version de développement, le projet a besoin
de `"minimum-stability": "dev"` — avec `"prefer-stable": true` pour que tout
le reste reste en versions stables. Un paquet qui n'est pas encore sur
Packagist est déclaré par un dépôt `vcs`. Le socle s'installe avec son
greffon Composer, `glitchr/base-plugin`, qu'il faut autoriser.

Les branches de version se nomment `1.x`, `2.x` ; la documentation d'un
paquet a une version par branche.

## La licence

Tous les paquets sont publiés sous licence **MIT**. Les versions parues plus
tôt sous LGPL-3.0-or-later restent disponibles sous cette licence ; le
README de chaque paquet le dit (« MIT since… »).

## « Vérifié en réel »

L'état affiché sur la fiche d'un paquet vient de son README, jamais d'une
supposition : *vérifié* quand il dit avoir été essayé contre le vrai service
(« Verified against the real service on… »), *non vérifié* quand il dit que
non, *en partie* quand il dit les deux. La phrase lue est citée telle quelle
sur la fiche, pour que le lecteur juge.

## Les dépendances d'une famille

Un paquet requiert ce sans quoi son code ne tourne pas ; le facultatif va
dans `suggest`. Jamais `symfony/framework-bundle`, `dependency-injection`,
`config`, `http-kernel`, Doctrine ni Twig dans le `require` d'une famille.
Le pont Symfony reste dans le paquet du cœur, sous `Bridge/Symfony`, ses
composants non requis.
