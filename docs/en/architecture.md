---
title: Architecture
description: The core and the application, the families and their providers, the bridges, and how this website itself is built.
order: 2
---

# Architecture

## Base and App

`omniscient/omnibase` holds what every site shares: its classes live under
`Base\`. A site extends them under `App\` with the same names - an
`App\Entity\User` over `Base\Entity\User`, a template of its own over the
core's - and the core finds the site's class first. What a site overrides
stays in the site; what every site needs moves to the core once.

The core's own code needs no framework. What it asks of its host - URLs,
translations, the current locale, access, sessions, tokens, files - is a
set of small contracts (`Omnibridge\Contract\*`), each fulfilled by the
host's bridge.

## Families and providers

A family answers one question - *ship a parcel*, *take a payment*, *read
what science knows of a compound* - whatever service is behind it. Its
package (`omniscient/omni<domain>`) holds the contract, the models and a
registry; each provider (`omni<domain>/<service>`) is one service
fulfilling it. A family exists only for a question asked of **at least two
real services** that no existing library already covers.

Families run in plain PHP: their `require` names no framework, and each has
a test (`Tests/BareTest.php`) that builds its registry by hand and checks
that no framework class is loaded.

## Bridges

| Package | What it connects |
|---|---|
| `omnibridge/symfony` | the core's contracts on Symfony's services, the bundle, the console commands |
| `omnibridge/laravel` | the same contracts on Laravel's container, its routes from the same actions |
| `omnibridge/doctrine` | the storage, with Doctrine ORM (independent of either framework) |
| `omnibridge/twig` | server-side rendering with Twig |
| `omnibridge/react` | the same screens as React components' props |
| `omnibridge/composer` | the installation: the stubs and the core's declared patches |
| `omnibridge/static` | a static export of an application's public pages |

A screen is described once - a name and its data - and a view bridge
renders it: Twig on the server, React in the browser.

## The static export, and this website

`omnibridge/static` enumerates an application's public addresses (from its
routes and the values their parameters take), renders each at build time
with the application's own code, and writes a folder any static host
serves: HTML, the files the pages need, redirections, `404.html`,
`sitemap.xml` and a manifest of every file's checksum. It reports broken
internal links, duplicate outputs and pages that cannot be static.

This website is built that way. It is a Symfony application on
`omniscient/omnibase`, whose content is files in its repository:

```text
catalog/                 one file per family: its packages, curated
docs/<locale>/           these guides
news/                    the news, one Markdown file per item and language
config/documentation.yaml  each package's documentation: its series and the commit each is read at
```

At build time, `app:docs:fetch` reads each package's `docs/` folder at the
commit the manifest pins, and `omnibridge/static` exports every page. Nothing
runs on the server when you read: no database, no PHP, no search service -
the search is a JSON index your browser reads.
