---
title: Contribuer
description: Le crayon, la copie, la demande de fusion ; écrire une page, une actualité, une entrée du catalogue.
order: 5
---

# Contribuer

La documentation de chaque paquet est le dossier `docs/` de son dépôt : des
fichiers Markdown, tels qu'ils y sont écrits. Ce site les rend à un commit
épinglé ; il ne les garde pas. Corriger une page, c'est corriger le fichier.

## Proposer une correction, en trois temps

1. Sur la page à corriger, cliquez le crayon **« Proposer une
   correction »**, à côté du titre : il ouvre l'éditeur de GitHub sur le
   fichier source, à la branche que suit la série.
2. Sans droit d'écriture sur le dépôt, GitHub propose de lui-même une copie
   (*fork*) : modifiez, puis **« Propose changes »**.
3. GitHub ouvre une demande de fusion (*pull request*). Elle est relue, puis
   fusionnée ; la page change ici quand la série est épinglée à un commit
   qui la contient.

Les guides, les actualités et le catalogue sont les fichiers de ce site, dans
[son dépôt](https://github.com/glitchr-studio/omniscient) : le même chemin
s'applique, et chaque demande de fusion construit un aperçu du site entier.

## Écrire une page d'un manuel

Dans le dossier `docs/` d'un paquet, un fichier `.md` par page. Un en-tête
facultatif donne son titre et sa place dans le sommaire :

```md
---
title: Webhooks
order: 3
---

# Webhooks
```

Sans en-tête, le titre est le premier `# Titre`, et l'ordre celui des noms
de fichiers (`01-installation.md`, `02-configuration.md`…). Un sous-dossier
est une section. `docs/index.md` (ou `docs/README.md`) est la page d'accueil
du manuel.

Les liens relatifs entre pages (`[Webhooks](webhooks.md)`) mènent aux pages
d'ici ; les liens vers d'autres fichiers du dépôt (`../src/Bridge/Symfony`)
et les images mènent à GitHub, au commit lu. Le HTML brut est montré comme
du texte. Les encadrés de GitHub sont reconnus :

```md
> [!NOTE]
> Ce que le lecteur doit savoir.

> [!TIP]
> Un raccourci.

> [!WARNING]
> Ce qui casse si on l'oublie.
```

## Écrire une actualité

Un fichier par article et par langue dans `news/`, nommé
`<AAAA-MM-JJ>-<slug>.<langue>.md` :

```md
---
title: omnibridge/static est publié
summary: L'export statique de la famille omnibridge.
tags: [omnibridge, version]
---

Le texte, en Markdown.
```

L'aperçu de la demande de fusion le montre ; la fusion le publie, avec les
flux Atom et RSS.

## Les paquets eux-mêmes

Les dépôts sont publics sur GitHub, sous
[glitchr-studio](https://github.com/glitchr-studio), sous licence MIT. Une
correction de code suit le même chemin : copie, commit, demande de fusion.
Chaque famille a un harnais Docker et une suite de tests ; son README dit
comment les lancer.
