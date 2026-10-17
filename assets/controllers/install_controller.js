import { Controller } from '@hotwired/stimulus';

/*
 * Get started (templates/client/install/index.html.twig). The form is a
 * plain GET form and works without this file. With it: the rows of the need
 * chosen open (the core, a family and its providers, a bridge, a prepared
 * structure), the others close; the providers are typed by name, with
 * suggestions from the search (/packages.json, the family's providers whose
 * name or description holds the word being typed); at each change the
 * answer alone is asked of the server (?fragment=1) and put in place, the
 * address bar follows, the "Compose" button is not needed. Each code block
 * gets a copy button.
 */
export default class extends Controller {
    static targets = ['answer', 'send', 'need', 'providers', 'suggestions', 'hint', 'browse'];
    static values = { url: String, suggest: String, copy: { type: String, default: 'Copier' }, copied: { type: String, default: 'Copié' } };

    connect() {
        this.sendTarget.hidden = true;
        this.decorate();
        this.reflect();
        this.outside = (event) => { if (!this.providersTarget.parentNode.contains(event.target)) this.close(); };
        document.addEventListener('click', this.outside);
    }

    disconnect() {
        document.removeEventListener('click', this.outside);
    }

    update() {
        this.reflect();
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.ask(), 120);
    }

    /* Another family: its providers are not the last one's. */
    family(event) {
        this.providersTarget.value = '';
        const input = event.target;
        const template = this.hintTarget.dataset.template;
        this.hintTarget.firstChild.textContent = template.replace('%n', input.dataset.count).replace('%f', input.value) + ' ';
        const browse = new URL(this.browseTarget.href, location.href);
        browse.searchParams.set('family', input.value);
        this.browseTarget.href = browse.pathname + browse.search;
        this.update();
    }

    /* The rows of the need chosen open, the others close. */
    reflect() {
        const need = (this.element.querySelector('input[name="need"]:checked') || {}).value || 'family';
        // A closed row's fields are not sent: the address keeps only what the answer reads.
        this.needTargets.forEach((row) => {
            row.hidden = row.dataset.need !== need;
            row.querySelectorAll('input, select').forEach((field) => { field.disabled = row.hidden; });
        });
    }

    /* Suggestions for the word being typed (the last one), among the family's providers. */
    suggest() {
        clearTimeout(this.suggesting);
        this.suggesting = setTimeout(async () => {
            const family = (this.element.querySelector('input[name="family"]:checked') || {}).value;
            if (!family) return;
            const word = this.providersTarget.value.split(/[\s,;]+/).pop();
            const typed = this.providersTarget.value.toLowerCase().split(/[\s,;]+/).filter(Boolean);
            const params = new URLSearchParams({ family, kind: 'provider', q: word });
            try {
                const response = await fetch(this.suggestValue + '?' + params.toString(), { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const found = (await response.json()).filter((p) => !typed.slice(0, -1).includes(p.short));
                this.show(found);
            } catch (e) {
                // Offline: the field still takes the names typed.
            }
        }, 90);
    }

    show(found) {
        const list = this.suggestionsTarget;
        list.innerHTML = '';
        found.forEach((provider, i) => {
            const item = document.createElement('li');
            item.id = 'om-provider-' + i;
            item.setAttribute('role', 'option');
            item.dataset.short = provider.short;
            const name = document.createElement('b');
            name.textContent = provider.name;
            item.appendChild(name);
            if (provider.description) {
                const text = document.createElement('span');
                text.textContent = provider.description;
                item.appendChild(text);
            }
            item.addEventListener('mousedown', (event) => { event.preventDefault(); this.pick(provider.short); });
            list.appendChild(item);
        });
        list.hidden = found.length === 0;
        this.providersTarget.setAttribute('aria-expanded', String(found.length > 0));
        this.active = -1;
    }

    close() {
        this.suggestionsTarget.hidden = true;
        this.providersTarget.setAttribute('aria-expanded', 'false');
        this.providersTarget.removeAttribute('aria-activedescendant');
    }

    /* The word being typed becomes the provider picked, and a space makes room for the next. */
    pick(short) {
        const words = this.providersTarget.value.split(/[\s,;]+/);
        words[words.length - 1] = short;
        this.providersTarget.value = words.filter(Boolean).join(' ') + ' ';
        this.close();
        this.providersTarget.focus();
        this.update();
    }

    navigate(event) {
        const items = [...this.suggestionsTarget.children];
        if (this.suggestionsTarget.hidden || !items.length) {
            if (event.key === 'Enter') { event.preventDefault(); this.update(); }
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            this.active = (this.active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items.forEach((item, i) => item.setAttribute('aria-selected', String(i === this.active)));
            this.providersTarget.setAttribute('aria-activedescendant', items[this.active].id);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (this.active >= 0) this.pick(items[this.active].dataset.short); else { this.close(); this.update(); }
        } else if (event.key === 'Escape') {
            this.close();
        }
    }

    async ask() {
        const params = new URLSearchParams(new FormData(this.element));
        const query = params.toString();
        history.replaceState(history.state, '', this.urlValue + (query ? '?' + query : ''));
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
