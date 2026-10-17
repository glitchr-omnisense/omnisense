---
title: Compatibilité
order: 2
---

# Compatibilité avec les cadres

Ce qui est vrai aujourd'hui, et ce qui le sera. Sans date promise.

## Aujourd'hui : les familles tournent en PHP nu

Le cœur d'une famille `omni*` ne requiert ni Symfony, ni Doctrine, ni Twig :
seulement des bibliothèques autonomes (`symfony/http-client-contracts`, et
pour un fournisseur qui appelle une API, `symfony/http-client`, qui est une
bibliothèque comme une autre et accepte toujours un client passé de
l'extérieur). Son registre se monte à la main :

```php
$registry = new Registry([new PayPalGatewayFactory(HttpClient::create())], [
    'paypal' => ['factory' => 'paypal', 'options' => ['client_id' => $id, 'secret' => $secret]],
]);
```

Cela vaut dans un script, une application Laravel, Symfony, ou autre. Le
pont Symfony de chaque famille est dans son paquet, sous `Bridge/Symfony`,
et ses dépendances ne sont pas requises : rien n'en est chargé ailleurs.

**La preuve** : chaque famille a un test sans cadre, `Tests/BareTest.php`,
qui monte son registre à la main et vérifie qu'aucune classe du cadre n'est
chargée.

> [!NOTE]
> Il n'y a pas encore de pont Laravel. Une famille s'y utilise comme en PHP
> nu : son registre monté dans un fournisseur de services. Un pont s'écrit
> quand quelqu'un s'en sert, pas avant.

## Aujourd'hui : omnibase est fait pour Symfony

`glitchr/omnibase` et les structures préparées `omnibase/*` sont des bundles Symfony :
leurs écrans, leur stockage et leurs gabarits s'appuient sur Symfony,
Doctrine et Twig. Ils ne s'installent pas ailleurs pour l'instant, et le
[sélecteur d'installation](/install) le dit quand on les choisit hors de
Symfony.

## Prévu : omnibase deviendra agnostique

Le métier de chaque structure passera à la racine de son paquet (objets,
règles, contrats), ses parts de cadre dans des ponts du même paquet :
contrôleurs et formulaires dans `Bridge/Symfony`, correspondances et dépôts
dans `Bridge/Doctrine`, gabarits dans `Bridge/Twig` — leurs dépendances non
requises, comme pour les familles. Les noms des paquets ne changent pas.

Une colle générique, qui ne connaît aucune structure, les reliera à chaque
cadre :

| Paquet | Ce qu'il relie |
|---|---|
| `omnibridge/symfony` | conteneur, routage, formulaires, sécurité, moteur d'administration |
| `omnibridge/doctrine` | le stockage (Doctrine est autonome, ce n'est pas une part de Symfony) |
| `omnibridge/twig` | le rendu (Twig est autonome aussi) |
| `omnibridge/composer` | l'installation : aujourd'hui `glitchr/base-plugin` |
| `omnibridge/react` | les pages en composants React, depuis n'importe quel cadre |
| `omnibridge/laravel` | seulement à l'usage |

Un écran se décrira une fois et un pont de vue le rendra : la structure
remet un nom et ses données, `omnibridge/twig` les rend côté serveur,
`omnibridge/react` les reçoit comme propriétés d'un composant React. Les formulaires sont le
cas difficile.

Ce chantier vient après les sites en cours. Jusque-là, aucun paquet n'est
touché pour cela.
