/* ---------- Knowledge base tabs ---------- */

export function initKbTabs() {
    var tabs = document.querySelectorAll('[data-kb-tab]');
    if (!tabs.length) return;

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var target = document.getElementById('kb-tab-' + tab.getAttribute('data-kb-tab'));
            document.querySelectorAll('#kb-tab-entries, #kb-tab-gaps').forEach(function (el) {
                el.style.display = 'none';
            });
            if (target) target.style.display = 'block';
        });
    });
}
