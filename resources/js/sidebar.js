/*Sidebar functions*/
export function initSidebarMenu() {
    var toggle = document.getElementById('menu-toggle');
    var shell = document.querySelector('.shell');
    var backdrop = document.getElementById('menu-backdrop');
    if (!toggle || !shell) return;

    function setOpen(open) {
        shell.classList.toggle('menu-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
        setOpen(!shell.classList.contains('menu-open'));
    });

    if (backdrop) backdrop.addEventListener('click', function () {
        setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
}
