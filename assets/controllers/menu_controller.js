import { Controller } from '@hotwired/stimulus';

/*
 * The header's menu on a narrow screen: the button opens and closes it,
 * Escape closes it, and so does a page swapped in by transparent.js
 * (app-defer.js sends `menu:close`).
 */
export default class extends Controller {
    static targets = ['button'];

    connect() {
        this.onKey = (event) => { if (event.key === 'Escape') this.close(); };
        document.addEventListener('keydown', this.onKey);
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKey);
    }

    toggle() {
        this.element.classList.contains('is-open') ? this.close() : this.open();
    }

    open() {
        this.element.classList.add('is-open');
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.element.classList.remove('is-open');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }
}
