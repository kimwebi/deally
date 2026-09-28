/* ---------- Modals ---------- */

export function initModalSystem() {
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-open-modal]');
        if (trigger) {
            var id = trigger.getAttribute('data-open-modal');
            var url = trigger.getAttribute('data-modal-url');

            if (url) {
                openRemoteModal(id, trigger, url);
            } else {
                openModal(id, trigger);
            }
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

/**
 * Open a modal whose body is fetched on demand.
 *
 * A list of past calls cannot carry a full transcript per row, so each row
 * carries only a URL and the fragment is swapped into one reusable shell. The
 * previous body stays visible while the next one loads: replacing it with a
 * blank host would read as an empty record.
 */
export function openRemoteModal(id, trigger, url) {
    var modal = document.getElementById(id);
    if (!modal) return;

    var host = modal.querySelector('[data-modal-host]') || modal;
    var cached = trigger.getAttribute('data-modal-loaded');

    if (cached === url) {
        openModal(id, trigger);
        return;
    }

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
            if (!response.ok) throw new Error('Request failed: ' + response.status);
            return response.text();
        })
        .then(function (html) {
            host.innerHTML = html;
            trigger.setAttribute('data-modal-loaded', url);
            openModal(id, trigger);
        })
        .catch(function (error) {
            host.innerHTML =
                '<div class="modal"><div class="modal-header"><div class="modal-header-icon call">📞</div>' +
                '<div class="modal-header-body"><div class="modal-title">Could not load this call</div>' +
                '<div class="modal-subtitle">' + String(error.message) + '</div></div>' +
                '<button class="modal-close" data-close-modal>✕</button></div></div>';
            openModal(id, trigger);
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
