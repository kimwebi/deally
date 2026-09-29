/* ---------- Customer contact role picker on the New Call form ----------

   The select offers the common titles; "Other…" reveals a text field for a
   title that is not listed. A select cannot submit a value it does not offer,
   so on submit the typed title is carried by a hidden field and the select is
   disabled (disabled controls do not submit). The server also maps the marker
   back defensively. */

var CONTACT_ROLE_OTHER = '__other__';

export function initCallRolePicker() {
    var select = document.getElementById('contact-role-select');
    if (!select) return;

    var otherInput = document.getElementById('contact-role-other');
    var form = select.closest('form');
    if (!otherInput || !form) return;

    function syncOtherVisibility() {
        var isOther = select.value === CONTACT_ROLE_OTHER;
        otherInput.hidden = !isOther;
        if (isOther) {
            otherInput.focus();
        }
    }

    select.addEventListener('change', syncOtherVisibility);

    form.addEventListener('submit', function () {
        if (select.value !== CONTACT_ROLE_OTHER) return;

        var typed = (otherInput.value || '').trim();

        if (!typed) {
            select.value = '';
            return;
        }

        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'contact_role';
        hidden.value = typed;
        select.disabled = true;
        select.parentNode.appendChild(hidden);
    });
}