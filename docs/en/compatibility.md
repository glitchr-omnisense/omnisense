---
title: Compatibility
description: What runs in plain PHP, what runs under Symfony and Laravel, what the bridges cover.
order: 3
---

# Compatibility with frameworks

What is true today. No date is promised for the rest.

## The families run in plain PHP

A family's core requires neither Symfony, nor Doctrine, nor Twig: only
standalone libraries (`symfony/http-client-contracts`, and for a provider
that calls an API, `symfony/http-client`, which is a library like any other
and always accepts a client passed from outside). Its registry is built by
hand:

```php
$registry = new Registry([new PayPalGatewayFactory(HttpClient::create())], [
    'paypal' => ['factory' => 'paypal', 'options' => ['client_id' => $id, 'secret' => $secret]],
]);
```

That holds in a script, a Laravel application, Symfony, or anything else.
Each family's Symfony bridge is in its package, under `Bridge/Symfony`, and
its dependencies are not required: nothing of it is loaded elsewhere.

**The proof**: each family has a test without a framework,
`Tests/BareTest.php`, which builds its registry by hand and checks that no
framework class is loaded.

## The core runs under Symfony and Laravel

Since 4.0, `omniscient/omnibase` requires only its framework-free
foundation. The framework comes through a bridge:

- **Symfony**: `omnibridge/symfony` brings the framework packages and
  registers the core's contracts on Symfony's services; the core's bundle
  refuses to start without it.
- **Laravel**: `omnibridge/laravel` does the same on Laravel's container,
  and reads the application's routes from the same actions (`#[Route]`).
- **Doctrine** and **Twig** are bridges of their own (`omnibridge/doctrine`,
  `omnibridge/twig`): neither is a part of Symfony.

The prepared structures `omnibase/*` are, today, Symfony bundles on the
core; each moves its trade to its package's root as it is reworked.

## Static hosting

An application's public pages can be exported with `omnibridge/static` and
served without PHP. Pages that need a server - signing in, a posted form,
a payment, a per-visitor answer - stay on the dynamic host, and the export
says which.
