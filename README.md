# Omniscient

The website of the Omni ecosystem: the cross-ecosystem guides, the news, the
package catalogue and each package's documentation, read from its own
repository at a pinned commit. A Symfony application on
`omniscient/omnibase` 4.x, exported to static files by `omnibridge/static`:
nothing runs on the server when the site is read - no database, no PHP, no
search service.

## Content

| Folder | What it holds |
|---|---|
| `docs/<locale>/` | the guides, one Markdown file per guide and language (same file name in each) |
| `news/` | the news, `<YYYY-MM-DD>-<slug>.<locale>.md`; the Atom and RSS feeds are built from them |
| `catalog/` | the catalogue, one YAML file per family: its phrase, its packages (kind, description, repository, branch, what its README says of its verification) |
| `config/documentation.yaml` | each package's documentation: its series, the branch each follows and the commit it is read at |

A change is a pull request; each pull request builds a preview of the whole
site (the `website` workflow's artifact).

`bin/console app:catalog:import` rewrites `catalog/` and
`config/documentation.yaml` from the working copies of the packages
(`OMNI_REPOS`, `~/Sites` mounted read-only), keeping the phrases written by
hand and the commits already pinned; `--pin` moves every series to its
branch's published head. No package has a release tag yet: every pinned ref
is a branch head's commit.

## Build

```sh
bin/omnibridge-static build     # docs/ of each package at its ref, Encore, export to dist/, check
bin/omnibridge-static check dist
```

`SITE_URL` is the public origin (canonical links, sitemap, feeds;
`http://localhost:8135` by default). `--local=<folder>` reads the commits
from working copies instead of GitHub; `--skip-assets` keeps the current
Encore build; `--verify` renders every page twice. Two builds of the same
commit give the same `dist/omnibridge-static.json`.

With the site's containers: `make static`, then `make static-serve`
(http://localhost:8135, a plain nginx).

## Tests

```sh
php vendor/bin/phpunit
```

The tests read their own catalogue, manual and news (`tests/Fixtures`).
`tests/smoke/chrome.mjs` checks a served build in headless Chrome (CI).

Licence: MIT.
