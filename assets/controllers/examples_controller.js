import { Controller } from '@hotwired/stimulus';

/*
 * The examples on the home page's floors turn, one at a time: an extension
 * on the left, then a family with one of its providers on the right, then
 * another extension... A family and the provider beside it always share
 * their domain (glitchr/omnibank and omnibank/powens change together), and
 * a family comes back with another of its providers.
 *
 * Only while the drawing is on screen and the page is shown; without
 * movement (prefers-reduced-motion) nothing turns.
 *
 * A row's chips keep one width while the names turn: the longest name they
 * may show, or less when the row cannot hold that many beside its "+ N" - a
 * longer name is then cut (…, the whole of it in its title). Measured once
 * the fonts are in and again when the row's width changes: the floors below
 * never move while the names turn.
 */
export default class extends Controller {
    static targets = ['component', 'family', 'provider'];
    static values = { extensions: Array, pairs: Array, interval: { type: Number, default: 2200 } };

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        this.reserve();
        document.fonts?.ready.then(() => this.reserve());
        this.resized = new ResizeObserver(() => this.reserve());
        this.resized.observe(this.element);

        this.step = 0;
        this.slot = { component: 0, pair: 0 };
        this.shownExtensions = this.componentTargets.map((_, i) => i);
        this.shownPairs = this.familyTargets.map((_, i) => i);
        this.nextExtension = this.shownExtensions.length;
        this.nextPair = this.shownPairs.length;
        this.turns = this.pairsValue.map(() => 0);
        this.shownPairs.forEach((i) => (this.turns[i] = 1));

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

    tick() {
        const left = this.extensionsValue.length > this.componentTargets.length;
        const right = this.pairsValue.length > this.familyTargets.length;
        if (!left && !right) return this.stop();

        const side = (this.step++ % 2 === 0 && left) || !right ? 'left' : 'right';
        side === 'left' ? this.turnExtension() : this.turnPair();
    }

    turnExtension() {
        const slot = this.slot.component++ % this.componentTargets.length;
        const next = this.pick(this.extensionsValue.length, this.shownExtensions, 'nextExtension');
        this.shownExtensions[slot] = next;
        this.swap(this.componentTargets[slot], this.extensionsValue[next]);
    }

    turnPair() {
        const slot = this.slot.pair++ % this.familyTargets.length;
        const next = this.pick(this.pairsValue.length, this.shownPairs, 'nextPair');
        const pair = this.pairsValue[next];
        this.shownPairs[slot] = next;
        const provider = pair.providers[this.turns[next]++ % pair.providers.length];

        // The family first, its provider a beat after: one change, read as one.
        this.swap(this.familyTargets[slot], pair.family);
        if (this.providerTargets[slot]) setTimeout(() => this.swap(this.providerTargets[slot], provider), 140);
    }

    // A row's chips as wide as the widest name their kind may show, or the share of the row left beside its "+ N".
    reserve() {
        const kinds = {
            component: this.extensionsValue,
            family: this.pairsValue.map((pair) => pair.family),
            provider: this.pairsValue.flatMap((pair) => pair.providers),
        };
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
            const width = Math.floor(Math.max(narrowest, Math.min(widest, share)));
            chips.forEach((chip) => {
                chip.style.width = `${width}px`;
                chip.title = chip.textContent;
            });
        }
    }

    // The next name not on screen, going round the list.
    pick(length, shown, cursor) {
        let next = this[cursor] % length;
        while (shown.includes(next)) next = (next + 1) % length;
        this[cursor] = next + 1;

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
