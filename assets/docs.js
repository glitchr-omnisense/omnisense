/*
 * The documentation pages' script (guides, manuals, news), no dependency:
 *
 *  - a "copy" button on each code block of the prose;
 *  - the heading the reader is at, marked in "on this page";
 *  - the contents folded behind a button on a narrow screen;
 *  - a version menu closed by a click elsewhere.
 *
 * Adapted from omnibase/docs' manual.js (MIT), without its server search:
 * the site's search is assets/search.js. A page may come whole or be
 * swapped in by transparent.js: everything is delegated from the document,
 * and what belongs to one page is set up again on "transparent:load".
 */
(function () {
    if (window.OmniscientDocs) return;

    var spy = null;

    function setup() {
        document.querySelectorAll('.manual-prose pre').forEach(function (pre) {
            if (pre.parentNode.classList.contains('doc-code')) return;
            var prose = pre.closest('.manual-prose');
            var block = document.createElement('div');
            block.className = 'doc-code';
            pre.parentNode.insertBefore(block, pre);
            block.appendChild(pre);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'doc-code-copy';
            button.textContent = prose.dataset.textCopy || 'Copy';
            block.appendChild(button);
        });

        if (spy) { spy.disconnect(); spy = null; }
        var links = {};
        document.querySelectorAll('.manual-toc a[href^="#"]').forEach(function (a) { links[decodeURIComponent(a.getAttribute('href').slice(1))] = a; });
        var ids = Object.keys(links);
        if (!ids.length || !('IntersectionObserver' in window)) return;
        var visible = {};
        spy = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) { visible[entry.target.id] = entry.isIntersecting; });
            var current = ids.filter(function (id) { return visible[id]; })[0];
            if (!current) return;
            ids.forEach(function (id) { links[id].classList.toggle('is-current', id === current); });
        }, { rootMargin: '-10% 0px -70% 0px' });
        ids.forEach(function (id) {
            var heading = document.getElementById(id);
            if (heading) spy.observe(heading);
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        var copy = target.closest('.doc-code-copy');
        if (copy) {
            var code = copy.parentNode.querySelector('pre');
            var prose = copy.closest('.manual-prose');
            var done = function () {
                copy.textContent = prose.dataset.textCopied || 'Copied';
                setTimeout(function () { copy.textContent = prose.dataset.textCopy || 'Copy'; }, 1600);
            };
            if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(code.textContent).then(done, function () {});
            return;
        }

        var menu = target.closest('[data-manual-menu]');
        if (menu) {
            var manual = menu.closest('.manual');
            var opened = manual.classList.toggle('is-menu-open');
            menu.setAttribute('aria-expanded', opened ? 'true' : 'false');
            return;
        }

        document.querySelectorAll('.manual-versions[open]').forEach(function (details) {
            if (!details.contains(target)) details.removeAttribute('open');
        });
    });

    window.OmniscientDocs = { setup: setup };
    window.addEventListener('transparent:load', setup);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
    else setup();
})();
