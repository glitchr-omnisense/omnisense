---
title: Installation
order: 10
---

# Installation and first calls

```sh
composer require glitchr/omnitrade omnitrade/stripe
```

> [!NOTE]
> Omnitrade needs no framework.

> [!WARNING]
> Never commit a signing secret.

## Plain PHP

```php
$registry = new Registry([new StripeGatewayFactory($http)], $config);
```

Then the [webhooks](webhooks.md), the [bridge](../src/Bridge/Symfony) and the [README](../README.md).

## In a framework

Symfony: a bundle does the wiring.
