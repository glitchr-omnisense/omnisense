import { Controller } from '@hotwired/stimulus';

/*
 * Light / dark. omnibase's Themizer renders theme-mode-<mode> on <html>
 * from the USER/THEME/MODE cookie (USER/THEME/PREFERS_MODE resolves "auto"):
 * this writes both and swaps the class at once, so the next page - rendered
 * by the server, or swapped in by transparent.js - agrees.
 */
const YEAR = 60 * 60 * 24 * 365;

export default class extends Controller {
    toggle() {
        const html = document.documentElement;
        const dark = !(html.classList.contains('theme-mode-dark')
            || (!html.classList.contains('theme-mode-light') && window.matchMedia('(prefers-color-scheme: dark)').matches));
        const mode = dark ? 'dark' : 'light';

        html.classList.toggle('theme-mode-dark', dark);
        html.classList.toggle('theme-mode-light', !dark);

        // Cookies refused (omnibase/consent): asked for, kept for the visit only.
        const keep = window.Consent?.refused() ? '' : `; max-age=${YEAR}`;
        document.cookie = `USER/THEME/MODE=${mode}; path=/${keep}; SameSite=Lax`;
        document.cookie = `USER/THEME/PREFERS_MODE=${mode}; path=/${keep}; SameSite=Lax`;
    }
}
