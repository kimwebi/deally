/* ---------- Quick-add event type picker ---------- */

export function initQuickAdd() {
    document.querySelectorAll('.type-picker').forEach(function (picker) {
        picker.addEventListener('click', function (e) {
            var opt = e.target.closest('.type-option');
            if (!opt) return;

            picker.querySelectorAll('.type-option').forEach(function (o) {
                o.classList.remove('selected');
            });
            opt.classList.add('selected');

            var hidden = picker.closest('.modal').querySelector('[data-event-type]');
            if (hidden) hidden.value = opt.getAttribute('data-type');
        });
    });
}