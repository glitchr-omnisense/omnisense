---
title: Architecture
description: Le cœur et l'application, les familles et leurs fournisseurs, les ponts, et comment ce site est lui-même construit.
order: 2
---

# Architecture

## Base et App

`omniscient/omnibase` porte ce que tous les sites partagent : ses classes
sont sous `Base\`. Un site les étend sous `App\` avec les mêmes noms — un
`App\Entity\User` par-dessus `Base\Entity\User`, un gabarit à lui par-dessus
celui du cœur — et le cœur trouve d'abord la classe du site. Ce qu'un site
redéfinit reste dans le site ; ce dont tous les sites ont besoin passe une
fois dans le cœur.

Le code propre du cœur ne demande aucun cadre. Ce qu'il attend de son hôte —
adresses, traductions, langue courante, accès, sessions, jetons, fichiers —
est un ensemble de petits contrats (`Omnibridge\Contract\*`), chacun rempli
par le pont de l'hôte.

## Familles et fournisseurs

Une famille répond à une question — *expédier un colis*, *encaisser un
paiement*, *lire ce que la science sait d'un composé* — quel que soit le
service derrière. Son paquet (`omniscient/omni<domaine>`) porte le contrat,
les modèles et un registre ; chaque fournisseur (`omni<domaine>/<service>`)
est un service qui le remplit. Une famille n'existe que pour une question
posée à **au moins deux services réels**, et qu'aucune bibliothèque
existante ne couvre déjà.

Les familles tournent en PHP nu : leur `require` ne nomme aucun cadre, et
chacune a un test (`Tests/BareTest.php`) qui monte son registre à la main et
vérifie qu'aucune classe d'un cadre n'est chargée.

## Les ponts

| Paquet | Ce qu'il relie |
|---|---|
| `omnibridge/symfony` | les contrats du cœur sur les services de Symfony, le bundle, les commandes |
| `omnibridge/laravel` | les mêmes contrats sur le conteneur de Laravel, ses routes depuis les mêmes actions |
| `omnibridge/doctrine` | le stockage, avec l'ORM Doctrine (indépendant des deux cadres) |
| `omnibridge/twig` | le rendu côté serveur avec Twig |
| `omnibridge/react` | les mêmes écrans comme propriétés de composants React |
| `omnibridge/composer` | l'installation : les stubs et les retouches déclarées du cœur |
| `omnibridge/static` | l'export statique des pages publiques d'une application |

Un écran se décrit une fois — un nom et ses données — et un pont de vue le
rend : Twig sur le serveur, React dans le navigateur.

## L'export statique, et ce site

`omnibridge/static` énumère les adresses publiques d'une application (ses
routes et les valeurs que prennent leurs paramètres), rend chacune au moment
de la construction avec le code de l'application, et écrit un dossier que
n'importe quel hébergement statique sert : le HTML, les fichiers dont les
pages ont besoin, les redirections, `404.html`, `sitemap.xml` et un
manifeste de la somme de contrôle de chaque fichier. Il signale les liens
internes cassés, les sorties en double et les pages qui ne peuvent pas être
statiques.

Ce site est construit ainsi. C'est une application Symfony sur
`omniscient/omnibase`, dont le contenu est fait de fichiers de son dépôt :

```text
catalog/                 un fichier par famille : ses paquets, tenus à la main
docs/<langue>/           ces guides
news/                    les actualités, un fichier Markdown par article et par langue
config/documentation.yaml  la documentation de chaque paquet : ses séries et le commit où chacune est lue
```

À la construction, `app:docs:fetch` lit le dossier `docs/` de chaque paquet
au commit que le manifeste épingle, et `omnibridge/static` exporte chaque
page. Rien ne tourne sur le serveur pendant votre lecture : pas de base de
données, pas de PHP, pas de service de recherche — la recherche est un index
JSON que votre navigateur lit.
