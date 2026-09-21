export function initThemeToggle() {
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    if (!buttons.length) return;

    function apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('deally-theme', theme);
        } catch (e) {}
        var cls = theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill';
        buttons.forEach(function (btn) {
            var icon = btn.querySelector('[data-theme-icon]');
            if (icon) icon.className = 'bi ' + cls + ' theme-icon';
            btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
        });
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            apply(current === 'dark' ? 'light' : 'dark');
        });
    });
}
