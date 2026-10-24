/*
 * After first paint: the glitchr plugins, started by omniscient/omnibase's
 * Base.boot() (vendor/omniscient/omnibase/assets/boot.js) - flowjs' stops, then its
 * navigation (links swap #content in place, the back office opens as a
 * website-in-website over the site) - and the site's own effects.
 */
Object.assign(window, { jQuery: jQuery, $: jQuery });

import nProgress from 'nprogress';
import 'nprogress/nprogress.css';
nProgress.configure({ showSpinner: false, trickleSpeed: 400 });
window.nProgress = nProgress;
window.addEventListener('beforeunload', function () { nProgress.start(); });
window.addEventListener('load', function () { nProgress.done(); });
window.addEventListener('pageshow', function (e) { if (e.persisted) nProgress.done(); });

import Swal from 'sweetalert2';
window.Swal = Swal;

import './styles/app-defer.scss';

import '@glitchr/flowjs/stops';
import '@glitchr/flowjs/navigation';
import Base from '../vendor/omniscient/omnibase/assets/boot.js';

// The documentation pages' copy buttons, current heading and folded contents,
// and the site's search over its prebuilt JSON index.
import './docs.js';
import './search.js';

window.addEventListener('transparent:load', function () {
    var header = document.querySelector('.om-header');
    if (header) header.dispatchEvent(new CustomEvent('menu:close'));
});

Base.boot({
    // No Sticky effect on this site: only the stops and the navigation are imported.
    sticky: false,
    // The home page's sections are magnetic (StickyStops, .sticky-stop). The arrow
    // keys go from section to section, as the page keys read a tall one: without
    // `keys` the arrow is the browser's 40px nudge, and the sections taller than
    // the screen are crept through line by line.
    // Going back up is free (flowjs 1.3, `up: false`): a scroll heading up rests where the reader
    // left it; a scroll heading down still settles on its section.
    // A section taller than the screen shows both its ends to the arrows (flowjs 1.4, `ends`):
    // down, to its bottom first, then to the next section's top; up the same way back.
    stops: { keys: true, up: false, ends: true },
    // Feeds and files load whole.
    exceptions: ['/blog/feed.xml', '/uploads/*'],
    current: '.om-nav a',
    transparent: { exit_duration: 200 }
});
