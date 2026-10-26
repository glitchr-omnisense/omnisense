---
title: Contributing
description: The pencil, the fork, the pull request; writing a page, a piece of news, a catalogue entry.
order: 5
---

# Contributing

Each package's documentation is its repository's `docs/` folder: Markdown
files, as they are written there. This site renders them at a pinned commit;
it does not keep them. Correcting a page means correcting the file.

## Propose a change, in three steps

1. On the page to correct, click the pencil **"Propose a change"**, beside
   the title: it opens GitHub's editor on the source file, at the branch the
   series follows.
2. Without write access to the repository, GitHub offers a fork of it by
   itself: edit, then **"Propose changes"**.
3. GitHub opens a pull request. It is reviewed, then merged; the page
   changes here once the series is pinned to a commit that has it.

The guides, the news and the catalogue are this site's own files, in
[its repository](https://github.com/glitchr-omnisense/omnisense): the same
path applies, and every pull request builds a preview of the whole site.

## Write a page of a manual

In a package's `docs/` folder, one `.md` file per page. An optional front
matter gives its title and its place in the contents:

```md
---
title: Webhooks
order: 3
---

# Webhooks
```

Without a front matter, the title is the first `# Title`, and the order
that of the file names (`01-installation.md`, `02-configuration.md`...). A
sub-folder is a section. `docs/index.md` (or `docs/README.md`) is the
manual's home page.

Relative links between pages (`[Webhooks](webhooks.md)`) lead to the pages
here; links to other files of the repository (`../src/Bridge/Symfony`) and
images lead to GitHub at the commit read. Raw HTML is shown as text. GitHub's
alerts are recognised:

```md
> [!NOTE]
> What the reader should know.

> [!TIP]
> A shortcut.

> [!WARNING]
> What breaks if forgotten.
```

## Write a piece of news

One file per item and language in `news/`, named
`<YYYY-MM-DD>-<slug>.<locale>.md`:

```md
---
title: omnibridge/static is out
summary: The static exporter of the omnibridge family.
tags: [omnibridge, release]
---

The text, in Markdown.
```

The preview build of the pull request shows it; the merge publishes it with
the Atom and RSS feeds.

## The packages themselves

The repositories are public on GitHub, under
[glitchr-studio](https://github.com/glitchr-studio), under the MIT licence.
A code correction follows the same path: fork, commit, pull request. Each
family has a Docker harness and a test suite; its README says how to run
them.
