import { Controller } from '@hotwired/stimulus';

/*
 * Light / dark. The choice is kept in the reader's browser (localStorage,
 * "om-theme") and applied by base.html.twig's inline script before the
 * first paint of the next page: the site is static, no server reads it.
 * Without a choice the system's preference holds (prefers-color-scheme).
 */
export default class extends Controller {
    toggle() {
        const html = document.documentElement;
        const dark = !(html.classList.contains('theme-mode-dark')
            || (!html.classList.contains('theme-mode-light') && window.matchMedia('(prefers-color-scheme: dark)').matches));
        const mode = dark ? 'dark' : 'light';

        html.classList.toggle('theme-mode-dark', dark);
        html.classList.toggle('theme-mode-light', !dark);

        try { localStorage.setItem('om-theme', mode); } catch (e) { /* private browsing: the choice holds for this page */ }
    }
}
