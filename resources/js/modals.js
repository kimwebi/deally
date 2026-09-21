/* ---------- Modals ---------- */

export function initModalSystem() {
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-open-modal]');
        if (trigger) {
            var id = trigger.getAttribute('data-open-modal');
            openModal(id, trigger);
        }

        var close = e.target.closest('[data-close-modal]');
        if (close) {
            var m = close.closest('.modal-overlay');
            if (m) m.classList.remove('open');
        }

        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('open');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.open').forEach(function (m) {
                m.classList.remove('open');
            });
        }
    });
}

export function openModal(id, trigger) {
    var modal = document.getElementById(id);
    if (!modal) return;

    if (trigger) {
        modal.querySelectorAll('[data-fill]').forEach(function (el) {
            var key = el.getAttribute('data-fill');
            var val = trigger.getAttribute('data-' + key);
            if (val) el.innerHTML = val;
        });

        var reviewTarget = modal.querySelector('[data-review-target]');
        if (reviewTarget) {
            var reviewUrl = trigger.getAttribute('data-review-url');
            if (reviewUrl) {
                reviewTarget.setAttribute('href', reviewUrl);
                reviewTarget.style.display = '';
            } else {
                reviewTarget.style.display = 'none';
            }
        }
    }

    modal.classList.add('open');
}
