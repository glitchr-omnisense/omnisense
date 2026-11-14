---
title: Install
description: The families, their providers and the bridges install with Composer's default minimum-stability; the release of each family, and what is not stable yet.
order: 1
---

# Install

The families, their providers and the bridges have stable releases on
Packagist, but for the few listed under [Not stable yet](#not-stable-yet). A project keeps Composer's default `minimum-stability`
(`stable`): no `@dev`, no development branch, and `composer require` with
no version writes the constraint by itself (`^1.0`, `^2.0`...).

## A family and a provider

A family and the service that answers - here payments, with PayPal:

```sh
composer require omnisense/omnitrade omnitrade/paypal
```

Composer writes `^1.0` for both, and installs `omnisense/contracts`
(`^1.0`), which every family builds on. Each family's manual starts with
plain PHP, then Symfony.

The release lines today:

| Packages | Release |
|---|---|
| `omnisense/contracts` | 1.0.0 |
| the families' own packages (`omnisense/omni<domain>`) | 1.0.0; `omnisense/omnibus` 2.0.0, `omnisense/omnitrade` 1.1.0, `omnisense/omnisign` 1.2.0, `omnisense/omnibill` 1.3.0 |
| the providers (`omni<domain>/<service>`) | 1.0.0; the carriers of `omnibus/*` 2.0.0 |
| the bridges | `omnisense/omnibridge` 1.0.1, `omnibridge/symfony` 1.1.0, `omnibridge/composer` 1.0.0, `omnibridge/doctrine` 1.0.1 |

The table at the end of this page gives each family's packages one by one,
from the catalogue: it is read from Packagist at each import.

## The core

`omnisense/omnibase` 5.0.0 is the core's stable release, but it still
requires three packages at their development branch: `omnibridge/composer`,
`omnisense/omnibridge` and `omnisense/omnidrive`. A dependency's development
branch is not accepted in a stable project, so until `omnisense/omnibase`
5.1.1 the three are named in the same command (a constraint written in the
root `composer.json` lets that package alone take its branch):

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase:^5.0 omnibridge/symfony \
    omnibridge/composer:1.x-dev omnisense/omnibridge:1.x-dev omnisense/omnidrive:1.x-dev
```

`omnibridge/composer` is the core's Composer plugin (the stubs and the one
patch the core declares): it must be allowed. With 5.1.1,
`composer require omnisense/omnibase omnibridge/symfony` is enough.

The prepared structures (`omnibase/blog`, `omnibase/marketplace`...) have
no release on Packagist yet, and they require the core, `omnibridge/symfony` and one
another at development branches: they do not install with the default
minimum-stability until they are released.

## Not stable yet

Said as it is today:

- The omnidocs family (`omnisense/omnidocs` and `omnidocs/markdown`,
  `php`, `jsdoc`, `sphinx`, `doxygen`) and the omnihub family
  (`omnisense/omnihub`, `omnihub/github`) have no release on Packagist yet.
- `omnisense/omnibase` 5.1.1 is not on Packagist yet, and
  `omnibridge/laravel` 1.1.0 is being prepared. `omnibridge/laravel` 1.0.0
  requires the core, so it meets the same wall as the core until then.
- `omnibridge/static` and `omnibridge/react` have no release on Packagist
  yet: `composer require omnibridge/static:1.x-dev`.
- omniplay's game-server clients (.NET, Godot, Mirror, Netcode for
  GameObjects, Node) are not Composer packages: the Unity ones install by
  their Git address, and none is on npm or NuGet yet.
- `omniauth/oauth` is abandoned: its sign-in through identity providers is
  `omnisense/omniauth`'s own, require that instead.

A package without a release is required at its development branch, named
in the command (`omnibridge/static:1.x-dev`): Composer accepts it for that
package alone, and the project keeps its `minimum-stability`. Its own
dependencies must then be stable, or named the same way:
`composer require omnisense/omnidocs:1.x-dev omnidocs/php:1.x-dev`.

## Each family's packages
