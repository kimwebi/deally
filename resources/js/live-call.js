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
        '<button class="flag-btn" title="Flag" data-flag>' + (item.flagged ? '🚩' : '�️') + '</button>' +
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
    var heroContinuation = null;

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function refreshIdle() {
        if (!idle) return;
        idle.style.display = stream.querySelectorAll('.ephemeral-card').length ? 'none' : '';
    }

    /* ---- ephemeral layer: what the agent sees moment-to-moment ---- */
    function appendEphem(kind, label, text, icon) {
        if (heroVisible) return;
        var icons = { heard: '👂', detected: '✦', gap: '⚠️', asked: '💬', objection: '🚩' };
        var el = document.createElement('div');
        el.className = 'ephemeral-card ' + kind;
        el.innerHTML =
            '<div class="ephem-icon ' + kind + '">' + (icon || icons[kind] || '✦') + '</div>' +
            '<div class="ephem-body">' +
            '<div class="ephem-label">' + label + '</div>' +
            '<div class="ephem-text">' + text + '</div>' +
            '</div>';
        stream.appendChild(el);
        refreshIdle();

        while (stream.querySelectorAll('.ephemeral-card').length > 3) {
            stream.removeChild(stream.querySelectorAll('.ephemeral-card')[0]);
        }
        stream.scrollTop = stream.scrollHeight;

        setTimeout(function () {
            el.classList.add('fading');
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
                refreshIdle();
            }, 500);
        }, 6500);
    }

    function summarise(text) {
        var t = String(text || '').replace(/\s+/g, ' ').trim();
        if (t.length <= 90) return t;
        return t.slice(0, 90).replace(/\s+\S*$/, '') + '…';
    }

    /* ---- findings layer: sticky, scrollable, newest at top ---- */
    var roleMeta = {
        say: { tag: '✓ Say this', accent: 'say' },
        ask: { tag: '? Ask this', accent: 'ask' },
        reference: { tag: '↗  Reference', accent: 'reference' },
        objection: { tag: '🚩 Objection', accent: 'objection' },
        waiting: { tag: '⏳ Waiting', accent: 'waiting' },
        person: { tag: '👤 From a person', accent: 'person' },
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
        var kbEmpty = document.getElementById('kb-empty');
        if (kbEmpty) kbEmpty.hidden = true;
        findings.scrollTop = 0;
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
        dot.innerHTML = '<span class="dot-thumbs"><span class="dot-thumbs-btn" data-unhelpful="1">👎 Unhelpful</span></span>';
        dot.addEventListener('click', function () {
            var shelf = document.getElementById('findings');
            if (shelf) {
                shelf.scrollTop = Math.max(0, el.offsetTop - shelf.clientHeight / 2 + el.offsetHeight / 2);
            }
            el.classList.add('pulse');
        });
        dot.querySelector('[data-unhelpful]').addEventListener('click', function (e) {
            e.stopPropagation();
            window.deallyToast && window.deallyToast('Marked unhelpful — fed to the corrections queue.', '👎');
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
                    if (chip.card) {
                        setTimeout(function () {
                            appendFindingsCard(chip.card, true);
                            hideHero();
                        }, 700);
                    } else {
                        submitQuery(chip.query, true);
                    }
                });
                chips.appendChild(b);
            });
        }
        if (expert) expert.hidden = !opts.expert;
        if (context) context.textContent = opts.context || '';
        hero.classList.add('active');
        if (shell) shell.classList.add('hero-live');
    }

    function hideHero() {
        if (!hero) return;
        heroVisible = false;
        hero.classList.remove('active');
        if (shell) shell.classList.remove('hero-live');
        if (heroContinuation) {
            var resume = heroContinuation;
            heroContinuation = null;
            resume();
        }
    }

    function thenAfterHero(ms, fn) {
        heroContinuation = function () {
            afterScenario(ms, fn);
        };
    }

    function triggerDecideHero() {
        showHero({
            eyebrow: 'AI Asks You',
            question: 'Which pricing tier applies to this deal?',
            chips: [
                {
                    label: 'Enterprise',
                    card: {
                        role: 'say',
                        label: '✓ Say this · Enterprise Pricing',
                        confidence: '98%',
                        body: 'Enterprise: $15/user/mo. 500 users = $7,500/mo. Volume discounts above 1,000 seats.',
                        package: 'Enterprise Suite · Enterprise Tier · 500 users',
                        source: 'KB · Pricing · Enterprise',
                    },
                },
                {
                    label: 'Pro',
                    card: {
                        role: 'say',
                        label: '✓ Say this · Pro Pricing',
                        confidence: '98%',
                        body: 'Pro Plan: $9/user/mo. Up to 200 users. Standard support.',
                        package: 'Pro Plan · up to 200 users',
                        source: 'KB · Pricing · Pro',
                    },
                },
                {
                    label: 'Starter',
                    card: {
                        role: 'say',
                        label: '✓ Say this · Starter Pricing',
                        confidence: '98%',
                        body: 'Starter: $5/user/mo. Up to 50 users. Email support only.',
                        package: 'Starter · up to 50 users',
                        source: 'KB · Pricing · Starter',
                    },
                },
            ],
            context: 'Customer asked about 500 users. Selecting a tier will pull the correct pricing from the KB.',
        });
    }

    function requestExpert() {
        var expert = document.getElementById('hero-expert');
        if (!expert) return;
        expert.classList.add('waiting');
        expert.textContent = '⏳ Waiting for expert…';

        setTimeout(function () {
            appendFindingsCard({
                role: 'person',
                label: 'Expert Reply · Harvey Specter',
                body: 'HIPAA is supported on Enterprise tier. BAA signing available. Compliance doc package to follow.',
                package: 'Enterprise Suite · Enterprise Tier',
                source: 'From Harvey Specter via WhatsApp',
            }, true);
            expert.classList.remove('waiting');
            expert.textContent = '🔔 Request Instant Expert Help';
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
            cards = [{ role: 'say', confidence: 'AI · live', body: json.answer, package: 'Suggested reply', source: 'AI · live transcript' }];
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
            : 'Objection raised — logged for the Solutions Lead.');

        queryFetch({ text: text, objection: 1 }).then(function (json) {
            if (!json.ok) return;
            if (json.answer && !(json.cards || []).length) {
                appendFindingsCard({
                    role: 'objection',
                    label: 'Objection',
                    confidence: 'AI · live',
                    body: json.answer,
                    package: 'Logged for review',
                    source: 'AI · live transcript',
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
                    appendEphem('detected', 'Detected', 'Checked the knowledge base for live guidance.', '<i class="bi bi-stars"></i>');
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
            toggle.innerHTML = '<i class="bi bi-mic-fill"></i> Mic';
            toggle.classList.remove('recording');
            if (livePill) livePill.innerHTML = '<span class="live-dot"></span>Paused';
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            window.deallyToast && window.deallyToast('Microphone not supported in this browser.', '🚫');
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function (stream) {
                stopScenario();
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
                if (livePill) livePill.innerHTML = '<span class="live-dot"></span>Transcribing · Live AI';
                flushTimer = setInterval(flushChunk, 6000);
            })
            .catch(function () {
                window.deallyToast && window.deallyToast('Microphone access denied —  allow it to get live AI suggestions.', '🚫');
            });
    }

    toggle.addEventListener('click', toggleListening);

    /* ---- transcription scenario (mirrors the prototype's live feed) ----
       Runs automatically so transcription works without a mic/key,
       while still sending real queries to the knowledge base. */
    var scenarioChained = null;
    var scenarioActive = false;

    function stopScenario() {
        if (scenarioChained) clearTimeout(scenarioChained);
        scenarioChained = null;
        scenarioActive = false;
    }

    function afterScenario(ms, fn) {
        scenarioChained = setTimeout(fn, ms);
    }

    function runTranscriptionScenario() {
        if (scenarioActive) return;
        scenarioActive = true;

        function heard(text) {
            appendEphem('heard', 'Heard', '<strong>Customer:</strong> ' + text);
        }

        function detected(text) {
            appendEphem('detected', 'Detected', text, '<i class="bi bi-stars"></i>');
        }

        /* Demo narrative (matches the prototype):
             1. Heard → Detected → Cisco battle card lands (auto)
             2. Customer asks about tier → "AI Asks You" hero appears and
                WAITS for a choice — it is ALONE, never stacked with the
                expert card.
             3. After you choose: customer asks about HIPAA → "No KB match"
                gap → "Request Instant Expert Help" card appears right after.
             4. After the expert replies: the call continues (SSO, buying
                signal). */
        var t = 0;

        function step(ms, fn) {
            t += ms;
            afterScenario(t, fn);
        }

        step(2200, function () {
            heard('using Cisco Meraki, support is slow');
        });
        step(1400, function () {
            detected('<strong>Competitor:</strong> Cisco');
        });
        step(1200, function () {
            appendFindingsCard({
                role: 'say',
                label: '✓ Say this · Cisco Battle Card',
                confidence: '94%',
                body: 'Highlight 99.9% uptime, 24/7 dedicated support, and native Slack integration.',
                package: 'Enterprise Suite · Enterprise Tier',
                source: 'KB · Competitor · Cisco',
            });
        });

        step(4500, function () {
            heard('asked about pricing for 500 users');
        });
        step(1400, function () {
            detected('<strong>Pricing question</strong> — need tier');
        });
        step(1200, function () {
            triggerDecideHero();
        });

        /* Step 3: HIPAA → gap → Request Instant Expert Help (only after the
           tier choice is made, so the two heroes never show together). */
        thenAfterHero(1000, function () {
            var t2 = 0;

            function step2(ms, fn) {
                t2 += ms;
                afterScenario(t2, fn);
            }

            step2(1000, function () {
                heard('asked about HIPAA compliance');
            });
            step2(1400, function () {
                appendEphem('gap', 'No KB match', 'HIPAA — not documented');
            });
            step2(1000, function () {
                showHero({
                    eyebrow: 'Expert Help Needed',
                    expert: true,
                    question: 'HIPAA isn\'t in the Knowledge Base. Request expert help?',
                    context: 'The AI searched but found no documented answer. A Solutions Lead can reply in seconds via WhatsApp.',
                });
            });

            /* Step 4: the call continues once the expert reply lands. */
            thenAfterHero(1000, function () {
                var t3 = 0;

                function step3(ms, fn) {
                    t3 += ms;
                    afterScenario(t3, fn);
                }

                step3(1000, function () {
                    heard('needs SSO with Okta');
                });
                step3(1200, function () {
                    appendFindingsCard({
                        role: 'say',
                        label: '✓ Say this · SSO',
                        confidence: '96%',
                        body: 'Enterprise tier includes SAML-based SSO with Okta, Azure AD, and Google Workspace.',
                        package: 'Enterprise Suite · Enterprise Tier',
                        source: 'KB · Features · SSO',
                    });
                });
                step3(7000, function () {
                    heard('"that sounds perfect, actually"');
                });
                step3(1200, function () {
                    appendFindingsCard({
                        role: 'ask',
                        label: '? Ask this · Buying Signal',
                        body: 'Ask: "What would need to be true for you to move forward this month?" — the intent is warm but no commitment was asked for.',
                        source: 'AI-generated · no KB match',
                    });
                });
            });
        });
    }

    runTranscriptionScenario();

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
