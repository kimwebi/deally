/* ---------- Live call simulation ---------- */

import { tick, dateNow } from './timer.js';
import { deallySuggest } from './suggestions.js';

var deallyCallScript = null;

export function initLiveCall() {
    var stream = document.getElementById('stream');
    if (!stream) return;

    if (document.getElementById('live-mic-toggle')) {
        initLiveAssistant();
        return;
    }

    var scriptEl = document.getElementById('live-script');
    if (!scriptEl) return;

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
    ai_detect: { label: 'AI Â· Detected Moments' },
    ai_ask: { label: 'AI Â· Generated Question' },
    agent_query: { label: 'You Â· Query' },
    ai_response: { label: 'AI Â· Response' },
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
            ? '<span class="meta-icon">âœ¦</span>' : '') +
        '<span>' + metaLabel + '</span>' +
        '</div>' +
        '<button class="flag-btn" title="Flag" data-flag>' + (item.flagged ? 'ðŸš©' : 'ðŸ³ï¸') + '</button>' +
        '</div>' +
        '<div class="reason-text">' + item.text + '</div>' +
        (isNewNow && item.timestamp ? '<div class="reason-time font-mono">' + item.timestamp + '</div>' : '');

    stream.appendChild(el);
    stream.scrollTop = stream.scrollHeight;

    if (isNewNow) {
        el.setAttribute('data-timestamp', item.timestamp || '');
    }
}

/* ---------- Real-time assistant (two-layer, dummy driver) ---------- */

export function initLiveAssistant() {
    var toggle = document.getElementById('live-mic-toggle');
    var stream = document.getElementById('stream');
    var idle = document.getElementById('stream-idle');
    var findings = document.getElementById('findings');
    var findingsCount = document.getElementById('findings-count');
    var dotStrip = document.getElementById('dot-strip');
    var livePill = document.querySelector('.live-pill');
    var shell = document.querySelector('.lc-shell');
    var hero = document.getElementById('hero');
    if (!toggle || !stream || !findings) return;

    var transcribeUrl = toggle.getAttribute('data-transcribe-url');
    var queryUrl = toggle.getAttribute('data-query-url');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) csrf = csrf.getAttribute('content');

    var mediaRecorder = null;
    var audioStream = null;
    var chunks = [];
    var flushTimer = null;
    var listening = false;

    var heardCount = 0;
    var heroVisible = false;
    var objMode = false;

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function refreshIdle() {
        if (!idle) return;
        idle.style.display = stream.querySelectorAll('.ephem-card').length ? 'none' : '';
    }

    /* ---- ephemeral layer: what the agent sees moment-to-moment ---- */
    function appendEphem(kind, label, text, icon) {
        var icons = { heard: 'ðŸ‘‚', detected: 'âœ¦', gap: 'âš ï¸', asked: 'ðŸ’¬', objection: 'ðŸš©' };
        var el = document.createElement('div');
        el.className = 'ephem-card';
        el.innerHTML =
            '<div class="ephem-icon ' + kind + '">' + (icon || icons[kind] || 'âœ¦') + '</div>' +
            '<div class="ephem-body">' +
            '<div class="ephem-label">' + label + '</div>' +
            '<div class="ephem-text">' + text + '</div>' +
            '</div>';
        stream.appendChild(el);
        refreshIdle();

        while (stream.querySelectorAll('.ephem-card').length > 3) {
            stream.removeChild(stream.querySelectorAll('.ephem-card')[0]);
        }
        stream.scrollTop = stream.scrollHeight;

        setTimeout(function () {
            el.classList.add('exit');
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
                refreshIdle();
            }, 520);
        }, 6000);
    }

    function summarise(text) {
        var t = String(text || '').replace(/\s+/g, ' ').trim();
        if (t.length <= 90) return t;
        return t.slice(0, 90).replace(/\s+\S*$/, '') + 'â€¦';
    }

    /* ---- findings layer: sticky, scrollable, newest at top ---- */
    var roleMeta = {
        say: { tag: 'âœ“ Say this', accent: 'say' },
        ask: { tag: '? Ask this', accent: 'ask' },
        reference: { tag: 'â†— Reference', accent: 'reference' },
        objection: { tag: 'ðŸš© Objection', accent: 'objection' },
        waiting: { tag: 'â³ Waiting', accent: 'waiting' },
        person: { tag: 'ðŸ‘¤ From a person', accent: 'person' },
    };

    function appendFindingsCard(card, noPulse) {
        var role = card.role || 'say';
        var meta = roleMeta[role] || roleMeta.say;
        var label = card.label ? esc(card.label) : meta.tag;
        var conf = card.confidence ? '<span class="kb-conf">' + esc(card.confidence) + '</span>' : '';

        var el = document.createElement('div');
        el.className = 'kb-card ' + meta.accent;
        el.innerHTML =
            '<span class="kb-accent"></span>' +
            '<div class="kb-top"><span class="kb-tag">' + label + '</span>' + conf + '</div>' +
            '<div class="kb-body">' + esc(card.body || '') + '</div>' +
            '<div class="kb-foot">' +
            (card.package ? '<div class="kb-foot-primary">' + esc(card.package) + '</div>' : '') +
            (card.source ? '<div class="kb-foot-source">' + esc(card.source) + '</div>' : '') +
            '</div>';

        findings.insertBefore(el, findings.firstChild);
        el.scrollIntoView({ block: 'nearest' });
        addDot(el, meta.accent);
        updateFindings();

        if (!noPulse) {
            setTimeout(function () { el.classList.add('pulse'); }, 80);
        }
    }

    function updateFindings() {
        if (!findingsCount) return;
        var n = findings.querySelectorAll('.kb-card').length;
        findingsCount.textContent = String(n).padStart(2, '0');
    }

    function addDot(el, accent) {
        if (!dotStrip) return;
        var dot = document.createElement('button');
        dot.className = 'findings-dot ' + accent;
        dot.title = 'View this card';
        dot.innerHTML = '<span class="dot-thumbs"><span class="dot-thumbs-btn" data-unhelpful="1">ðŸ‘Ž Unhelpful</span></span>';
        dot.addEventListener('click', function () {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('pulse');
        });
        dot.querySelector('[data-unhelpful]').addEventListener('click', function (e) {
            e.stopPropagation();
            window.deallyToast && window.deallyToast('Marked unhelpful â€” fed to the corrections queue.', 'ðŸ‘Ž');
        });
        dotStrip.appendChild(dot);
    }

    /* ---- hero (AI Asks You) ---- */
    function showHero(opts) {
        if (!hero) return;
        heroVisible = true;
        var eyebrow = document.getElementById('hero-eyebrow');
        var q = document.getElementById('hero-question');
        var chips = document.getElementById('hero-chips');
        var expert = document.getElementById('hero-expert');
        var context = document.getElementById('hero-context');
        if (eyebrow) eyebrow.classList.toggle('expert', !!opts.expert);
        if (eyebrow) eyebrow.querySelector('.hero-eyebrow-label').textContent = opts.eyebrow || (opts.expert ? 'Expert Request' : 'AI Asks You');
        if (q) q.textContent = opts.question || '';
        if (chips) {
            chips.innerHTML = '';
            (opts.chips || []).forEach(function (chip) {
                var b = document.createElement('button');
                b.className = 'hero-chip';
                b.textContent = chip.label;
                b.addEventListener('click', function () {
                    b.classList.add('waiting');
                    submitQuery(chip.query, true);
                });
                chips.appendChild(b);
            });
        }
        if (expert) expert.hidden = !opts.expert;
        if (context) context.textContent = opts.context || '';
        hero.hidden = false;
        if (shell) shell.classList.add('hero-live');
    }

    function hideHero() {
        if (!hero) return;
        heroVisible = false;
        hero.hidden = true;
        if (shell) shell.classList.remove('hero-live');
    }

    function triggerDecideHero() {
        showHero({
            eyebrow: 'AI Asks You',
            question: 'The buyer has signalled needs across pricing, compliance and switching. Which area should I prioritise on the Findings panel?',
            chips: [
                { label: 'Pricing tier', query: 'What pricing tier fits a 500-user company? Give me the confident reply.' },
                { label: 'Compliance', query: 'How do we handle HIPAA compliance in a call?' },
                { label: 'Switching', query: 'What do we say about migration speed vs competitors?' },
            ],
            context: 'Any choice pulls matching guidance onto the Findings Panel.',
        });
    }

    function requestExpert() {
        var expert = document.getElementById('hero-expert');
        if (!expert) return;
        expert.classList.add('waiting');
        expert.textContent = 'â³ Requesting expertâ€¦';
        appendEphem('detected', 'Expert Ping', 'Requesting instant expert help via the Solutions Lead.', 'ðŸ””');

        setTimeout(function () {
            appendFindingsCard({
                role: 'person',
                label: 'From a person',
                confidence: 'Solutions Lead',
                body: 'Let us run a quick HIPAA compliance check and confirm before you commit â€” expect a written answer within the hour.',
                package: 'Expert reply Â· Solutions Lead',
                source: 'WhatsApp Â· live reply',
            }, true);
            expert.classList.remove('waiting');
            expert.textContent = 'ðŸ”” Request Instant Expert Help';
            hideHero();
        }, 5000);
    }

    /* ---- queries ---- */
    function queryFetch(payload) {
        return fetch(queryUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(payload),
        })
            .then(function (res) { return res.json(); })
            .catch(function () { return { ok: false }; });
    }

    function replyCards(json) {
        var cards = json.cards || [];
        if (!cards.length && json.answer) {
            cards = [{ role: 'say', confidence: 'AI Â· live', body: json.answer, package: 'Suggested reply', source: 'AI Â· live transcript' }];
        }
        return cards;
    }

    function submitQuery(text, hideHeroAfter) {
        text = (text || '').trim();
        if (!text) return;
        appendEphem('asked', 'You asked', esc(summarise(text)));
        queryFetch({ text: text }).then(function (json) {
            if (!json.ok) return;
            replyCards(json).forEach(function (c) { appendFindingsCard(c); });
            if (hideHeroAfter) hideHero();
        });
    }

    function submitObjection() {
        var objInput = document.getElementById('obj-input');
        var text = (objInput && objInput.value || '').trim();
        appendEphem('objection', 'Objection', text
            ? 'Objection raised: ' + esc(summarise(text))
            : 'Objection raised â€” logged for the Solutions Lead.');

        queryFetch({ text: text, objection: 1 }).then(function (json) {
            if (!json.ok) return;
            if (json.answer && !(json.cards || []).length) {
                appendFindingsCard({
                    role: 'objection',
                    label: 'Objection',
                    confidence: 'AI Â· live',
                    body: json.answer,
                    package: 'Logged for review',
                    source: 'AI Â· live transcript',
                });
            }
            (json.cards || []).forEach(function (c) { appendFindingsCard(c); });
            if (objInput) objInput.value = '';
            objBtn.classList.remove('open');
            objMode = false;
        });
    }

    /* ---- transcription flush ---- */
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
                appendEphem('heard', 'Heard', esc(summarise(json.transcript)));
                heardCount++;
                var cards = json.suggestions || [];
                if (cards.length) {
                    appendEphem('detected', 'Detected', 'Checked the knowledge base for live guidance.', 'âœ¦');
                    cards.forEach(function (c) { appendFindingsCard(c); });
                }
                if (heardCount === 2 && !heroVisible) triggerDecideHero();
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
            toggle.innerHTML = '<i class="bi bi-mic-fill"></i> Start';
            toggle.classList.remove('recording');
            if (livePill) livePill.innerHTML = '<span class="live-dot"></span>Paused';
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            window.deallyToast && window.deallyToast('Microphone not supported in this browser.', 'ðŸš«');
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
                toggle.innerHTML = '<i class="bi bi-stop-circle-fill"></i> Stop';
                toggle.classList.add('recording');
                if (livePill) livePill.innerHTML = '<span class="live-dot"></span>Listening Â· Live AI';
                flushTimer = setInterval(flushChunk, 6000);
            })
            .catch(function () {
                window.deallyToast && window.deallyToast('Microphone access denied â€” allow it to get live AI suggestions.', 'ðŸš«');
            });
    }

    toggle.addEventListener('click', toggleListening);

    /* ---- chat dock ---- */
    var queryInput = document.getElementById('query-input');
    var querySend = document.getElementById('query-send');
    var objBtn = document.getElementById('obj-btn');

    if (queryInput && querySend) {
        var chatSubmit = function () {
            var text = queryInput.value;
            if (!text.trim()) return;
            queryInput.value = '';
            submitQuery(text, false);
        };
        querySend.addEventListener('click', chatSubmit);
        queryInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') chatSubmit();
        });
    }

    if (objBtn) {
        var objInput = document.getElementById('obj-input');
        objBtn.addEventListener('click', function () {
            if (!objInput) return;
            objMode = !objMode;
            objInput.hidden = !objMode;
            objBtn.classList.toggle('open', objMode);
            if (objMode) objInput.focus();
        });
        if (objInput) {
            objInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') submitObjection();
            });
        }
    }

    var expertBtn = document.getElementById('hero-expert');
    if (expertBtn) expertBtn.addEventListener('click', requestExpert);

    /* ---- end call ---- */
    function endCall() {
        var durationEl = document.getElementById('end-duration');
        var notesEl = document.getElementById('end-notes');
        var notesInput = document.getElementById('notes-input');
        var timerEl = document.getElementById('call-timer');
        if (notesEl && notesInput) notesEl.value = notesInput.value;
        if (durationEl && timerEl) durationEl.value = timerEl.textContent;
        if (window.confirm('End this call and generate the post-call summary?')) {
            var form = document.getElementById('end-call-form');
            if (form) form.submit();
        }
    }

    var endForm = document.getElementById('end-call-form');
    if (endForm) {
        endForm.addEventListener('submit', function (e) {
            e.preventDefault();
            endCall();
        });
    }

    var notesInput = document.getElementById('notes-input');

    /* ---- keyboard shortcuts ---- */
    var shortcutsOverlay = document.getElementById('shortcuts-overlay');
    var shortcutsClose = document.getElementById('shortcuts-close');

    if (shortcutsClose) {
        shortcutsClose.addEventListener('click', function () {
            if (shortcutsOverlay) shortcutsOverlay.hidden = true;
        });
    }

    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            endCall();
            return;
        }
        if (e.key === '?') {
            if (shortcutsOverlay) shortcutsOverlay.hidden = !shortcutsOverlay.hidden;
            return;
        }
        if (e.key === 'Escape') {
            if (shortcutsOverlay) shortcutsOverlay.hidden = true;
            if (objBtn && objMode) {
                var oi = document.getElementById('obj-input');
                if (oi) oi.hidden = true;
                objBtn.classList.remove('open');
                objMode = false;
            }
            return;
        }
        var activeTag = e.target && e.target.tagName;
        if ((e.key === 'n' || e.key === 'N') && activeTag !== 'INPUT' && activeTag !== 'TEXTAREA') {
            if (notesInput) notesInput.focus();
            return;
        }
        if (e.key === '/' && !e.metaKey && !e.ctrlKey && !e.altKey && activeTag !== 'INPUT' && activeTag !== 'TEXTAREA') {
            e.preventDefault();
            if (queryInput) queryInput.focus();
            return;
        }
        if ((e.key === 'e' || e.key === 'E') && activeTag !== 'INPUT' && activeTag !== 'TEXTAREA' && heroVisible) {
            requestExpert();
        }
    });
}
