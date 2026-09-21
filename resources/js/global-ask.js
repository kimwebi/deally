/*Ask Deally Anything functions*/
export function initGlobalAsk() {
    var input = document.getElementById('cmdbar-input');
    var form = document.getElementById('cmdbar-form');
    var results = document.getElementById('cmdbar-results');
    if (!input || !form || !results) return;

    var navEl = document.getElementById('cmdbar-nav');
    var nav = [];
    try {
        nav = navEl ? JSON.parse(navEl.textContent) : [];
    } catch (e) {
        nav = [];
    }

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    function closeResults() {
        results.hidden = true;
        results.innerHTML = '';
    }

    function matchNav(text) {
        var t = text.toLowerCase().trim();
        if (!t) return nav.slice(0, 5);
        return nav.filter(function (item) {
            return item.label.toLowerCase().indexOf(t) !== -1
                || (item.keywords + '').toLowerCase().indexOf(t) !== -1;
        }).slice(0, 6);
    }

    function renderNav() {
        var items = matchNav(input.value);
        if (!items.length) {
            results.innerHTML = '<div class="cmdbar-no-match">No matching pages â€” press Enter to ask DeAlly.</div>';
            return;
        }
        var html = items.map(function (item) {
            return '<a class="cmdbar-nav-item" href="' + esc(item.href) + '">'
                + '<i class="bi ' + esc(item.icon) + '"></i><span>' + esc(item.label) + '</span>'
                + '<span class="cmdbar-nav-go"><i class="bi bi-arrow-right"></i></span></a>';
        }).join('');
        results.innerHTML = html;
    }

    input.addEventListener('focus', function () {
        results.hidden = false;
        renderNav();
    });

    input.addEventListener('input', function () {
        results.hidden = false;
        renderNav();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = (input.value || '').trim();
        if (!text) return;

        results.hidden = false;
        results.innerHTML = '<div class="cmdbar-thinking"><span class="live-dot"></span>DeAlly is thinkingâ€¦</div>';

        fetch('/app/ask', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ text: text }),
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (json && json.ok && json.answer) {
                    var kb = nav.find(function (item) { return item.label === 'Knowledge Base'; });
                    results.innerHTML =
                        '<div class="cmdbar-result-label">DeAlly Â· grounded in your knowledge base</div>' +
                        '<div class="cmdbar-result-answer">' + esc(json.answer) + '</div>' +
                        (kb ? '<a class="cmdbar-result-link" href="' + esc(kb.href) + '"><i class="bi bi-book"></i> Explore the Knowledge Base</a>' : '');
                } else {
                    results.innerHTML =
                        '<div class="cmdbar-result-error">Could not find an answer right now â€” try a more specific question.</div>';
                }
            })
            .catch(function () {
                results.innerHTML = '<div class="cmdbar-result-error">Network error â€” try again.</div>';
            });
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeResults();
            input.blur();
        }
    });

    document.addEventListener('keydown', function (e) {
        var tag = e.target && e.target.tagName;

        if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            input.focus();
            input.select();
            return;
        }

        if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && !e.metaKey && !e.ctrlKey && !e.altKey) {
            e.preventDefault();
            input.focus();
        }
    });

    document.addEventListener('click', function (e) {
        if (!results.hidden && e.target && !e.target.closest('.topbar-center')) closeResults();
    });
}
