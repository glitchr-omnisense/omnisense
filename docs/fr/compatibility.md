---
title: Compatibilité
description: Ce qui tourne en PHP nu, ce qui tourne sous Symfony et Laravel, ce que couvrent les ponts.
order: 3
---

# Compatibilité avec les cadres

Ce qui est vrai aujourd'hui. Aucune date n'est promise pour le reste.

## Les familles tournent en PHP nu

Le cœur d'une famille ne requiert ni Symfony, ni Doctrine, ni Twig :
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
qui monte son registre à la main et vérifie qu'aucune classe d'un cadre
n'est chargée.

## Le cœur tourne sous Symfony et sous Laravel

Depuis la 4.0, `omniscient/omnibase` ne requiert que sa fondation, sans
cadre. Le cadre vient par un pont :

- **Symfony** : `omnibridge/symfony` apporte les paquets du cadre et
  inscrit les contrats du cœur sur les services de Symfony ; le bundle du
  cœur refuse de démarrer sans lui.
- **Laravel** : `omnibridge/laravel` fait de même sur le conteneur de
  Laravel, et lit les routes de l'application dans les mêmes actions
  (`#[Route]`).
- **Doctrine** et **Twig** sont des ponts à part (`omnibridge/doctrine`,
  `omnibridge/twig`) : ni l'un ni l'autre n'est une part de Symfony.

Les structures préparées `omnibase/*` sont, aujourd'hui, des bundles
Symfony sur le cœur ; chacune passe son métier à la racine de son paquet
quand elle est reprise.

## L'hébergement statique

Les pages publiques d'une application peuvent être exportées par
`omnibridge/static` et servies sans PHP. Les pages qui ont besoin d'un
serveur — se connecter, un formulaire envoyé, un paiement, une réponse
propre à un visiteur — restent sur l'hôte dynamique, et l'export dit
lesquelles.
