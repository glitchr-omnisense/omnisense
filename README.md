# Omnisense

The website of the Omni ecosystem: the cross-ecosystem guides, the news, the
package catalogue and each package's documentation, read from its own
repository at a pinned commit. A Symfony application on
`omnisense/omnibase` 4.x, exported to static files by `omnibridge/static`:
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

`SITE_URL` is the public address (canonical links, sitemap, feeds;
`http://localhost:8135` by default): an origin, or the folder of one the site
is served under (`https://glitchr-omnisense.github.io/omnisense/`) - the pages
are rendered at the root and omnibridge/static moves every internal address
under the folder. `--github-pages` exports for GitHub Pages (`.nojekyll`, no
`_redirects`, any address outside the folder an error). `--local=<folder>`
reads the commits from working copies instead of GitHub; `--skip-assets`
keeps the current Encore build; `--verify` renders every page twice. Two
builds of the same commit give the same `dist/`.

With the site's containers: `make static`, then `make static-serve`
(http://localhost:8135, an nginx that serves it as GitHub Pages does;
`make static-serve STATIC_BASE_PATH=/omnisense` for a build under that
folder).

## Hosting

GitHub Pages, deployed by the `website` workflow on every push to `main`
(pull requests build the preview artifact only). Once, on GitHub:
Settings → Pages → Source: "GitHub Actions". The address is the one Pages
gives the repository (`https://glitchr-omnisense.github.io/omnisense/`); with a
custom domain, set it in Settings → Pages and set the repository variable
`SITE_URL` to it (`https://example.org/`): the site is then built at the root.

## Tests

```sh
php vendor/bin/phpunit
```

The tests read their own catalogue, manual and news (`tests/Fixtures`).
`tests/smoke/chrome.mjs` checks a served build in headless Chrome (CI).

Licence: MIT.
