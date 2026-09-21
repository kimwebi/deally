/*Documentation opening and loading*/
export function initAuthDocs() {
    var toggler = document.getElementById('auth-docs-toggle');
    var group = toggler ? toggler.closest('.auth-docs') : null;
    var list = document.getElementById('auth-docs-list');
    if (!toggler || !group || !list) return;

    function setOpen(open) {
        group.classList.toggle('open', open);
        list.hidden = !open;
        toggler.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggler.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!group.classList.contains('open'));
    });

    document.addEventListener('click', function (e) {
        if (!group.contains(e.target)) setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
}
