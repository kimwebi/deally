/* DeAlly — shared application JS */

document.addEventListener('DOMContentLoaded', function () {
    initModalSystem();
    initLiveCall();
    initTimer();
    initToasts();
    initWorkerMode();
    initForms();
    initKbTabs();
    initThemeToggle();
});

function initThemeToggle() {
    var buttons = document.querySelectorAll('[data-theme-toggle]');
    if (!buttons.length) return;

    function apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('deally-theme', theme);
        } catch (e) {}
        var icon = theme === 'dark' ? '☀️' : '🌙';
        buttons.forEach(function (btn) {
            btn.textContent = icon;
            btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
        });
    }

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            apply(current === 'dark' ? 'light' : 'dark');
        });
    });
}

/* ---------- Modals ---------- */

function initModalSystem() {
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

function openModal(id, trigger) {
    var modal = document.getElementById(id);
    if (!modal) return;

    if (trigger) {
        modal.querySelectorAll('[data-fill]').forEach(function (el) {
            var key = el.getAttribute('data-fill');
            var val = trigger.getAttribute('data-' + key);
            if (val) el.innerHTML = val;
        });
    }

    modal.classList.add('open');
}

/* ---------- Live call simulation ---------- */

var deallyCallScript = null;

function initLiveCall() {
    var stream = document.getElementById('stream');
    var scriptEl = document.getElementById('live-script');
    if (!stream || !scriptEl) return;

    if (document.getElementById('live-mic-toggle')) {
        initLiveAssistant();
        return;
    }

    try {
        deallyCallScript = JSON.parse(scriptEl.textContent);
    } catch (err) {
        deallyCallScript = [];
    }

    if (!deallyCallScript.length) return;

    var idx = 0;
    var nextAt = 0;

    function pump() {
        requestAnimationFrame(function (t) {
            if (document.body.classList.contains('call-racing')) {
                while (idx < deallyCallScript.length) {
                    appendReason(deallyCallScript[idx]);
                    idx++;
                    deallySuggest();
                    tick();
                }
                requestAnimationFrame(pump);
                return;
            }

            if (t >= nextAt) {
                if (idx < deallyCallScript.length) {
                    var item = deallyCallScript[idx];
                    nextAt = t + item.delay;
                    appendReason(item);
                    idx++;
                    deallySuggest();
                }
            }

            requestAnimationFrame(pump);
        });
    }

    pump();
    tick();
}

var deallySpeakerMeta = {
    customer: { label: 'Customer' },
    agent: { label: 'Agent' },
    ai_detect: { label: 'AI · Detected Moments' },
    ai_ask: { label: 'AI · Generated Question' },
    agent_query: { label: 'You · Query' },
    ai_response: { label: 'AI · Response' },
};

function reasonLabel(cls) {
    return deallySpeakerMeta[cls] || { label: cls };
}

function classFor(cls) {
    if (cls === 'customer') return 'customer';
    if (cls === 'agent') return 'ai-detect';
    return cls;
}

function appendReason(item) {
    var stream = document.getElementById('stream');
    var isNewNow = stream && stream.classList.contains('express');

    if (item.scenario) {
        var badge = document.getElementById('scenario-badge');
        if (badge) badge.textContent = item.scenario;
    }

    var minutes = Math.floor(dateNow() / 60000);
    var seconds = Math.floor((dateNow() % 60000) / 1000);
    var stamp = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

    var el = document.createElement('div');
    el.className = 'reason-item ' + classFor(item.type);
    el.setAttribute('data-id', item.id || '');

    var metaLabel = item.type === 'agent_query'
        ? 'You'
        : item.type === 'ai_response'
            ? 'AI'
            : (deallySpeakerMeta[item.type] ? deallySpeakerMeta[item.type].label : 'Agent');

    el.innerHTML =
        '<div class="reason-header">' +
        '<div class="reason-meta">' +
        (item.type === 'ai_detect' || item.type === 'ai_ask' || item.type === 'ai_response'
            ? '<span class="meta-icon">✦</span>' : '') +
        '<span>' + metaLabel + '</span>' +
        '</div>' +
        '<button class="flag-btn" title="Flag" data-flag>' + (item.flagged ? '🚩' : '🏳️') + '</button>' +
        '</div>' +
        '<div class="reason-text">' + item.text + '</div>' +
        (isNewNow && item.timestamp ? '<div class="reason-time font-mono">' + item.timestamp + '</div>' : '');

    stream.appendChild(el);
    stream.scrollTop = stream.scrollHeight;

    if (isNewNow) {
        el.setAttribute('data-timestamp', item.timestamp || '');
    }
}

function dateNow() {
    return window._deallyStart || Date.now();
}

/* ---------- Real-time assistant (mic + OpenAI) ---------- */

function initLiveAssistant() {
    var toggle = document.getElementById('live-mic-toggle');
    var stream = document.getElementById('stream');
    var pool = document.getElementById('suggestions');
    var count = document.getElementById('sugg-count');
    var livePill = document.querySelector('.live-pill');
    if (!toggle || !stream || !pool) return;

    var transcribeUrl = toggle.getAttribute('data-transcribe-url');
    var queryUrl = toggle.getAttribute('data-query-url');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) csrf = csrf.getAttribute('content');

    var mediaRecorder = null;
    var audioStream = null;
    var chunks = [];
    var flushTimer = null;
    var listening = false;

    function appendStream(type, text, metaLabel) {
        var el = document.createElement('div');
        el.className = 'reason-item ' + (type === 'customer' ? 'customer' : 'ai-response');
        var meta = '';
        if (metaLabel) {
            meta = '<div class="reason-meta"><span>' + (type === 'ai-response' ? '<span class="meta-icon">✦</span>' : '') + metaLabel + '</span></div>';
        }
        el.innerHTML = '<div class="reason-header">' + meta + '</div>' + '<div class="reason-text">' + text + '</div>';
        stream.appendChild(el);
        stream.scrollTop = stream.scrollHeight;
    }

    function appendSuggestion(text) {
        var el = document.createElement('div');
        el.className = 'suggestion confident';
        el.setAttribute('data-type', 'confident');
        el.innerHTML =
            '<div class="suggestion-tag">AI SUGGESTION<span class="suggestion-confidence">AI · LIVE</span></div>' +
            '<div class="suggestion-text">' + text + '</div>' +
            '<div class="suggestion-source">Source: OpenAI · live transcript</div>';
        pool.appendChild(el);
        pool.scrollTop = pool.scrollHeight;
        if (count) {
            var n = parseInt(count.textContent || '0', 10);
            count.textContent = String(n + 1).padStart(2, '0');
        }
    }

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function flushChunk() {
        if (!chunks.length) return;

        var blob = new Blob(chunks, { type: mediaRecorder ? mediaRecorder.mimeType : 'audio/webm' });
        chunks = [];
        if (livePill) livePill.classList.add('busy');

        var fd = new FormData();
        fd.append('audio', blob, 'segment.webm');

        fetch(transcribeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            body: fd,
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.ok) return;
                appendStream('customer', esc(json.transcript), 'Customer');
                (json.suggestions || []).forEach(function (s) { appendSuggestion(esc(s)); });
            })
            .catch(function () {})
            .then(function () {
                if (livePill) livePill.classList.remove('busy');
            });
    }

    function toggleListening() {
        if (listening) {
            listening = false;
            clearInterval(flushTimer);
            flushTimer = null;
            flushChunk();
            if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
            if (audioStream) audioStream.getTracks().forEach(function (t) { t.stop(); });
            toggle.textContent = '🎤 Start Listening';
            toggle.classList.remove('recording');
            if (livePill) livePill.textContent = 'Paused';
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            window.deallyToast && window.deallyToast('Microphone not supported in this browser.', '🚫');
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function (stream) {
                audioStream = stream;
                mediaRecorder = new MediaRecorder(stream);
                chunks = [];
                mediaRecorder.ondataavailable = function (e) {
                    if (e.data && e.data.size) chunks.push(e.data);
                };
                mediaRecorder.start(2000);

                listening = true;
                toggle.textContent = '⏹ Stop Listening';
                toggle.classList.add('recording');
                if (livePill) {
                    livePill.innerHTML = '<span class="live-dot"></span>Listening · Live AI';
                }
                flushTimer = setInterval(flushChunk, 6000);
            })
            .catch(function () {
                window.deallyToast && window.deallyToast('Microphone access denied — allow it to get live AI suggestions.', '🚫');
            });
    }

    toggle.addEventListener('click', toggleListening);

    var queryBox = document.querySelector('.query-box');
    if (queryBox) {
        var input = queryBox.querySelector('input');
        var send = queryBox.querySelector('.query-send');
        if (input && send) {
            function submitQuery() {
                var text = (input.value || '').trim();
                if (!text) return;
                input.value = '';
                appendStream('agent_query', esc(text), 'You · Query');

                fetch(queryUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ text: text }),
                })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        if (json.ok) appendStream('ai-response', esc(json.answer), 'AI · Response');
                    })
                    .catch(function () {});
            }

            send.addEventListener('click', submitQuery);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') submitQuery();
            });
        }
    }
}

/* ---------- Suggestions ---------- */

var deallySuggestionPools = {
    confident: [
        'Customer would like a discount of at least 15%. The <b>Confident</b> response is to anchor first — restate value and promise a <b>formal proposal</b> within 2 days.',
        'Customer confirmed budget in the $8–12k range. Confidently commit to a <b>tailored implementation plan</b> to move the deal forward.',
        'Customer leans on competitor <b>Comparison Grid</b>. Position our <b>tool migration speed</b> and <b>2-week pilot</b> as the safer choice.',
    ],
    steering: [
        'Recommend steering the conversation. The buyer keeps asking about <b>price</b> — pivot to <b>business outcomes</b>: time saved, fewer missed follow-ups.',
        'Buyer signal: they said \u201cwe can decide this quickly.\u201d <b>Steer</b> toward a clear negotiation close: offer a package with a longer contract.',
    ],
    competitor: [
        'Competitor mention detected — they referenced <b>DealMate</b>. Compare our <b>native AI interventions</b> vs their manual coach notes.',
    ],
    expert: [
        'Expert mode: this buyer is a <b>product manager</b>. Keep suggestions to <b>requirements language</b>, not budget talk.',
    ],
    data: [
        'Heads up — the buyer mentioned <b>data isolation</b> concerns. Whip this up: your deployment supports <b>single-tenant isolation</b>.',
    ],
};

function deallySuggest() {
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
                    ? 'Source: live transcript · competitor detection'
                    : type === 'data'
                        ? 'Source: capability card'
                        : 'Source: playbook';
                el.innerHTML =
                    '<div class="suggestion-tag">' + type.toUpperCase() + '<span class="suggestion-confidence">AI · LIVE</span></div>' +
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

/* ---------- Timer ---------- */

var _timerStart = null;

function initTimer() {
    var el = document.getElementById('call-timer');
    if (!el) return;

    var startedAt = Number(el.getAttribute('data-start') || 0);
    _timerStart = startedAt ? startedAt * 1000 : Date.now();
    window._deallyStart = _timerStart;

    setInterval(tick, 1000);
}

function tick() {
    var el = document.getElementById('call-timer');
    if (!el) return;

    if (!window._deallyStart) window._deallyStart = Date.now();
    var m = Math.floor((Date.now() - window._deallyStart) / 60000);
    var s = Math.floor((Date.now() - window._deallyStart) % 60000 / 1000);
    el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

/* ---------- Toasts ---------- */

function initToasts() {
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

/* ---------- Knowledge base tabs ---------- */

function initKbTabs() {
    var tabs = document.querySelectorAll('[data-kb-tab]');
    if (!tabs.length) return;

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var target = document.getElementById('kb-tab-' + tab.getAttribute('data-kb-tab'));
            document.querySelectorAll('#kb-tab-entries, #kb-tab-gaps').forEach(function (el) {
                el.style.display = 'none';
            });
            if (target) target.style.display = 'block';
        });
    });
}

/* ---------- Call worker mode (express/race) ---------- */

function initWorkerMode() {
    var race = document.querySelector('.call-racing');
    if (race) {
        document.body.classList.add('call-racing');
    }
}

/* ---------- Forms ---------- */

function initForms() {
    // Quick add forms (task, kb, proposal) submit normally.

    // "End call" buttons that confirm before navigating
    document.querySelectorAll('[data-end-call]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var href = btn.getAttribute('href') || btn.getAttribute('data-end-call');
            if (window.confirm('End this call and generate the post-call summary?')) {
                window.location.href = href;
            }
        });
    });

    // Add-to-tasks quick button on the summary page
    var addTask = document.querySelector('[data-add-task-async]');
    if (addTask) {
        addTask.addEventListener('click', function (e) {
            e.preventDefault();
            var btn = addTask;
            if (btn.classList.contains('done')) return;

            fetch(addTask.getAttribute('href'), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (json.ok) {
                        btn.classList.add('done');
                        btn.innerHTML = '✓ Added to Tasks';
                        window.deallyToast && window.deallyToast('Task added from the call.');
                    }
                })
                .catch(function () {
                    window.location.href = addTask.getAttribute('href');
                });
        });
    }

    // Call flag buttons
    document.addEventListener('click', function (e) {
        var flag = e.target.closest('[data-flag]');
        if (!flag) return;
        var item = flag.closest('.reason-item');
        flag.classList.toggle('flagged');
        flag.textContent = flag.classList.contains('flagged') ? '🚩' : '🏳️';
        if (flag.classList.contains('flagged')) {
            item.classList.add('flagged');
            window.deallyToast && window.deallyToast('Line flagged.');
        }
    });

    // Suggestion chips on live call
    document.addEventListener('click', function (e) {
        var chip = e.target.closest('.chip[data-action]');
        if (!chip || chip.classList.contains('clicked') || chip.classList.contains('waiting')) return;

        var action = chip.getAttribute('data-action');
        var promptText = chip.getAttribute('data-prompt');

        chip.classList.add('clicked');
        if (action === 'ask') {
            pushQuery(promptText, true);
        } else if (action === 'add-task') {
            chip.classList.remove('clicked');
            chip.classList.add('waiting');
            setTimeout(function () {
                chip.classList.remove('waiting');
                chip.textContent = '✓ Task added';
                window.deallyToast && window.deallyToast('Follow-up task created.');
            }, 1200);
        }
    });

    // Query box on live call
    var queryBox = document.querySelector('.query-box');
    if (queryBox) {
        var input = queryBox.querySelector('input');
        var send = queryBox.querySelector('.query-send');

        function submitQuery() {
            var text = (input.value || '').trim();
            if (!text) return;
            pushQuery(text, false);
            input.value = '';
        }

        send.addEventListener('click', submitQuery);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') submitQuery();
        });
    }
}

function pushQuery(text, isSuggestion) {
    var stream = document.getElementById('stream');
    if (!stream) return;

    var minutes = Math.floor((Date.now() - (window._deallyStart || Date.now())) / 60000);
    var seconds = Math.floor((Date.now() - (window._deallyStart || Date.now())) % 60000 / 1000);

    var q = document.createElement('div');
    q.className = 'reason-item agent-query';
    q.innerHTML =
        '<div class="reason-header">' +
        '<div class="reason-meta"><span>You · Query</span></div>' +
        '</div>' +
        '<div class="reason-text">' + text + '</div>';
    stream.appendChild(q);

    // Append an AI response after the synthesized delay.
    var r = document.createElement('div');
    r.className = 'reason-item ai-response';
    r.style.display = 'none';
    r.innerHTML =
        '<div class="reason-header">' +
        '<div class="reason-meta"><span class="meta-icon">✦</span><span>AI · Response</span></div>' +
        '</div>' +
        '<div class="reason-text">' + genericResponse(text) + '</div>';
    stream.appendChild(r);

    stream.scrollTop = stream.scrollHeight;

    setTimeout(function () {
        r.style.display = 'block';
        r.classList.add('exiting'); /* visual cue only */
        setTimeout(function () {
            r.classList.remove('exiting');
        }, 100);
        stream.scrollTop = stream.scrollHeight;
    }, isSuggestion ? 300 : 900);
}

function genericResponse(query) {
    var lower = query.toLowerCase();
    if (lower.indexOf('price') !== -1 || lower.indexOf('cost') !== -1 || lower.indexOf('discount') !== -1) {
        return 'Our flexible tier starts at <b>$499/month</b>. If budget is the concern, we can explore a <b>6-month pilot</b> at a reduced rate.';
    }
    if (lower.indexOf('competitor') !== -1 || lower.indexOf('dealmate') !== -1) {
        return 'Fair question. The main differences: we catch <b>objections in real time</b> with inline interventions, and the post-call summary auto-updates your CRM.';
    }
    if (lower.indexOf('timeline') !== -1 || lower.indexOf('when') !== -1) {
        return 'Fastest path: kick off the <b>2-week pilot</b> this Friday, roll out to 10 seats, then full team by month-end.';
    }
    return 'Sent the buyer a tailored recap tying our value to their two stated priorities: <b>security reviews</b> and <b>reduced admin time</b>.';
}