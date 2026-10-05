import { Controller } from '@hotwired/stimulus';

/*
 * The home page's mark: "Omni" stays, the suffix after it turns - base,
 * bus, trade... - and the sentence under it says what that family asks.
 * The list beside it holds every family: pointing at one, or giving it the
 * focus, shows it and stops the turning until the pointer leaves.
 *
 * Without movement (prefers-reduced-motion) nothing turns by itself: the
 * first family is shown, the list still answers.
 */
export default class extends Controller {
    static targets = ['slot', 'phrase', 'item'];
    static values = { interval: { type: Number, default: 2800 } };

    connect() {
        this.index = 0;
        this.still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.pause = () => this.stop();
        this.resume = () => this.start();
        this.visibility = () => (document.hidden ? this.stop() : this.start());
        this.element.addEventListener('mouseleave', this.resume);
        this.element.addEventListener('focusout', this.resume);
        document.addEventListener('visibilitychange', this.visibility);
        this.start();
    }

    disconnect() {
        this.stop();
        this.element.removeEventListener('mouseleave', this.resume);
        this.element.removeEventListener('focusout', this.resume);
        document.removeEventListener('visibilitychange', this.visibility);
    }

    start() {
        if (this.still || this.timer || this.itemTargets.length < 2) return;
        this.timer = setInterval(() => this.show((this.index + 1) % this.itemTargets.length), this.intervalValue);
    }

    stop() {
        clearInterval(this.timer);
        this.timer = null;
    }

    pick(event) {
        this.stop();
        this.show(this.itemTargets.indexOf(event.currentTarget));
    }

    show(index) {
        if (index < 0 || index === this.index || !this.hasSlotTarget) return;
        const item = this.itemTargets[index];
        this.index = index;

        // The suffix: the old one leaves upwards, the new one comes from below.
        this.slotTarget.querySelectorAll('.is-out').forEach((old) => old.remove());
        const current = this.slotTarget.querySelector('.is-in');
        const next = document.createElement('span');
        next.className = 'is-in';
        next.textContent = item.dataset.suffix;
        if (current) {
            current.className = 'is-out';
            setTimeout(() => current.remove(), 450);
        }
        this.slotTarget.appendChild(next);

        // The sentence: out, changed, in.
        if (this.hasPhraseTarget) {
            this.phraseTarget.classList.add('is-changing');
            setTimeout(() => {
                this.phraseTarget.textContent = item.dataset.phrase;
                this.phraseTarget.classList.remove('is-changing');
            }, 180);
        }

        this.itemTargets.forEach((other, i) => (i === index ? other.setAttribute('aria-current', 'true') : other.removeAttribute('aria-current')));
    }
}
