---
title: omnibridge/static - a static export for any application
summary: The omnibridge family gains a generic exporter - an application's public pages rendered at build time by its own code, for any static host.
tags: [omnibridge, release]
---

`omnibridge/static` enumerates an application's public addresses - from a
list, from its router's routes and the values their parameters take, or
from its sitemap - renders each at build time with the application's own
code, and writes a folder any static host or CDN serves without PHP.

What the export writes: the pages as a static host serves them, the files
they need (the bundler's build, the public files they link to), the
redirections (`_redirects` and `redirects.json`), `404.html`, `sitemap.xml`
with each page's languages, and `omnibridge-static.json` - every file's
checksum, sorted and without a date, so that two builds can be compared.
Broken internal links, duplicate outputs and pages that cannot be static are
reported; pages that must stay dynamic (signing in, posted forms) are named
and their links sent to the dynamic host.

It requires PHP 8.2 and nothing else; the Symfony and Laravel commands are
thin wrappers in their bridges. Its first two users: Les Ringo Stars, and
this website.
