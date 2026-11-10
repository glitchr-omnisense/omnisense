import { Controller } from '@hotwired/stimulus';

/*
 * The table of /apis/policies (templates/client/apis/policies.html.twig).
 * Every row is in the page; this file shows the filters and narrows the
 * rows without asking the server: within a group, any of the tags checked
 * (omnical or omnilang); across groups, all of them (and commercial use
 * forbidden). The text search folds case and accents as
 * App\Twig\ApiExtension::haystack() does. The address follows
 * (?family=omnical&commercial=forbidden,unknown&q=google), and a fragment
 * (#family-omnical.commercial-unknown) reads the same, as on /apis.
 */
const GROUPS = ['family', 'commercial'];

export default class extends Controller {
    static targets = ['form', 'q', 'list', 'count', 'families', 'reset', 'none'];
    static values = { url: String };

    connect() {
        this.formTarget.hidden = false;
        this.rows = [...this.listTarget.children];
        const params = new URLSearchParams(location.search);
        for (const token of location.hash.slice(1).split('.')) {
            const match = /^(family|commercial)-([a-z0-9-]+)$/.exec(token);
            if (match) params.set(match[1], [params.get(match[1]), match[2]].filter(Boolean).join(','));
        }
        if (params.has('q')) this.qTarget.value = params.get('q');
        for (const group of GROUPS) {
            const wanted = (params.get(group) || '').split(',').filter(Boolean);
            for (const input of this.inputs(group)) input.checked = wanted.includes(input.value);
        }
        if (this.familiesTarget.querySelector('input:checked')) this.familiesTarget.open = true;
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

        let shown = 0;
        for (const row of this.rows) {
            const show = GROUPS.every((group) => !picked[group].length || picked[group].includes(row.dataset[group]))
                && words.every((word) => row.dataset.text.includes(word));
            row.hidden = !show;
            if (show) shown++;
        }
        this.noneTarget.hidden = shown > 0;
        this.countTarget.textContent = this.countTarget.dataset.template.replace('%p', shown);
        const n = this.familiesTarget.querySelectorAll('input:checked').length;
        this.familiesTarget.querySelector('[data-picked]').textContent = n ? `(${n})` : '';
        this.resetTarget.disabled = !(words.length > 0 || GROUPS.some((group) => picked[group].length > 0));

        if (!write) return;
        const params = new URLSearchParams();
        if (this.qTarget.value.trim()) params.set('q', this.qTarget.value.trim());
        for (const group of GROUPS) if (picked[group].length) params.set(group, picked[group].join(','));
        const query = params.toString().replace(/%2C/g, ',');
        history.replaceState(history.state, '', this.urlValue + (query ? '?' + query : '') + (/^#policy-/.test(location.hash) ? location.hash : ''));
    }
}

function fold(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}
