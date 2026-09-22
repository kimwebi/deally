/* ---------- Toasts ---------- */

export function initToasts() {
    window.deallyToast = function (message, icon) {
        var toast = document.getElementById('toast');
        if (!toast) return;
        var text = toast.querySelector('.toast-text');
        var ic = toast.querySelector('.toast-icon');
        if (text) text.textContent = message;
        if (ic) ic.textContent = icon || '✓';
        toast.classList.add('show');
        clearTimeout(toast._t);
        toast._t = setTimeout(function () {
            toast.classList.remove('show');
        }, 2600);
    };

    var autoToast = document.getElementById('toast');
    if (autoToast && autoToast.hasAttribute('data-auto-toast')) {
        window.deallyToast(autoToast.getAttribute('data-auto-toast'));
    }
}
