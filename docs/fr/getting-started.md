---
title: Démarrer
description: Le socle et les structures préparées, facultatives ; installer une famille ou le cœur en quelques lignes.
order: 1
---

# Démarrer

L'écosystème Omni est un ensemble de paquets PHP. Il se lit en deux parts :
le **socle**, que toute application peut prendre, et des **structures
préparées**, facultatives.

| Part | Nom | Ce que c'est |
|---|---|---|
| Socle : le cœur | `omnisense/omnibase` | ce dont un site part : comptes, back-office, traductions, images, formulaires, écrans |
| Socle : les familles | `omnisense/omni<domaine>` | un contrat pour un domaine (colis, paiements, registres…), quel que soit le service qui répond |
| Socle : les fournisseurs | `omni<domaine>/<service>` | un service qui remplit le contrat de sa famille : un paquet par service, [à chercher](/fr/paquets) |
| Socle : les ponts | `omnibridge/<cible>` | la colle entre le socle et un cadre, un stockage, une couche de rendu ou un hébergement statique |
| Facultatif : les structures préparées | `omnibase/<métier>` | une structure toute prête pour un métier — entités, écrans, back-office, traductions, données de démonstration — assemblée sur le cœur |

Les structures préparées sont **facultatives** : rien dans le cœur ni dans
les familles n'en dépend. Un site en prend une pour aller vite, ou écrit la
sienne sur le cœur et les familles.

Une famille et ses fournisseurs **ne dépendent pas du cœur** : ils
s'installent dans n'importe quelle application PHP. Le cœur tourne sous
Symfony et sous Laravel par ses ponts (voir [Compatibilité](compatibility.md)).

## Installer une famille

Une famille et le fournisseur qui répond — ici le paiement, avec PayPal :

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

Dans Symfony, le bundle de la famille est dans son paquet (`Bridge/Symfony`) :
déclarez-le dans `config/bundles.php` et ses passerelles sous la clé de la
famille (`omnitrade:`). Le manuel de chaque famille s'ouvre sur l'usage en
PHP nu, puis sur Symfony.

> [!NOTE]
> Aucun paquet n'a encore de version stable : chacun se requiert à sa
> branche de développement (`1.x-dev`), et un projet a besoin de
> `"minimum-stability": "dev"` avec `"prefer-stable": true` (voir
> [Conventions](conventions.md)).

## Installer le cœur

`omnisense/omnibase` vient avec son greffon Composer, `omnibridge/composer`
(les stubs et la seule retouche que le cœur déclare), qu'il faut autoriser,
et le pont du cadre hôte :

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase:4.x-dev omnibridge/symfony:1.x-dev
```

Puis, si elle fait gagner du temps, une structure préparée : `omnibase/blog`,
`omnibase/marketplace`, `omnibase/agenda`… Chacune a son manuel ; aucune
n'est requise.

## Lire la suite

- [Architecture](architecture.md) : le cœur et l'application, les familles,
  les ponts, l'export statique.
- [La documentation](/fr/docs) : un manuel par paquet, lu dans le dossier
  `docs/` de son dépôt à un commit épinglé.
- [La recherche des paquets](/fr/paquets) : chaque paquet par nom, famille
  ou sorte, et ce que son README dit de sa vérification.
- [Contribuer](contributing.md) : corriger une page depuis GitHub.
