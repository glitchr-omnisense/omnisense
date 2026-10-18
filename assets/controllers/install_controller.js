import { Controller } from '@hotwired/stimulus';

/*
 * Get started (templates/client/install/index.html.twig). The form is a
 * plain GET form and works without this file. With it: the rows of the need
 * chosen open (the core, families and their providers, a bridge, a prepared
 * structure), the others close; several families can be chosen, and the
 * providers of the chosen ones show as chips, family by family (every
 * family's are in the page: another family shows at once), each family
 * counted and the whole counted ("38 providers in omnibus + omnilex"); the
 * field narrows them by name ("12 of 38"), Enter ticks the only one left.
 * A changed count ticks (not with reduced motion); the chips' box keeps its
 * height while a filter narrows it, so nothing under it jumps as one types.
 * At each change the answer alone is asked of the server (?fragment=1) and
 * put in place, the address bar follows, the "Compose" button is not needed.
 * Each code block gets a copy button.
 */
export default class extends Controller {
    static targets = ['answer', 'send', 'need', 'family', 'filter', 'groups', 'group', 'count', 'browse'];
    static values = { url: String, copy: { type: String, default: 'Copier' }, copied: { type: String, default: 'Copié' } };

    connect() {
        this.sendTarget.hidden = true;
        // The field narrows the chips: what it holds is a filter, not names to send.
        this.filterTarget.removeAttribute('name');
        this.filterTarget.value = '';
        this.decorate();
        this.reflect();
        this.render(false);
    }

    update() {
        this.reflect();
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.ask(), 120);
    }

    /* A family ticked or not: its providers show or go (kept ticked, not sent, for when it comes back). */
    family() {
        this.render(true);
        this.update();
    }

    pick() {
        this.render(true);
        this.update();
    }

    filter() {
        const groups = this.groupsTarget;
        if (this.filterTarget.value.trim() && !groups.style.minHeight) {
            // The box keeps the height it had before the filter: the answer under it does not move as one types.
            groups.style.minHeight = groups.getBoundingClientRect().height + 'px';
        } else if (!this.filterTarget.value.trim()) {
            groups.style.minHeight = '';
        }
        this.render(true);
    }

    filterKey(event) {
        if (event.key === 'Enter') {
            // Enter never sends the page: it ticks the only provider the filter leaves.
            event.preventDefault();
            const left = this.visibleChips();
            if (left.length === 1) {
                left[0].checked = !left[0].checked;
                this.clearFilter();
                this.pick();
            }
        } else if (event.key === 'Escape' && this.filterTarget.value) {
            event.preventDefault();
            this.clearFilter();
        }
    }

    clearFilter() {
        this.filterTarget.value = '';
        this.groupsTarget.style.minHeight = '';
        this.render(true);
    }

    chosenFamilies() {
        return this.familyTargets.filter((input) => input.checked).map((input) => input.value);
    }

    visibleChips() {
        return this.groupTargets.filter((g) => !g.hidden).flatMap((g) => [...g.querySelectorAll('input[name="provider[]"]')].filter((i) => !i.closest('.om-chip').hidden));
    }

    /* The chosen families' providers shown, the filter applied, every count written. */
    render(animate) {
        const families = this.chosenFamilies();
        const words = this.filterTarget.value.toLowerCase().split(/[\s,;]+/).filter(Boolean);
        let total = 0;
        let shown = 0;
        let picked = 0;
        const names = [];
        this.groupTargets.forEach((group) => {
            const on = families.includes(group.dataset.family);
            group.hidden = !on;
            const chips = [...group.querySelectorAll('input[name="provider[]"]')];
            let left = 0;
            chips.forEach((input) => {
                input.disabled = !on || this.familyRowClosed();
                const match = !words.length || words.some((w) => input.dataset.short.includes(w) || input.value.includes(w));
                input.closest('.om-chip').hidden = !match;
                if (match) left++;
            });
            const tally = group.querySelector('.om-provider-tally');
            this.write(tally, words.length ? left + '/' + chips.length : String(chips.length), animate && on);
            const none = group.querySelector('.om-provider-none');
            if (none && chips.length) none.hidden = left > 0;
            if (on) {
                total += chips.length;
                shown += left;
                picked += chips.filter((i) => i.checked).length;
                if (names.length < 3) names.push(...chips.slice(0, 2).map((i) => i.dataset.short));
            }
        });

        // No family, nothing to narrow nor show: the line under says to choose one.
        this.groupsTarget.hidden = !families.length;
        this.filterTarget.parentNode.hidden = !families.length;
        this.writeCount(families, total, shown, picked, words.length > 0, animate);
        this.filterTarget.placeholder = names.slice(0, 3).join(' ');
        const browse = new URL(this.browseTarget.href, location.href);
        if (families.length === 1) browse.searchParams.set('family', families[0]); else browse.searchParams.delete('family');
        this.browseTarget.href = browse.pathname + browse.search;
    }

    familyRowClosed() {
        return this.needTargets.some((row) => row.hidden && row.contains(this.groupsTarget));
    }

    /* "38 providers in omnibus + omnilex · 2 chosen", or "12 of 38 in ...": the numbers in their own boxes, to tick. */
    writeCount(families, total, shown, picked, filtered, animate) {
        const line = this.countTarget;
        const data = line.dataset;
        const before = [...line.querySelectorAll('.om-num')].map((n) => n.textContent);
        if (!families.length) {
            line.textContent = data.none;
            return;
        }
        const num = (n) => '\u0000' + n + '\u0001';
        const list = families.join(' + ');
        let text = filtered
            ? data.of.replace('%s', num(shown)).replace('%n', num(total)).replace('%f', list)
            : (total === 1 ? data.one.replace('1', num(1)) : data.other.replace('%n', num(total))).replace('%f', list);
        if (picked) text += ' · ' + (picked === 1 ? data.pickedOne.replace('1', num(1)) : data.pickedOther.replace('%n', num(picked)));
        line.textContent = '';
        const parts = text.split(/\u0000|\u0001/);
        parts.forEach((part, i) => {
            if (i % 2) {
                const b = document.createElement('b');
                b.className = 'om-num';
                b.textContent = part;
                line.appendChild(b);
            } else if (part) {
                line.appendChild(document.createTextNode(part));
            }
        });
        if (animate) {
            line.querySelectorAll('.om-num').forEach((n, i) => { if (before[i] !== undefined && before[i] !== n.textContent) this.tick(n); });
        }
    }

    write(element, text, animate) {
        if (element.textContent === text) return;
        element.textContent = text;
        if (animate) this.tick(element);
    }

    /* A number that changed ticks once (the stylesheet leaves it still with reduced motion). */
    tick(element) {
        element.classList.remove('is-ticking');
        void element.offsetWidth;
        element.classList.add('is-ticking');
    }

    /* The rows of the need chosen open, the others close. */
    reflect() {
        const need = (this.element.querySelector('input[name="need"]:checked') || {}).value || 'family';
        // A closed row's fields are not sent: the address keeps only what the answer reads.
        this.needTargets.forEach((row) => {
            row.hidden = row.dataset.need !== need;
            row.querySelectorAll('input, select').forEach((field) => {
                const group = field.closest('.om-provider-group');
                field.disabled = row.hidden || (group !== null && group.hidden);
            });
        });
        this.filterTarget.removeAttribute('name');
    }

    async ask() {
        const params = new URLSearchParams(new FormData(this.element));
        // No family ticked is said, not left out: left out, the server would take the first family.
        if (!this.familyRowClosed() && !params.has('family[]')) params.set('family', '');
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
