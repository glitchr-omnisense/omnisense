/*
 * The site's search, in the reader's browser (templates/client/search.html.twig):
 * the index of the page's language (/search.json, /fr/search.json, written
 * with the site by App\Content\SearchIndex) is loaded once, then each
 * question is answered from it - every word has to be found (lower case,
 * accents ignored) in the title, the text, the headings or the extras; a
 * word in the title counts more, the whole title most. The question stays in
 * the address (?q=), so a search can be shared and the page reloaded.
 */
(function () {
    if (window.OmniscientSearch) return;

    var cache = {};
    var MAX = 60;

    function fold(text) {
        return (text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function load(url) {
        if (!cache[url]) {
            cache[url] = fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) {
                if (!r.ok) throw new Error(r.status);
                return r.json();
            }).then(function (index) {
                index.entries.forEach(function (e) {
                    e._t = fold(e.t);
                    e._all = fold([e.t, e.d, e.h, e.x].join(' '));
                });
                return index.entries;
            });
        }
        return cache[url];
    }

    function score(entry, words, whole) {
        var s = 0;
        for (var i = 0; i < words.length; i++) {
            if (entry._all.indexOf(words[i]) === -1) return -1;
            s += entry._t.indexOf(words[i]) !== -1 ? 10 : 1;
        }
        if (entry._t === whole) s += 100;
        if (entry.k === 'guide') s += 2;
        return s;
    }

    function render(root, entries, query) {
        var list = root.querySelector('[data-site-search-results]');
        var count = root.querySelector('[data-site-search-count]');
        var kinds = JSON.parse(root.dataset.kinds || '{}');
        var whole = fold(query).trim();
        var words = whole.split(/\s+/).filter(Boolean);
        list.textContent = '';
        if (!words.length) { count.textContent = ''; return; }
        var found = [];
        entries.forEach(function (e, i) {
            var s = score(e, words, whole);
            if (s >= 0) found.push([s, i, e]);
        });
        found.sort(function (a, b) { return b[0] - a[0] || a[1] - b[1]; });
        count.textContent = found.length === 0 ? root.dataset.none : found.length === 1 ? root.dataset.one : root.dataset.many.replace('%n', found.length);
        found.slice(0, MAX).forEach(function (hit) {
            var e = hit[2];
            var li = document.createElement('li');
            li.className = 'om-hit';
            var head = document.createElement('div');
            head.className = 'om-hit-head';
            var a = document.createElement('a');
            a.className = 'om-hit-name';
            a.href = e.u;
            a.textContent = e.t;
            var kind = document.createElement('span');
            kind.className = 'om-kind is-' + e.k;
            kind.textContent = kinds[e.k] || e.k;
            head.append(a, kind);
            if (e.k === 'doc' && e.x) {
                var where = document.createElement('code');
                where.className = 'om-hit-version';
                where.textContent = e.x;
                head.append(where);
            }
            li.append(head);
            if (e.d) {
                var p = document.createElement('p');
                p.className = 'om-hit-text';
                p.textContent = e.d;
                li.append(p);
            }
            list.append(li);
        });
    }

    function setup() {
        var root = document.querySelector('[data-site-search]');
        if (!root || root.dataset.ready) return;
        root.dataset.ready = '1';
        var input = root.querySelector('[data-site-search-input]');
        var form = input.form;
        input.value = new URLSearchParams(location.search).get('q') || '';
        load(root.dataset.siteSearch).then(function (entries) {
            var run = function () {
                render(root, entries, input.value);
                var q = input.value.trim();
                history.replaceState(history.state, '', location.pathname + (q ? '?q=' + encodeURIComponent(q) : ''));
            };
            input.addEventListener('input', run);
            form.addEventListener('submit', function (event) { event.preventDefault(); run(); });
            render(root, entries, input.value);
        }, function () {
            root.querySelector('[data-site-search-count]').textContent = root.dataset.none;
        });
        if (!input.value) input.focus();
    }

    window.OmniscientSearch = { setup: setup };
    window.addEventListener('transparent:load', setup);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
    else setup();
})();
