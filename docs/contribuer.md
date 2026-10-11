---
title: Contribuer
order: 4
---

# Contribuer

La documentation de chaque paquet est le dossier `docs/` de son dépôt : des
fichiers Markdown, tels qu'ils y sont écrits. Ce site les rend, il ne les
garde pas. Corriger une page, c'est corriger le fichier.

## Proposer une modification, en trois lignes

1. Sur la page à corriger, cliquez le crayon **« Proposer une
   modification »**, à côté du titre : il ouvre l'éditeur de GitHub sur le
   fichier source, à la bonne branche.
2. Sans droit d'écriture sur le dépôt, GitHub propose de lui-même une
   bifurcation (*fork*) de celui-ci : modifiez, puis **« Propose changes »**.
3. GitHub ouvre une demande de fusion (*pull request*). Elle est relue, puis
   fusionnée ; la page est corrigée ici à la lecture suivante du dépôt.

Les pages de ce manuel-ci (Démarrer, Compatibilité, Conventions, cette page)
sont celles du site lui-même, dont le dépôt n'est pas public : elles ne
portent pas de crayon. Une remarque à leur sujet passe par
[le dépôt du socle](https://github.com/glitchr-studio/omnibase/issues).

## Écrire une page

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
est une section ; son `index.md` en est la page d'accueil. Le README du
paquet est la page d'accueil du manuel.

Les liens relatifs entre pages (`[Webhooks](webhooks.md)`) et vers des
fichiers du dépôt (`../src/Bridge/Symfony`) sont réécrits à l'affichage ;
les images du dossier sont servies. Trois encadrés sont reconnus :

```md
> [!NOTE]
> Ce que le lecteur doit savoir.

> [!TIP]
> Un raccourci.

> [!WARNING]
> Ce qui casse si on l'oublie.
```

## Une version par branche

La branche en cours d'un dépôt est lue sur place. Les autres branches de
version (`1.x`, `2.x`) sont copiées par `wikidoc:sync` ; le menu des versions
mène à la même page dans chacune, et le crayon ouvre le fichier **de la
branche lue**, pas de la branche par défaut.

## Les paquets eux-mêmes

Les dépôts sont publics sur GitHub, sous
[glitchr-studio](https://github.com/glitchr-studio), sous licence MIT. Une
correction de code suit le même chemin : bifurcation, commit, demande de
fusion. Chaque famille a un harnais Docker et une suite de tests ; son
README dit comment les lancer.
