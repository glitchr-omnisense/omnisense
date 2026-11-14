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
from (`Site::dependsOn()`). Every page depends on the code, templates,
translations, configuration, lock and environment, the star count, what it
knows of the manuals (key, package, series) and the catalogue's skeleton:
each family with its packages' names (`Catalogue::skeleton()`), enough for a
link to any of them. On top of that:

- a manual's pages depend on its entry in `config/documentation.yaml`, its
  fetched `var/docs` folders, its compatibility row and its package's family
  file (`catalog/<family>.yaml`);
- a piece of news depends on its files; the news list and the feeds on
  `news/`; a guide on `docs/` (the Install guide also on `catalog/`);
- a family's or a package's address that redirects to a manual depends on
  nothing more; otherwise on the family's file, or on `catalog/` (a package
  page lists what requires it);
- the home depends on `catalog/`, `news/` and `docs/`; the package list and
  `/support` on `catalog/`; the API pages on `catalog/`, `config/apis.yaml`
  and `config/policies.json`; the dashboard on `catalog/` and
  `config/compatibility.yaml`;
- the documentation's entry and `search.json` depend on `catalog/`,
  `config/documentation.yaml` and every manual's inputs (`search.json` also on
  `news/` and `docs/`);
- search, legal, privacy, `robots.txt` and `404.html` depend on the shared
  inputs only.

The renderer's answers are kept in `var/static-cache`, and a page whose
inputs did not change is not rendered again: an untouched build renders
nothing. Measured on the whole site (4692 addresses): one provider's
description in its family's file renders 94 (that family's manuals, the
pages listing the catalogue), a piece of news 12, a guide 18,
`compatibility.yaml` 2, `apis.yaml` 6, a page of one manual 12; a template or
a file of `src/` renders everything. An address found only by following a
link (no route) has no declared inputs and is rendered every time.
Nothing checks these
lists: a page that reads something its list leaves out would be served stale
from the cache, so the workflow compares each build of `main` with a full
one. `--full` renders everything; `--only=/docs/omnibase/*` renders the
addresses it names whatever the cache holds; `--cache=<folder>` keeps the
answers elsewhere. omnibridge/static also keeps a build record in that
folder: the PHP files the export loaded with their checksums, PHP and its
extensions, the installed packages. An export whose record is missing or no
longer matches (a vendor upgrade, another PHP, a changed file of `src/`)
renders every page and says why ("full (...)"); the kernel's compiled
container and Twig cache under `var/cache` are left out of it
(`Site::ignoreLoaded()` in `static.php`), since every fresh boot writes them
again.

`bin/console app:provenance dist` writes `dist/provenance.json`, the
deployment manifest published with the site
(`https://glitchr-omnisense.github.io/omnisense/provenance.json`). It records:

- `site`: the repository, the commit and its date, and `modified`, each
  tracked file the build changed in its checkout with its SHA-256 (the data
  the workflow fetches before the export: `config/compatibility.yaml`,
  `config/github.yaml`, `config/policies.json`; a pin a dispatch moved);
- `tools`: PHP, ICU, Composer, Node, Yarn and omnibridge/static;
- `packages`: every locked package (not `require-dev`) with its version and
  commit;
- `documentation`: each manual's series with its repository, the ref
  `config/documentation.yaml` pins and the commit `app:docs:fetch` read;
- `artifact`: the SHA-256 of `omnibridge-static.json`, the number of files and
  bytes, and a checksum of the whole folder (each file's path and SHA-256,
  sorted).

It holds no time and no run: two builds from the same sources write the same
file. The run's own facts (what asked for it, the export's counts) are in the
run's summary. It is written after the comparison with a full export and is
not one of the files `omnibridge-static.json` lists (the exporter's report has
no room for data of the site's own).

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
- **Rollback.** Each build of `main` keeps its folder for 90 days as the
  run's `site` artifact. **Run workflow** with `rollback` set to an earlier
  run's id (the number in its address), or to the full SHA of a commit, deploys
  that folder again. Nothing is built. A commit takes the latest successful run
  of this workflow on `main` built from it whose artifact is still kept. Before
  anything goes out, `.github/scripts/check-site.py` checks every file against
  the run's `omnibridge-static.json`, checks that no other file is there, and
  checks the report, the folder checksum and the commit against its
  `provenance.json`. From the command line:

  ```sh
  gh workflow run website.yml --ref main -f rollback=<run id or commit SHA>
  # which run a value names, without deploying anything:
  GITHUB_REPOSITORY=glitchr-omnisense/omnisense .github/scripts/rollback-run.sh <run id or commit SHA>
  # a downloaded site checked as the rollback checks it:
  gh run download <run id> -n site -D /tmp/site && python3 .github/scripts/check-site.py /tmp/site <commit SHA>
  ```

  An artifact older than 90 days is gone: check out that commit and run the
  workflow on it with `full` instead. The next push to `main` deploys its own
  build again, so the rollback holds until then.
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
- A package that published a release sends a `repository_dispatch` of type
  `package-released`, so that the catalogue picks up the release:

  ```sh
  gh api repos/glitchr-omnisense/omnisense/dispatches \
    -f event_type=package-released \
    -f 'client_payload[package]=omnibase/blog' -f 'client_payload[version]=1.0.1' \
    -f 'client_payload[repository]=glitchr-omnisense/omnibase-blog'
  ```

  `package` (`vendor/name`, or `@scope/name` for npm) and `version` are
  required. `repository` is optional: it is cloned in addition to the
  catalogue's own repositories, which lets a package the catalogue does not
  list yet join it. Only owners the catalogue already has are accepted. The
  `catalogue` job clones every repository the catalogue lists, shallow, at its
  branch (`app:catalog:clone`). One clone that fails stops the job, because an
  import over a partial set would drop packages. The job then runs
  `app:catalog:import` over the clones, with each package's release read from
  Packagist. If nothing changed, the run stops there. Otherwise the site is
  built from the new catalogue, then tested and smoke-tested, and the change is
  proposed as a pull request from the branch `catalogue/<package>-<version>`.
  A later dispatch of the same release pushes that branch again. Nothing is
  deployed: merging the pull request is the push that deploys it.

  Why a pull request and not a commit to `main` (as `documentation-published`
  does): the import writes onto public pages what other repositories'
  `composer.json` and READMEs and Packagist say, and a stale Packagist
  replica can answer an older release. A maintainer reads the diff first. A
  pull request opened with the job's token starts no workflow; its
  description links the run that built and tested it, and the run's
  `website-catalogue` artifact is the preview. The job's token needs
  Settings → Actions → General → "Allow GitHub Actions to create and approve
  pull requests". Without that setting, the branch is still pushed, and the
  run's summary gives the address that opens the pull request.

  The token that sends either dispatch must belong to the sending repository's
  workflow: a fine-grained token limited to this repository with "Contents:
  read and write" (the dispatches endpoint asks for it), stored in the sender
  as a secret.

## Tests

```sh
php vendor/bin/phpunit
```

The tests read their own catalogue, manual and news (`tests/Fixtures`).
`tests/smoke/chrome.mjs` checks a served build in headless Chrome (CI).

Licence: MIT.
