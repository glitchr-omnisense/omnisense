import { Controller } from '@hotwired/stimulus';

/*
 * The examples on the home page's floors turn, one at a time: a family's
 * own package on the foundation's floor (glitchr/omnibus, then
 * omnisense/omnilab...), a prepared structure on the optional one
 * (omnibase/blog, then omnibase/agenda...). Each drawing has its own
 * controller and the kinds it holds; where both are, they take turns.
 * Providers are never shown here: they are the search's.
 *
 * Only while the drawing is on screen and the page is shown; without
 * movement (prefers-reduced-motion) nothing turns.
 *
 * A row's chips keep one width while the names turn: the longest name they
 * may show, or less when the row cannot hold that many beside its "+ N" - a
 * longer name is then cut (…, the whole of it in its title) - unless the row
 * is so narrow that a chip a line, whole, reads better (a phone). Measured once
 * the fonts are in and again when the row's width changes: the floors below
 * never move while the names turn.
 */
export default class extends Controller {
    static targets = ['component', 'family'];
    static values = { families: Array, structures: Array, interval: { type: Number, default: 2200 } };

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        this.reserve();
        document.fonts?.ready.then(() => this.reserve());
        this.resized = new ResizeObserver(() => this.reserve());
        this.resized.observe(this.element);

        this.step = 0;
        this.kinds = {
            family: { names: this.familiesValue, slot: 0 },
            component: { names: this.structuresValue, slot: 0 },
        };
        for (const [kind, state] of Object.entries(this.kinds)) {
            state.shown = this[`${kind}Targets`].map((_, i) => i);
            state.next = state.shown.length;
        }

        this.visible = false;
        this.observer = new IntersectionObserver(([entry]) => {
            this.visible = entry.isIntersecting;
            this.visible ? this.start() : this.stop();
        });
        this.observer.observe(this.element);
        this.visibility = () => (document.hidden ? this.stop() : this.visible && this.start());
        document.addEventListener('visibilitychange', this.visibility);
    }

    disconnect() {
        this.stop();
        this.observer?.disconnect();
        this.resized?.disconnect();
        if (this.visibility) document.removeEventListener('visibilitychange', this.visibility);
    }

    start() {
        if (this.timer) return;
        this.timer = setInterval(() => this.tick(), this.intervalValue);
    }

    stop() {
        clearInterval(this.timer);
        this.timer = null;
    }

    // The kinds with more names than chips take turns.
    tick() {
        const turning = Object.keys(this.kinds).filter((kind) => this.kinds[kind].names.length > this[`${kind}Targets`].length);
        if (!turning.length) return this.stop();

        this.turn(turning[this.step++ % turning.length]);
    }

    turn(kind) {
        const state = this.kinds[kind];
        const chips = this[`${kind}Targets`];
        const slot = state.slot++ % chips.length;
        const next = this.pick(state);
        state.shown[slot] = next;
        this.swap(chips[slot], state.names[next]);
    }

    // A row's chips as wide as the widest name their kind may show, or the share of the row left beside its "+ N".
    reserve() {
        const kinds = { component: this.structuresValue, family: this.familiesValue };
        for (const [kind, names] of Object.entries(kinds)) {
            const chips = this[`${kind}Targets`];
            if (!chips.length || !names.length) continue;
            const row = chips[0].parentNode;
            const probe = chips[0].cloneNode(false);
            probe.removeAttribute('data-examples-target');
            probe.setAttribute('aria-hidden', 'true');
            probe.style.cssText = 'position: absolute; visibility: hidden; width: auto';
            row.appendChild(probe);
            let widest = 0;
            for (const name of names) {
                probe.innerHTML = mark(name);
                widest = Math.max(widest, probe.getBoundingClientRect().width);
            }
            // The narrowest a chip may be: "omni" and a few letters, read; below it the row wraps (still without moving).
            probe.innerHTML = mark('omnixxxx/xxx');
            const narrowest = probe.getBoundingClientRect().width;
            probe.remove();

            const style = getComputedStyle(row);
            const gap = parseFloat(style.columnGap) || 0;
            const others = [...row.children].filter((child) => !chips.includes(child)).reduce((sum, child) => sum + child.getBoundingClientRect().width + gap, 0);
            const share = (row.clientWidth - others - gap * (chips.length - 1)) / chips.length;
            // Too narrow a share for the longest name (a phone): a chip a line, each as wide as the longest name, rather than
            // names cut to "glitchr/omn…" - the row is as many lines whatever name it shows, so nothing below moves.
            const whole = share < widest && widest <= row.clientWidth;
            const width = Math.ceil(whole ? widest : Math.max(narrowest, Math.min(widest, share)));
            chips.forEach((chip) => {
                chip.style.width = `${width}px`;
                chip.title = chip.textContent;
            });
        }
    }

    // The next name not on screen, going round the list.
    pick(state) {
        const length = state.names.length;
        let next = state.next % length;
        while (state.shown.includes(next)) next = (next + 1) % length;
        state.next = next + 1;

        return next;
    }

    swap(chip, name) {
        chip.classList.add('is-out');
        setTimeout(() => {
            chip.innerHTML = mark(name);
            chip.title = name;
            chip.classList.remove('is-out');
        }, 220);
    }
}

// As the omni_mark filter (App\Twig\CatalogueExtension::mark): "omni" in grey, the domain after it in blue.
function mark(name) {
    const escape = (text) => text.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

    return name.split('/').map((part) => {
        const match = /^omni(.+)$/.exec(part);

        return match ? `<span class="om-mark-omni">omni</span><b class="om-mark-domain">${escape(match[1])}</b>` : escape(part);
    }).join('/');
}
