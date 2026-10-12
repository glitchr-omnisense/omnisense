# Les manuels

Les manuels sont ceux d'omnibase/docs (ses propres pages : `/docs/omnibase/docs`).
Ce site n'y ajoute que sa configuration et une page d'index groupée comme
le catalogue (`templates/bundles/WikidocBundle/client/index.html.twig`).

```yaml
# config/packages/wikidoc.yaml
wikidoc:
    public: { enabled: true, path: docs }
    discover:
        - '%app.catalogue.repos_dir%/*/*'      # chaque famille, fournisseur et bundle
        - '%app.catalogue.repos_dir%/flowjs'     # et les bibliothèques
    cache_ttl: 60
    search:
        typesense: { enabled: true, prefix: omniscient }
```

- **Un manuel par paquet** : son dossier `docs/` et son README, lus dans le
  dépôt. Un paquet sans `docs/` a un manuel d'une page, son README.
- **Une version par branche** : la branche en cours est lue sur place ;
  `bin/console wikidoc:sync` copie le `docs/` et le README des autres
  branches de version (`1.x`, `2.x`, `main`…) sous `var/wikidoc/`. À relancer
  après un `git fetch` dans un dépôt.
- **La recherche** : `bin/console wikidoc:index` écrit une collection
  Typesense par manuel et par version (`omniscient_<paquet>_<version>`).
  Quand le conteneur `search` est arrêté, ou qu'un manuel n'a pas encore sa
  collection, l'index des pages répond à la place et la page le dit.
- **Corriger une page** : le crayon « Proposer une modification », à côté
  du titre (`templates/bundles/WikidocBundle/client/page.html.twig`), ouvre
  l'éditeur de GitHub sur le fichier, à la branche lue (`rendered.editUrl`,
  `PageRenderer::forgeUrl`). Un manuel sans dépôt public — celui du site —
  n'en a pas. Il n'y a pas d'éditeur ici.
- **La page d'entrée** (`templates/bundles/WikidocBundle/client/index.html.twig`)
  oriente comme docs.conda-forge.org et symfony.com/doc : la recherche,
  puis Démarrer (les pages du `docs/` du site), la référence (un manuel par
  paquet, groupés comme le catalogue), Contribuer.

L'écran Administration › Manuels montre chaque manuel, ses versions et
leur nombre de pages, le moteur qui répond, et deux boutons qui font ce que
font les deux commandes.

Le manuel du back-office d'omnibase/docs (`@WikidocBundle/src/Controller`,
aussi à `/docs`) n'est pas importé dans les routes de ce site.
