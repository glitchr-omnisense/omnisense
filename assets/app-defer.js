/*
 * After first paint: the glitchr plugins, started by glitchr/omnibase's
 * Base.boot() (vendor/glitchr/omnibase/assets/boot.js) - stickyjs, then
 * transparentjs (links swap #content in place, the back office opens as a
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

import '@glitchr/stickyjs';
import '@glitchr/transparentjs';
import Base from '../vendor/glitchr/omnibase/assets/boot.js';

window.addEventListener('transparent:load', function () {
    var header = document.querySelector('.om-header');
    if (header) header.dispatchEvent(new CustomEvent('menu:close'));
});

Base.boot({
    nest: ['/admin*'],
    // The home page's sections are magnetic (StickyStops, [data-sticky-stop]).
    stops: true,
    // Feeds and files load whole.
    exceptions: ['/blog/feed.xml', '/uploads/*'],
    current: '.om-nav a',
    transparent: { exit_duration: 200 }
});
