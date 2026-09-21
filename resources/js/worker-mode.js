/* ---------- Call worker mode (express/race) ---------- */

export function initWorkerMode() {
    var race = document.querySelector('.call-racing');
    if (race) {
        document.body.classList.add('call-racing');
    }
}
