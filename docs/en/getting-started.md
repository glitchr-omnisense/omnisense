---
title: Getting started
description: The foundation and the optional prepared structures; installing a family or the core in a few lines.
order: 1
---

# Getting started

The Omni ecosystem is a set of PHP packages. It reads in two parts: the
**foundation**, which any application can take, and **prepared
structures**, which are optional.

| Part | Name | What it is |
|---|---|---|
| Foundation: the core | `omnisense/omnibase` | what a site starts from: accounts, back office, translations, pictures, forms, screens |
| Foundation: the families | `omnisense/omni<domain>` | one contract for one domain (parcels, payments, registers...), whatever service answers |
| Foundation: the providers | `omni<domain>/<service>` | one service fulfilling its family's contract: one package per service, [to search for](/packages) |
| Foundation: the bridges | `omnibridge/<target>` | the glue between the foundation and a framework, a storage, a view layer or a static host |
| Optional: the prepared structures | `omnibase/<trade>` | a ready-made structure for a trade - entities, screens, back office, translations, demo data - assembled on the core |

The prepared structures are **optional**: nothing in the core or the
families depends on them. A site takes one to go faster, or writes its own
on the core and the families.

A family and its providers **do not depend on the core**: they install in
any PHP application. The core runs under Symfony and Laravel through its
bridges (see [Compatibility](compatibility.md)).

## Install a family

A family and the provider that answers - here payments, with PayPal:

```sh
composer require omnisense/omnitrade:1.x-dev omnitrade/paypal:1.x-dev
```

```php
use Omnitrade\PayPal\PayPalGatewayFactory;
use Omnitrade\Registry;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new PayPalGatewayFactory(HttpClient::create())], [
    'paypal' => ['factory' => 'paypal', 'options' => ['client_id' => $id, 'secret' => $secret, 'sandbox' => true]],
]);
$gateway = $registry->get('paypal');
```

In Symfony, the family's bundle is in its own package (`Bridge/Symfony`):
declare it in `config/bundles.php` and its gateways under the family's key
(`omnitrade:`). Each family's manual starts with plain PHP, then Symfony.

> [!NOTE]
> No package has a stable release yet: each is required at its development
> branch (`1.x-dev`), and a project needs `"minimum-stability": "dev"` with
> `"prefer-stable": true` (see [Conventions](conventions.md)).

## Install the core

`omnisense/omnibase` comes with its Composer plugin, `omnibridge/composer`
(the stubs and the one patch the core declares), which must be allowed, and
the bridge of the host framework:

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase:4.x-dev omnibridge/symfony:1.x-dev
```

Then, if it saves time, a prepared structure: `omnibase/blog`,
`omnibase/marketplace`, `omnibase/agenda`... Each has its manual; none is
required.

## Read on

- [Architecture](architecture.md): the core and the application, the
  families, the bridges, the static export.
- [The documentation](/docs): one manual per package, read from its
  repository's `docs/` folder at a pinned commit.
- [The package search](/packages): every package by name, family or kind,
  and what its README says of its verification.
- [Contributing](contributing.md): correcting a page from GitHub.
