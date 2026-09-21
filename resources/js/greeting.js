/* DeAlly — time-of-day greeting for the topbar. */

document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('topbar-greeting');
    if (!el) return;

    var user = (el.getAttribute('data-greeting-user') || '').trim().split(/\s+/)[0] || '';

    var phrases = {
        morning: [
            'Good morning',
            'Rise and shine',
            'Morning',
            'Hope your morning is off to a great start',
            'Today is a great day to close deals',
        ],
        afternoon: [
            'Good afternoon',
            'Happy afternoon',
            'Hope your afternoon is productive',
            'Keep the momentum going',
        ],
        evening: [
            'Good evening',
            'Great work today',
            'Winding down well',
            'Happy evening',
        ],
        night: [
            'How is your night',
            'Late-night work is when deals get won',
            'Burning the midnight oil',
            'How is your night going',
        ],
    };

    function pick(list) {
        return list[Math.floor(Math.random() * list.length)];
    }

    function applyGreeting() {
        var hour = new Date().getHours();
        var pool;

        if (hour >= 5 && hour < 12) {
            pool = phrases.morning;
        } else if (hour >= 12 && hour < 17) {
            pool = phrases.afternoon;
        } else if (hour >= 17 && hour < 21) {
            pool = phrases.evening;
        } else {
            pool = phrases.night;
        }

        var phrase = pick(pool) + (user ? ', ' + user : '') + '!';
        if (el.textContent !== phrase) el.textContent = phrase;
    }

    applyGreeting();
    setInterval(applyGreeting, 60000);
});