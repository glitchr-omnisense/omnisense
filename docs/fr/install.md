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
| le paquet propre de chaque famille (`omnisense/omni<domaine>`) | 1.0.x ; `omnisense/omnibus` 2.0.0, `omnisense/omnitrade` 1.1.1, `omnisense/omnisign` 1.2.1, `omnisense/omnibill` 1.3.0 |
| les fournisseurs (`omni<domaine>/<service>`) | 1.0.x ; les transporteurs `omnibus/*` 2.0.0 |
| les ponts | `omnisense/omnibridge` 1.0.1, `omnibridge/symfony` 1.1.1, `omnibridge/laravel` 1.1.0, `omnibridge/static` 1.1.0, `omnibridge/composer`, `twig`, `eloquent` et `react` 1.0.0, `omnibridge/doctrine` 1.0.1 |
| le cœur | `omnisense/omnibase` 5.1.1 |
| les structures préparées (`omnibase/*`) | 1.0.x ; `omnibase/docs` 2.0.0 |

Le tableau en fin de page donne les paquets de chaque famille un par un,
depuis le catalogue : il est lu sur Packagist à chaque import.

## Le cœur

`omnisense/omnibase` vient avec son greffon Composer, `omnibridge/composer`
(les stubs et la seule retouche que le cœur déclare), qu'il faut autoriser,
et le pont du cadre hôte :

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase omnibridge/symfony
```

Composer écrit `^5.1` pour le cœur. Les structures préparées
(`omnibase/blog`, `omnibase/marketplace`…) s'installent de la même façon,
chacune par son nom : `composer require omnibase/blog`.

## Pas encore stable

Dit tel quel aujourd'hui :

- Les clients de serveur de jeu d'omniplay (.NET, Godot, Mirror, Netcode for
  GameObjects, Node) ne sont pas des paquets Composer : ceux de Unity
  s'installent par leur adresse Git, et aucun n'est encore sur npm ni NuGet.
- `omniauth/oauth` est abandonné : la connexion par fournisseur d'identité
  appartient à `omnisense/omniauth`, à requérir à sa place.
- `omnibase/lawyer` et `omnibase/notary` sont abandonnés au profit
  d'`omnibase/legal`, qui réunit les professions juridiques, un profil chacune.

Une branche de développement sert à essayer ce qui n'est pas encore publié :
nommée dans la commande (`omnibridge/static:1.x-dev`), Composer l'accepte pour
ce seul paquet, et le projet garde sa `minimum-stability`.

## Les paquets de chaque famille
