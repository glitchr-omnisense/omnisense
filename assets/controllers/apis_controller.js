import { Controller } from '@hotwired/stimulus';

/*
 * The cards of /apis (templates/client/apis/index.html.twig). Every card is
 * in the page; this file shows the filters and narrows the cards without
 * asking the server. Tags combine: within a group, any of the tags checked
 * (omnilang or omnical); across groups, all of them (and no key needed). A
 * card carries what it is filtered by in its data-* attributes - a family
 * the union of its providers' auth and commercial use, so that it stays
 * when one of its providers matches. The text search folds case and accents
 * as App\Twig\ApiExtension::haystack() does. The address follows
 * (?family=omnilang,omnical&auth=none&q=deepl), so a filtered view can be
 * shared: a static host answers the same page whatever the query, and this
 * file reads it again.
 */
const GROUPS = ['status', 'auth', 'commercial', 'help', 'family', 'capability'];
const ATTRIBUTE = { status: 'status', auth: 'auth', commercial: 'commercial', help: 'help', family: 'family', capability: 'capabilities' };

export default class extends Controller {
    static targets = ['form', 'q', 'list', 'count', 'families', 'capabilities', 'reset', 'noFamily', 'noProvider'];
    static values = { url: String };

    connect() {
        this.formTarget.hidden = false;
        this.cards = this.listTargets.flatMap((list) => [...list.children]);
        // A shared address (?family=omnilang,omnical&auth=none), or another page's link as a fragment
        // (#family-omnilang.help-1: what the site's links carry, a static page having no query of its own).
        const params = new URLSearchParams(location.search);
        for (const token of location.hash.slice(1).split('.')) {
            const match = /^(status|auth|commercial|help|family|capability)-([a-z0-9-]+)$/.exec(token);
            if (match) params.set(match[1], [params.get(match[1]), match[2]].filter(Boolean).join(','));
        }
        if (params.has('q')) this.qTarget.value = params.get('q');
        for (const group of GROUPS) {
            const wanted = (params.get(group) || '').split(',').filter(Boolean);
            for (const input of this.inputs(group)) input.checked = wanted.includes(input.value);
        }
        // A group with a tag checked from the address opens, so that the tag can be seen and unchecked.
        for (const details of [this.familiesTarget, this.capabilitiesTarget]) {
            if (details.querySelector('input:checked')) details.open = true;
        }
        this.apply(false);
    }

    inputs(group) {
        return [...this.formTarget.querySelectorAll(`input[name="${group}"]`)];
    }

    submit(event) {
        event.preventDefault();
        this.apply(true);
    }

    filter() {
        cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.apply(true));
    }

    reset() {
        this.qTarget.value = '';
        for (const input of this.formTarget.querySelectorAll('input[type="checkbox"]')) input.checked = false;
        this.apply(true);
        this.qTarget.focus();
    }

    apply(write) {
        const words = fold(this.qTarget.value).split(/\s+/).filter(Boolean);
        const picked = {};
        for (const group of GROUPS) picked[group] = this.inputs(group).filter((i) => i.checked).map((i) => i.value);

        let families = 0;
        let providers = 0;
        for (const card of this.cards) {
            const shown = GROUPS.every((group) => matches(card, group, picked[group]))
                && words.every((word) => card.dataset.text.includes(word));
            card.hidden = !shown;
            if (shown) card.dataset.kind === 'family' ? families++ : providers++;
        }
        this.noFamilyTarget.hidden = families > 0;
        this.noProviderTarget.hidden = providers > 0;
        this.countTarget.textContent = this.countTarget.dataset.template.replace('%f', families).replace('%p', providers);
        for (const details of [this.familiesTarget, this.capabilitiesTarget]) {
            const n = details.querySelectorAll('input:checked').length;
            details.querySelector('[data-picked]').textContent = n ? `(${n})` : '';
        }
        const any = words.length > 0 || GROUPS.some((group) => picked[group].length > 0);
        this.resetTarget.disabled = !any;

        if (!write) return;
        const params = new URLSearchParams();
        if (this.qTarget.value.trim()) params.set('q', this.qTarget.value.trim());
        for (const group of GROUPS) if (picked[group].length) params.set(group, picked[group].join(','));
        const query = params.toString().replace(/%2C/g, ',');
        history.replaceState(history.state, '', this.urlValue + (query ? '?' + query : '') + (/^#card-/.test(location.hash) ? location.hash : ''));
    }
}

function matches(card, group, picked) {
    if (!picked.length) return true;
    const values = (card.dataset[ATTRIBUTE[group]] || '').split(' ');
    return picked.some((value) => values.includes(value));
}

function fold(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}
