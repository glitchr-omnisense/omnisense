---
title: Installer
description: Les familles, leurs fournisseurs et les ponts s'installent avec la minimum-stability par défaut de Composer ; la version de chaque famille, et ce qui n'est pas encore stable.
order: 1
---

# Installer

Les familles, leurs fournisseurs et les ponts ont des versions stables sur
Packagist, sauf les quelques-uns listés sous [Pas encore stable](#pas-encore-stable). Un projet garde la `minimum-stability` par défaut de Composer
(`stable`) : ni `@dev` ni branche de développement, et `composer require`
sans version écrit la contrainte de lui-même (`^1.0`, `^2.0`…).

## Une famille et un fournisseur

Une famille et le service qui répond — ici le paiement, avec PayPal :

```sh
composer require omnisense/omnitrade omnitrade/paypal
```

Composer écrit `^1.0` pour les deux, et installe `omnisense/contracts`
(`^1.0`), sur lequel chaque famille est construite. Le manuel de chaque
famille s'ouvre sur l'usage en PHP nu, puis sur Symfony.

Les lignes de versions aujourd'hui :

| Paquets | Version |
|---|---|
| `omnisense/contracts` | 1.0.0 |
| le paquet propre de chaque famille (`omnisense/omni<domaine>`) | 1.0.0 ; `omnisense/omnibus` 2.0.0, `omnisense/omnisign` 1.2.0, `omnisense/omnibill` 1.3.0 |
| les fournisseurs (`omni<domaine>/<service>`) | 1.0.0 ; les transporteurs `omnibus/*` 2.0.0 |
| les ponts | `omnisense/omnibridge` 1.0.1, `omnibridge/symfony` 1.1.0, `omnibridge/composer` 1.0.0, `omnibridge/doctrine` 1.0.1 |

Le tableau en fin de page donne les paquets de chaque famille un par un,
depuis le catalogue : il est lu sur Packagist à chaque import.

## Le cœur

`omnisense/omnibase` 5.0.0 est la version stable du cœur, mais elle requiert
encore trois paquets à leur branche de développement : `omnibridge/composer`,
`omnisense/omnibridge` et `omnisense/omnidrive`. La branche de développement
d'une dépendance n'est pas acceptée dans un projet stable : jusqu'à
`omnisense/omnibase` 5.1.0, les trois se nomment dans la même commande (une
contrainte écrite dans le `composer.json` racine laisse ce seul paquet
prendre sa branche) :

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase:^5.0 omnibridge/symfony \
    omnibridge/composer:1.x-dev omnisense/omnibridge:1.x-dev omnisense/omnidrive:1.x-dev
```

`omnibridge/composer` est le greffon Composer du cœur (les stubs et la seule
retouche que le cœur déclare) : il faut l'autoriser. Avec la 5.1.0,
`composer require omnisense/omnibase omnibridge/symfony` suffit.

Les structures préparées (`omnibase/blog`, `omnibase/marketplace`…) n'ont
pas encore de version, et elles requièrent le cœur, `omnibridge/symfony` et
l'une l'autre à des branches de développement : elles ne s'installent pas
avec la minimum-stability par défaut tant qu'elles ne sont pas publiées.

## Pas encore stable

Dit tel quel aujourd'hui :

- La famille omnidocs (`omnisense/omnidocs` et `omnidocs/markdown`, `php`,
  `jsdoc`, `sphinx`, `doxygen`) et la famille omnihub (`omnisense/omnihub`,
  `omnihub/github`) n'ont pas encore de version.
- `omnisearch/typesense` attend la 2.0.0 de `glitchr/typesense-bundle`, le
  bundle Symfony sur lequel il repose.
- `omnisense/omnibase` 5.1.0 et `omnibridge/laravel` 1.1.0 sont en
  préparation. `omnibridge/laravel` 1.0.0 requiert le cœur : il bute donc
  sur le même mur que le cœur d'ici là.
- `omnibridge/static` et `omnibridge/react` n'ont pas encore de version :
  `composer require omnibridge/static:1.x-dev`.
- Les clients de serveur de jeu d'omniplay (.NET, Godot, Mirror, Netcode for
  GameObjects, Node) ne sont pas des paquets Composer : ceux de Unity
  s'installent par leur adresse Git, et aucun n'est encore sur npm ni NuGet.
- `omniauth/oauth` est abandonné : la connexion par fournisseur d'identité
  appartient à `omnisense/omniauth`, à requérir à sa place.

Un paquet sans version se requiert à sa branche de développement, nommée
dans la commande (`omnibridge/static:1.x-dev`) : Composer l'accepte pour ce seul
paquet, et le projet garde sa `minimum-stability`. Ses propres dépendances
doivent alors être stables, ou nommées de la même façon :
`composer require omnisense/omnidocs:1.x-dev omnidocs/php:1.x-dev`.

## Les paquets de chaque famille
