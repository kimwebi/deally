/* DeAlly — animated analog clock in the topbar */

(function () {
    var el = document.getElementById('analog-clock');
    if (!el) return;

    var handHour = el.querySelector('.achour');
    var handMin = el.querySelector('.acmin');
    var handSec = el.querySelector('.acsec');
    if (!handHour || !handMin || !handSec) return;

    var NUM_RADIUS = 30;

    var HOURS = [3, 6, 9, 12];

    for (var i = 0; i < HOURS.length; i++) {
        var hour = HOURS[i];
        var rad = (hour * 30 - 90) * Math.PI / 180;
        var num = document.createElement('span');
        num.className = 'acnum';
        num.style.left = (50 + NUM_RADIUS * Math.cos(rad)) + '%';
        num.style.top = (50 + NUM_RADIUS * Math.sin(rad)) + '%';
        num.textContent = hour;
        el.appendChild(num);
    }

    var last = { s: 0, m: 0, h: 0, sRounds: 0, mRounds: 0, hRounds: 0 };

    function tick() {
        var now = new Date();
        var secs = now.getSeconds() + now.getMilliseconds() / 1000;
        var mins = now.getMinutes() + secs / 60;
        var hours = (now.getHours() % 12) + mins / 60;

        var s = secs * 6;
        var m = mins * 6;
        var h = hours * 30;

        if (s < last.s) last.sRounds += 360;
        if (m < last.m) last.mRounds += 360;
        if (h < last.h) last.hRounds += 360;

        last.s = s;
        last.m = m;
        last.h = h;

        handSec.style.transform = 'rotate(' + (last.sRounds + s) + 'deg)';
        handMin.style.transform = 'rotate(' + (last.mRounds + m) + 'deg)';
        handHour.style.transform = 'rotate(' + (last.hRounds + h) + 'deg)';

        el.setAttribute('title', 'Local time · ' + now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
    }

    tick();
    setInterval(tick, 250);
})();