---
title: Démarrer
order: 1
---

# Démarrer

L'écosystème Omni est un ensemble de paquets PHP. Il se lit en deux parts :
le **socle**, que tout site peut prendre, et des **structures préparées**,
facultatives.

| Part | Nom | Ce que c'est |
|---|---|---|
| Socle : le cœur | `glitchr/omnibase` | ce dont tout site part : comptes, back-office, traductions, images, formulaires |
| Socle : les familles | `omniscient/omni<domaine>` | un contrat pour un domaine (colis, paiements, registres…), quel que soit le service derrière ; `glitchr/omni<domaine>` pour celles qui n'ont pas encore déménagé |
| Socle : les fournisseurs | `omni<domaine>/<service>` | un service qui remplit le contrat de sa famille : un paquet par service, [à chercher](/packages) |
| Socle : les ponts | `omnibridge/<cible>` | la colle entre le socle et un cadre, un stockage ou une couche de rendu |
| Facultatif : les structures préparées | `omnibase/<métier>` | une structure toute prête pour un métier — entités, écrans, back-office, traductions, données de démonstration — déjà assemblée sur le socle |

Les structures préparées sont **facultatives** : rien dans le cœur ni dans
les familles n'en dépend. Un site en prend une pour aller vite, ou écrit la
sienne sur le cœur et les familles.

Une famille et ses fournisseurs **ne dépendent pas du cœur** : ils
s'installent dans n'importe quelle application PHP. Le cœur et les
structures préparées sont, aujourd'hui, des bundles Symfony (voir
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

Dans Symfony, le pont est dans le paquet de la famille (`Bridge/Symfony`) :
déclarez son bundle dans `config/bundles.php` et ses passerelles sous la clé
de la famille (`omnitrade:`). Le manuel de chaque famille s'ouvre sur
l'usage en PHP nu, puis sur Symfony.

> [!TIP]
> Le [sélecteur d'installation](/install) compose les commandes, le bloc
> `composer.json` et, sous Symfony, la ligne de `config/bundles.php` pour le
> cadre, le canal et ce qu'il vous faut (le cœur, une famille et ses
> fournisseurs, un pont, une structure préparée), avec les contraintes de
> version qu'il faut (voir [Conventions](conventions.md)).

## Installer le cœur

`glitchr/omnibase` s'installe avec son greffon Composer, qui doit être
autorisé :

```sh
composer config allow-plugins.glitchr/base-plugin true
composer require glitchr/omnibase:3.x-dev
```

Puis, si elle fait gagner du temps, une structure préparée :
`omnibase/blog`, `omnibase/marketplace`, `omnibase/agenda`… Chacune a son
manuel ; aucune n'est requise.

## Lire la suite

- [La documentation de chaque paquet](/docs) : un manuel par paquet, lu
  dans le dossier `docs/` de son dépôt, une version par branche.
- [La recherche des paquets](/packages) : chaque paquet, par nom, famille
  ou sorte, et où il en est (publié, vérifié en réel, sa dernière version).
- [Contribuer](contribuer.md) : corriger une page depuis GitHub.
