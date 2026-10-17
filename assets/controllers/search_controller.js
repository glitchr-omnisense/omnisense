import { Controller } from '@hotwired/stimulus';

/*
 * The package search (templates/client/catalogue/index.html.twig). The form
 * is a plain GET form and works without this file. With it the rows are
 * narrowed as one types, without asking the server: every row is on the
 * page, each with the haystack App\Catalogue\PackageSearch searches (name,
 * family, description, keywords; lower case, without accents), and scored
 * as it scores - each word has to be found, a word in the name counts more,
 * the exact short name most; without words, the order of the floors. The
 * address bar follows, so a search can be shared.
 */
export default class extends Controller {
    static targets = ['q', 'family', 'list', 'count', 'send'];
    static values = { url: String };

    connect() {
        this.sendTarget.hidden = true;
        this.rows = [...this.listTarget.children];
    }

    filter() {
        cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.apply());
    }

    apply() {
        const words = fold(this.qTarget.value).split(/\s+/).filter(Boolean);
        const family = this.familyTarget.value;
        const kind = (this.element.querySelector('input[name="kind"]:checked') || {}).value || '';

        const found = [];
        for (const row of this.rows) {
            const score = (!family || row.dataset.family === family) && (!kind || row.dataset.kind === kind) ? scoreOf(row, words) : null;
            row.hidden = score === null;
            if (score !== null) found.push([score, Number(row.dataset.order), row]);
        }
        found.sort((a, b) => b[0] - a[0] || a[1] - b[1]);
        const hidden = this.rows.filter((row) => row.hidden);
        this.listTarget.append(...found.map(([, , row]) => row), ...hidden);

        const count = this.countTarget.dataset;
        this.countTarget.textContent = found.length === 0 ? count.none : found.length === 1 ? count.one : count.many.replace('%n', found.length);

        const params = new URLSearchParams();
        if (this.qTarget.value.trim()) params.set('q', this.qTarget.value.trim());
        if (family) params.set('family', family);
        if (kind) params.set('kind', kind);
        const query = params.toString();
        history.replaceState(history.state, '', this.urlValue + (query ? '?' + query : ''));
    }
}

function fold(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

// As PackageSearch::score(): null when a word is missing.
function scoreOf(row, words) {
    let score = 0;
    for (const word of words) {
        if (!row.dataset.text.includes(word)) return null;
        score += row.dataset.short === word || row.dataset.name === word ? 100 : row.dataset.name.includes(word) ? 10 : 1;
    }

    return score;
}
