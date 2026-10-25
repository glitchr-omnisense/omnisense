---
title: Writing a provider
description: From the template to a release - implement a family's contract, run its conformance suite, document what was verified, publish a version.
order: 6
---

# Writing a provider

A provider is a package of its own - `omnimap/maplibre`, `omnibus/colissimo`, `omnidrive/s3` -
that implements one family's contract for one service. Every provider is written, tested,
documented and released the same way; what differs from one family to the next is the contract
and its conformance suite.

## Start from the template

[glitchr-studio/omni-provider-template](https://github.com/glitchr-studio/omni-provider-template)
is a GitHub template repository with a skeleton for each family. One script fills the names:

```sh
gh repo create <owner>/omnimap-maplibre --public --template glitchr-studio/omni-provider-template --clone
cd omnimap-maplibre
bin/init omnimap maplibre "MapLibre"
composer install && vendor/bin/phpunit
```

The suite is green before a line is written: each skeleton is the smallest provider that meets
its family's contract. What you get:

- `composer.json`: the package's name, the family's core, no framework in `require`;
- the factory and the provider, implementing the family's contract;
- `Tests/<Name>ContractTest.php`: the family's conformance suite, wired;
- `Tests/BareTest.php`: every class loaded with the package's requirements alone;
- `README.md` with its **Verified, and not**, `docs/index.md`, `CHANGELOG.md`, the MIT `LICENSE`;
- `.github/workflows/ci.yml` and `release.yml`, calling
  [glitchr-studio/ci](https://github.com/glitchr-studio/ci) pinned to a commit.

## The conformance suites

Each family core ships its suite as a PHPUnit trait under `<Core>\Testing`. A provider's test
class uses it and hands it the provider, built over recorded answers: the suite never reaches the
network.

| Family | Suite | What it holds every provider to |
|---|---|---|
| omnimap | `Omnimap\Testing\ProviderContractTests` | factory and registry, capabilities as answered, the renderer's description on declared browser hosts, images, tiles, geocoding, the error rule |
| omnidrive | `Omnidrive\Testing\DriveContractTests` | writing, reading, streams, listing, moving, visibility, not-found errors |
| omnibus | `Omnibus\Testing\GatewayContractTests` | each request supported or refused, rates, labels, tracking, pickup points, carrier errors |
| omnitrade | `Omnitrade\Testing\GatewayContractTests` | money in minor units, statuses, refunds, voids, signed notifications, provider errors |
| omnistate | `Omnistate\Testing\RegistryContractTests` | each register contract answered or refused, 404 as null, unavailability with its retry delay |
| omnishield | `Omnishield\Testing\GatewayContractTests` | capabilities as interfaces, verdicts, scores, key and reachability errors |
| omnisearch | `Omnisearch\Testing\DriverContractTests` | indexes, words, filters, sorting, pages, facets, writes, an engine that does not answer |
| omniauth | `Omniauth\Testing\ProviderContractTests` | the authorization URL (PKCE, state, nonce), the code exchange, refusals without secrets |

A check the service legitimately cannot meet is an optional hook of the trait, documented - never
a skipped test. A check it fails is a defect, fixed in the provider.

## The error rule

The same in every family: the service's refusals (401, 403, 404, 429, 5xx) and unreadable answers
(a proxy's HTML page in a 200) are the family's provider exception, keeping the service's
message; a request that got no answer is its unreachable exception; a capability the provider does
not have is its "not supported" exception - never a `TypeError`, never a silent `null`. Each core's
`docs/providers.md` gives its exceptions' names.

## Document what was verified

The README's **Verified, and not** says what was run - the suite, offline; live checks, on which
date, against what - and what was not. The catalogue reads it: the package's page and the
[compatibility dashboard](/compatibility) show "verified for real", "in part" or "not verified"
from those words, never from a guess.

## Release

1. Move the changelog's `## Unreleased` entries under `## [X.Y.Z] - YYYY-MM-DD`, push, wait for a
   green CI.
2. `git tag -a vX.Y.Z -m X.Y.Z && git push origin vX.Y.Z`.
3. The release workflow runs the suite on the tag, takes the notes from the changelog (no section,
   no release), attaches the tag's source archive with its SHA-256 and a build provenance
   attestation, and publishes the GitHub release - a pre-release for a suffixed tag or any 0.x.
4. Packagist: the repository submitted once; its GitHub integration, or the workflow when the
   repository holds `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN`, picks the tags up.

A published tag is never moved or deleted: a mistake is a new version.

## The compatibility dashboard

[/compatibility](/compatibility) is generated at each build of this site from GitHub: for every
package, the jobs of its last CI run on its development branch, whether its tests run its family's
conformance suite and whether that CI passed, and what its README says of a live check.
