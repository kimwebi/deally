/* ---------- Timer ---------- */

var _timerStart = null;

export function dateNow() {
    return window._deallyStart || Date.now();
}

export function initTimer() {
    var el = document.getElementById('call-timer');
    if (!el) return;

    var startedAt = Number(el.getAttribute('data-start') || 0);
    _timerStart = startedAt ? startedAt * 1000 : Date.now();
    window._deallyStart = _timerStart;

    setInterval(tick, 1000);
}

export function tick() {
    var el = document.getElementById('call-timer');
    if (!el) return;

    if (!window._deallyStart) window._deallyStart = Date.now();
    var m = Math.floor((Date.now() - window._deallyStart) / 60000);
    var s = Math.floor((Date.now() - window._deallyStart) % 60000 / 1000);
    el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}
