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
| the families' own packages (`omnisense/omni<domain>`) | 1.0.x; `omnisense/omnibus` 2.0.0, `omnisense/omnitrade` 1.1.1, `omnisense/omnisign` 1.2.1, `omnisense/omnibill` 1.3.0 |
| the providers (`omni<domain>/<service>`) | 1.0.x; the carriers of `omnibus/*` 2.0.0 |
| the bridges | `omnisense/omnibridge` 1.0.1, `omnibridge/symfony` 1.1.1, `omnibridge/laravel` 1.1.0, `omnibridge/static` 1.1.0, `omnibridge/composer`, `twig`, `eloquent` and `react` 1.0.0, `omnibridge/doctrine` 1.0.1 |
| the core | `omnisense/omnibase` 5.1.1 |
| the prepared structures (`omnibase/*`) | 1.0.x; `omnibase/docs` 2.0.0 |

The table at the end of this page gives each family's packages one by one,
from the catalogue: it is read from Packagist at each import.

## The core

`omnisense/omnibase` comes with its Composer plugin, `omnibridge/composer`
(the stubs and the one patch the core declares), which must be allowed, and
the bridge of the host framework:

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase omnibridge/symfony
```

Composer writes `^5.1` for the core. The prepared structures
(`omnibase/blog`, `omnibase/marketplace`...) install the same way, each by
its name: `composer require omnibase/blog`.

## Not stable yet

Said as it is today:

- omniplay's game-server clients (.NET, Godot, Mirror, Netcode for
  GameObjects, Node) are not Composer packages: the Unity ones install by
  their Git address, and none is on npm or NuGet yet.
- `omniauth/oauth` is abandoned: its sign-in through identity providers is
  `omnisense/omniauth`'s own, require that instead.
- `omnibase/lawyer` and `omnibase/notary` are abandoned for
  `omnibase/legal`, which holds the legal professions, a profile each.

A development branch is for trying what is not released yet: named in the
command (`omnibridge/static:1.x-dev`), Composer accepts it for that package
alone, and the project keeps its `minimum-stability`.

## Each family's packages
