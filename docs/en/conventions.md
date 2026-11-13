---
title: Conventions
description: Names, versions and Composer constraints, the MIT licence, "verified for real".
order: 4
---

# Conventions

What every package has in common: its name, its versions, its licence, and
what its README says of its verification.

## Names

A package is named by its Composer name, never by its folder:
`omnisense/omnistate`, not "omnistate/core". The core and each family's
own package are under the `omnisense/` vendor; the former `glitchr/...`
name is declared as a `replace`, so that a project still requiring it
receives the new package. The other names read "the family / one of its
members": `omnitrade/stripe` is a provider for an outside service,
`omnibase/marketplace` a prepared structure - optional: nothing in the core
or the families depends on it.

## Versions

Each package keeps its history in its git repository. Development happens
on version branches named `1.x`, `2.x` (`main` for a few prepared
structures), and releases are tagged on them (`v1.0.0`), following semantic
versioning. A package requires the others at their releases (`^1.0`),
never at a development branch.

| The project requires... | Constraint |
|---|---|
| a release `v1.4.2` | `^1.4` (what `composer require` writes) |
| a package with no release yet, branch `1.x` | `1.x-dev` |
| a package with no release yet, branch `main` | `dev-main` |

A project keeps Composer's default `minimum-stability` (`stable`). A
development branch named in the root `composer.json` is accepted for that
package alone; one required by a dependency is not, unless the root names
it too. What has a release and what does not yet: [Install](install.md).

## Documentation versions

Each package has its own documentation series - one per branch it
maintains - and this site reads each series at an exact commit, recorded in
its `config/documentation.yaml`. A series is moved to a newer commit by an
explicit change to that file, reviewed like any other: a moving branch never
rewrites published documentation by itself. `/docs/<package>/latest` is a
redirection to the default series, not a page of its own.

## The licence

Every package is published under the **MIT** licence. Versions released
earlier under LGPL-3.0-or-later remain available under that licence; each
package's README says so ("MIT since...").

## "Verified for real"

The state shown on a package's page comes from its README, never from a
guess: *verified* when it says it was tried against the real service
("Verified against the real service on..."), *not verified* when it says it
was not, *in part* when it says both. The sentence read is quoted as it is
on the page, so that the reader judges.

## A family's dependencies

A package requires what its code cannot run without; the optional goes in
`suggest`. Never `symfony/framework-bundle`, `dependency-injection`,
`config`, `http-kernel`, Doctrine or Twig in a family's `require`. The
Symfony bridge stays in the family's package, under `Bridge/Symfony`, its
components not required.
