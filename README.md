# Omnisense

The website of the Omni ecosystem: the cross-ecosystem guides, the news, the
package catalogue and each package's documentation, read from its own
repository at a pinned commit. A Symfony application on
`omnisense/omnibase` 5.x (omnibridge/symfony, doctrine mode), exported to static files by `omnibridge/static`:
nothing runs on the server when the site is read - no database, no PHP, no
search service.

## Content

| Folder | What it holds |
|---|---|
| `docs/<locale>/` | the guides, one Markdown file per guide and language (same file name in each) |
| `news/` | the news, `<YYYY-MM-DD>-<slug>.<locale>.md`; the Atom and RSS feeds are built from them |
| `catalog/` | the catalogue, one YAML file per family: its phrase, its packages (kind, description, repository, branch, what its README says of its verification) |
| `config/documentation.yaml` | each package's documentation: its series, the branch each follows and the commit it is read at |
| `config/policies.json` | the usage policies the providers' packages declare, at the commit each was read at (written by `app:policies:import`, never by hand) |

A change is a pull request; each pull request builds a preview of the whole
site (the `website` workflow's artifact).

`bin/console app:catalog:import` rewrites `catalog/` and
`config/documentation.yaml` from the working copies of the packages
(`OMNI_REPOS`, `~/Sites` mounted read-only), keeping the phrases written by
hand and the commits already pinned; `--pin` moves every series to its
branch's published head. A package that only reserves its name (its
keyword `reserved`) is left out: `config/apis.yaml` shows it as reserved.
Each entry's `version` is the package's latest stable release on Packagist,
read from Packagist's public metadata (`repo.packagist.org/p2/<name>.json`,
anonymously, a few requests at a time), and `abandoned` says Packagist or
the package's own keyword `abandoned`; when Packagist does not answer, the
entry keeps what it said. The pinned documentation refs are still branch
heads' commits.

`bin/console app:policies:import` rewrites `config/policies.json`: the usage
policy each provider of the catalogue declares (its `policy.json`, named in
`extra.omnisense.policy`), read at its branch's published head from GitHub and
exported by omnisense/contracts, which checks it against the schema; `--local`
reads the working copies at `refs/remotes/origin/<branch>` instead. The CI
runs it before each build; a policy that does not validate fails it. The
cards of `/apis` and the table of `/apis/policies` show these declarations;
`config/apis.yaml` types a policy only for a provider with no package yet.

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

The build is incremental. `static.php` declares what each page is rendered
from (`Site::dependsOn()`): every page depends on the code, templates,
translations, configuration, catalogue, lock and environment; a manual's
pages on its entry in `config/documentation.yaml`, its fetched `var/docs`
folders and its compatibility row; a piece of news on its files; the news
list and feeds on `news/`; a guide on `docs/`. The renderer's answers are
kept in `var/static-cache`, and a page whose inputs did not change is not
rendered again. A page whose inputs are not declared (the home, the
catalogue, the search index...) is rendered every time. Nothing checks these
lists: a page that reads something its list leaves out would be served stale
from the cache, so the workflow compares each build of `main` with a full
one. `--full` renders everything; `--only=/docs/omnibase/*` renders the
addresses it names whatever the cache holds; `--cache=<folder>` keeps the
answers elsewhere.

`bin/console app:provenance dist` writes `dist/provenance.json`, the
deployment manifest published with the site. It records the site's commit,
the commit each documentation source was read at, the locked packages, the
tool versions and the export's checksums. Its build time and run are that
build's own: it is written after the comparison and is not one of the files
`omnibridge-static.json` lists.

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

The workflow can also be started by hand or by another repository:

- **Run workflow** with `full` renders every page.
- **Run workflow** with `rollback` set to an earlier run's id deploys again
  the `site` artifact that run kept. Each build of `main` keeps one for 90
  days. Nothing is built. The run must be a successful run of this workflow on
  `main`, and its files are checked against its `omnibridge-static.json` and
  `provenance.json` first.
- A documentation package that published a commit sends a
  `repository_dispatch` of type `documentation-published` with a token
  allowed to write to this repository:

  ```sh
  gh api repos/glitchr-omnisense/omnisense/dispatches \
    -f event_type=documentation-published \
    -f 'client_payload[key]=omnibase' -f 'client_payload[commit]=<full SHA>'
  ```

  `key` is the manual's key in `config/documentation.yaml`. `commit` must be on
  the branch the series follows; without it, the branch's head is taken.
  `series` is optional and defaults to the manual's default series.
  `app:docs:pin` moves that series to the commit. Only that manual's addresses
  are rendered (`--only`), and the moved pin is committed to `main` before the
  site is deployed. If the key is unknown or missing, the whole site is built
  as on a push. A commit that is not on the branch fails the run, and the
  site is left as it was.

## Tests

```sh
php vendor/bin/phpunit
```

The tests read their own catalogue, manual and news (`tests/Fixtures`).
`tests/smoke/chrome.mjs` checks a served build in headless Chrome (CI).

Licence: MIT.
