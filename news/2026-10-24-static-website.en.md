---
title: This website is now static
summary: Guides, news, the catalogue and every package's manual, built from files and served without a server-side application.
tags: [website]
---

The Omnisense website is now a set of static pages, exported by
`omnibridge/static` from the same Symfony application on
`omnisense/omnibase`. What changed for readers:

- **The content is files.** The guides (`docs/`), the news (`news/`), the
  catalogue (`catalog/`, one file per family) and the list of manuals
  (`config/documentation.yaml`) live in the site's repository; a correction
  is a pull request, and every pull request builds a preview.
- **Each package's manual is pinned.** It is read from the package's own
  `docs/` folder at an exact commit, recorded in the manifest, and the page
  says which. Each package has its own series; `/docs/<package>/latest`
  redirects to the current one.
- **The search runs in your browser**, from a JSON index written with the
  site.
- **No account, no cookie.** Following a package was the only reason to sign
  in; GitHub's own "Watch" does it better.

The install selector, which composed commands on the server, is gone for
now: each package's page gives its `composer require` line, and the guides
explain the constraints.
