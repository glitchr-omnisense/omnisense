---
title: Démarrer
order: 1
---

# Démarrer

L'écosystème Omni est un ensemble de paquets PHP. Ils se lisent en quatre
étages, et c'est tout le vocabulaire à connaître :

| Étage | Nom | Ce que c'est |
|---|---|---|
| Socle | `glitchr/omnibase` | ce dont tout site part : comptes, back-office, traductions, images, formulaires |
| Composants | `omnibase/<métier>` | les pages et les écrans d'un métier (boutique, blog, agenda, cabinet…), posés sur le socle |
| Familles | `glitchr/omni<question>` | une question posée à plusieurs services, un seul contrat pour la réponse |
| Fournisseurs | `omni<question>/<service>` | un service qui répond à la question : un paquet par service |

Une famille et ses fournisseurs **ne dépendent pas du socle** : ils
s'installent dans n'importe quelle application PHP. Le socle et ses
composants sont, aujourd'hui, des bundles Symfony (voir
[Compatibilité](compatibilite.md)).

## Installer une famille

Trois lignes suffisent. Ici le paiement, avec Stripe pour répondre :

```sh
composer require glitchr/omnitrade:^1.4 omnitrade/stripe:^1.4
```

```php
use Omnitrade\Registry;
use Omnitrade\Stripe\StripeGatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new StripeGatewayFactory(HttpClient::create())], [
    'stripe' => ['factory' => 'stripe', 'options' => ['secret' => $secret]],
]);
$gateway = $registry->get('stripe');
```

Dans Symfony, le pont est dans le paquet du cœur (`Bridge/Symfony`) :
déclarez son bundle dans `config/bundles.php` et ses passerelles sous la clé
de la famille (`omnitrade:`). Le manuel de chaque famille s'ouvre sur
l'usage en PHP nu, puis sur Symfony.

> [!TIP]
> Le [sélecteur d'installation](/install) compose la commande et le bloc
> `composer.json` pour le cadre, le canal et les paquets choisis, avec les
> contraintes de version qu'il faut (voir [Conventions](conventions.md)).

## Installer le socle

`glitchr/omnibase` s'installe avec son greffon Composer, qui doit être
autorisé :

```sh
composer config allow-plugins.glitchr/base-plugin true
composer require glitchr/omnibase:3.x-dev
```

Puis un composant par métier : `omnibase/blog`, `omnibase/marketplace`,
`omnibase/agenda`… Chacun a son manuel.

## Lire la suite

- [La documentation de chaque paquet](/docs) : un manuel par paquet, lu
  dans le dossier `docs/` de son dépôt, une version par branche.
- [Le catalogue](/packages) : où en est chaque paquet (publié, vérifié en
  réel, ses versions).
- [Contribuer](contribuer.md) : corriger une page depuis GitHub.
