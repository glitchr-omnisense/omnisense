---
title: Six new families, GitHub Pages, manuals by series
summary: The website is published on GitHub Pages through omnibridge/static; omnifund, omnidns, omniticket, omnireach, omnifilm and omnigame join the catalogue; omnidrive gains four providers; flowjs 1.5.2; omnibase's manual for 4.x and 3.x side by side.
tags: [website, omnibridge, release]
---

**GitHub Pages.** `omnibridge/static` has a GitHub Pages target: the pages
are rendered at the root and every internal address is moved under the
folder the site is served from (`/omniscient/` here), with a `.nojekyll`
file, a `CNAME` for a custom domain, the redirections written as small
pages, and a failed build for any address left outside the folder. This
website is built that way by its own workflow: a pull request builds the
whole site as a preview; a merge to `main` builds it twice (the two folders
must be identical), checks it in a headless Chrome and publishes it. This
piece of news went through that path.

**Six new families.** Each is a core package of contracts with its
providers as packages of their own, under any PHP host:

- **omnifund** - who funds a project: GitHub Sponsors, Open Collective,
  Liberapay.
- **omnidns** - a domain name where it is registered (availability,
  registration, renewal, name servers, zone records): OVH, Gandi,
  Cloudflare, Scaleway.
- **omniticket** - tickets sold elsewhere (events, attendees, a ticket code
  checked at the door): Weezevent, Billetweb, HelloAsso, Eventbrite,
  Ticketmaster.
- **omnireach** - what outside tools know of a site's audience: Search
  Console, Bing Webmaster Tools, Matomo, Plausible, Google Analytics 4.
- **omnifilm** - the record of a film, a series or an episode: TMDB, OMDb,
  TheTVDB, Trakt, Wikidata.
- **omnigame** - a game's presence on the stores, read only: Steam, GOG,
  itch.io, IGDB, RAWG.

**omnidrive** gains four providers - WebDAV (Nextcloud, ownCloud, any
server), Azure Blob Storage, Dropbox and Google Drive - beside the local
directory, S3, SFTP and FTP. OneDrive waits for a maintained Flysystem
adapter.

**flowjs 1.5.2.** A stop at most a quarter of a screen taller than the
screen (the new `shortEnd` option) rests at its top only: neither a key nor
a scroll coming to rest stops a few pixels further, at its end. This
website's home page reads its sections that way.

**Manuals by series.** A package with several maintained series publishes
each: omnibase's manual is read for 4.x, the current series, and for 3.x,
each at the head of its branch. A selector on every manual page leads to the
same page in the other series; the pages of an older series say which one
is current and are kept out of search engines' indexes.
