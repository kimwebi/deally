/* ---------- Suggestions ---------- */

import { dateNow } from './timer.js';

var deallySuggestionPools = {
    confident: [
        'Customer would like a discount of at least 15%. The <b>Confident</b> response is to anchor first, restate value and promise a <b>formal proposal</b> within 2 days.',
        'Customer confirmed budget in the $12k range. Confidently commit to a <b>tailored implementation plan</b> to move the deal forward.',
        'Customer leans on competitor <b>Comparison Grid</b>. Position our <b>tool migration speed</b> and <b>2-week pilot</b> as the safer choice.',
    ],
    steering: [
        'Recommend steering the conversation. The buyer keeps asking about <b>price</b> pivot to <b>business outcomes</b>: time saved, fewer missed follow-ups.',
        'Buyer signal: they said we can decide this quickly. <b>Steer</b> toward a clear negotiation close: offer a package with a longer contract.',
    ],
    competitor: [
        'Competitor mention detected they referenced <b>DealMate</b>. Compare our <b>native AI interventions</b> vs their manual coach notes.',
    ],
    expert: [
        'Expert mode: this buyer is a <b>product manager</b>. Keep suggestions to <b>requirements language</b>, not budget talk.',
    ],
    data: [
        'Heads up the buyer mentioned <b>data isolation</b> concerns. Whip this up: your deployment supports <b>single-tenant isolation</b>.',
    ],
};

export function deallySuggest() {
    var pool = document.getElementById('suggestions');
    var count = document.getElementById('sugg-count');
    if (!pool) return;

    var total = +6 + 2; // base always suggests
    var added = 0;

    function maybeAdd(type, key, idx) {
        if (added >= 2) return;
        var last = pool.querySelector('.suggestion:last-child');
        if (last && last.getAttribute('data-type') === type) return;
        if (Math.random() > 0.6) return;

        var poolList = deallySuggestionPools[type];
        if (!poolList) return;
        var items = idx === undefined ? poolList : poolList.slice(idx, idx + 1);
        added++;
        total++;

        for (var j = 0; j < items.length; j++) {
            setTimeout(function () {
                var el = document.createElement('div');
                el.className = 'suggestion ' + type;
                el.setAttribute('data-type', type);
                var src = type === 'competitor'
                    ? 'Source: live transcript Â· competitor detection'
                    : type === 'data'
                        ? 'Source: capability card'
                        : 'Source: playbook';
                el.innerHTML =
                    '<div class="suggestion-tag">' + type.toUpperCase() + '<span class="suggestion-confidence">AI Â· LIVE</span></div>' +
                    '<div class="suggestion-text">' + items[0] + '</div>' +
                    '<div class="suggestion-source">' + src + '</div>';
                pool.appendChild(el);
                pool.scrollTop = pool.scrollHeight;
            }, 320);
        }
    }

    // Competitor detection at call tick interval frequency
    var t = dateNow();
    if (t > 900) maybeAdd('competitor');
    if (t > 27000) maybeAdd('expert');
    if (t > 60000) maybeAdd('steering');
    if (t > 100000) maybeAdd('confident');

    if (count) count.textContent = String(total).padStart(2, '0');
}
