import { Controller } from '@hotwired/stimulus';

/*
 * The install selector (templates/client/install/index.html.twig). The form
 * is a plain GET form and works without this file. With it: at each change
 * the answer alone is asked of the server (?fragment=1) and put in place,
 * the address bar follows, the "Compose" button is not needed, and the rows
 * that depend on a choice (omnibase's components: Symfony only) open and
 * close. Each code block gets a copy button.
 */
export default class extends Controller {
    static targets = ['answer', 'send', 'symfony', 'group'];
    static values = { url: String, copy: { type: String, default: 'Copier' }, copied: { type: String, default: 'Copié' } };

    connect() {
        this.sendTarget.hidden = true;
        this.decorate();
        this.reflect();
    }

    update() {
        this.reflect();
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.ask(), 120);
    }

    /* The rows that depend on the framework: omnibase and its components are Symfony bundles today. */
    reflect() {
        const framework = (this.element.querySelector('input[name="framework"]:checked') || {}).value;
        const symfony = framework === 'symfony';
        this.symfonyTargets.forEach((row) => {
            row.classList.toggle('is-off', !symfony);
            row.querySelectorAll('input').forEach((input) => { input.disabled = !symfony; });
        });
    }

    async ask() {
        const params = new URLSearchParams(new FormData(this.element));
        const query = params.toString();
        history.replaceState(null, '', this.urlValue + (query ? '?' + query : ''));
        params.set('fragment', '1');

        this.element.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(this.urlValue + '?' + params.toString(), { headers: { Accept: 'text/html' } });
            if (response.ok) {
                this.answerTarget.innerHTML = await response.text();
                this.decorate();
            }
        } catch (e) {
            // Offline: the last answer stays; the form still sends as a page.
        } finally {
            this.element.removeAttribute('aria-busy');
        }
    }

    /* A copy button on each code block of the answer. */
    decorate() {
        this.answerTarget.querySelectorAll('pre.om-code').forEach((pre) => {
            if (pre.querySelector('button')) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'om-copy';
            button.textContent = this.copyValue;
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(pre.querySelector('code').textContent);
                    button.textContent = this.copiedValue;
                    setTimeout(() => { button.textContent = this.copyValue; }, 1600);
                } catch (e) {
                    // No clipboard: the text can still be selected.
                }
            });
            pre.appendChild(button);
        });
    }
}
