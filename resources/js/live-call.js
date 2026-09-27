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

/* ---------- Real-time assistant (dual-stream capture) ---------- */

export function initLiveAssistant() {
    var toggle = document.getElementById('live-mic-toggle');
    var stream = document.getElementById('stream');
    var idle = document.getElementById('stream-idle');
    var findings = document.getElementById('findings');
    var findingsCount = document.getElementById('findings-count');
    var dotStrip = document.getElementById('dot-strip');
    var shelfNote = document.getElementById('kb-shelf-note');

    /* The shelf renders the newest cards only, so a long call cannot flood the
       panel. The server sends the cap with the page; nothing is deleted. */
    var shelfLimit = parseInt(findings && findings.getAttribute('data-shelf-limit'), 10) || 10;
    var livePill = document.querySelector('.live-pill');
    var shell = document.querySelector('.lc-shell');
    var hero = document.getElementById('hero');
    if (!toggle || !stream || !findings) return;

    var transcribeUrl = toggle.getAttribute('data-transcribe-url');
    var startUrl = toggle.getAttribute('data-start-url');
    var queryUrl = toggle.getAttribute('data-query-url');
    var feedbackUrl = toggle.getAttribute('data-feedback-url');
    var demoMode = toggle.getAttribute('data-demo-mode') === 'true';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) csrf = csrf.getAttribute('content');

    /* Two sources are captured independently so a slow transcription request
       never pauses the recorder. The agent stream comes from the microphone and
       the customer stream from the shared meeting audio. */
    /* Two sources are captured independently so a slow transcription request
       never pauses the recorder. The agent stream comes from the microphone and
       the customer stream from the shared meeting audio.

       Each window is recorded by its own short-lived MediaRecorder that is
       started and then stopped (see recordWindow), which is what makes every
       upload a complete WebM file with its own header. A single long-running
       recorder sliced with requestData() instead produces headerless fragments
       after the first one, and those decode unreliably: the opening window
       transcribes and later ones come back empty. */
    var CHUNK_MS = 4000;
    var MAX_QUEUED_CHUNKS = 4;
    var SOURCE_LABELS = { agent: 'You', customer: 'Customer' };

    /* Whisper invents text ("Thank you.") for audio with no speech, so genuinely
       silent windows are never uploaded. The meter samples the live stream, so
       this costs nothing per window.

       The floor has to sit below real speech, not just above a quiet room: a
       customer on a compressed meeting stream, or a rep on a laptop mic, can sit
       under a high threshold, and a window dropped here is discarded for good —
       the question is simply never transcribed and nothing on the page says why.
       A live capture's noise floor is orders of magnitude under this, so the
       margin against hallucinated silence is still large. */
    var SILENCE_RMS = 0.0015;
    var LEVEL_SAMPLE_MS = 100;
    var SILENT_WINDOWS_BEFORE_WARNING = 3;

    /* The silence floor above now only guards the *diagnosis*: quiet windows are
       still uploaded, because a real question spoken quietly is the exact thing
       that used to vanish. */
    var ALERT_RMS = 0.02;

    /* A transcription request that never settles must not hold the source queue
       shut: the windows behind it pile up and get dropped as backlog, and the
       rep is left with a live-looking page that never transcribes again until
       they reload. */
    var UPLOAD_TIMEOUT_MS = 30000;

    var inflight = 0;
    var capturing = false;
    var starting = false;
    var watchdogTimer = null;

    var heroVisible = false;
    var objMode = false;
    var heroContinuation = null;

    var sources = {
        agent: createSource('agent'),
        customer: createSource('customer'),
    };

    function createSource(key) {
        return {
            key: key,
            stream: null,
            recorder: null,
            parts: [],
            timer: null,
            sequence: 0,
            queue: [],
            uploading: false,
            active: false,
            stopped: false,
            restarts: 0,
            gaveUp: false,
            dataAt: 0,
            recordStream: null,
            windowStartedAt: Date.now(),
            levelContext: null,
            levelAnalyser: null,
            levelSink: null,
            levelTimer: null,
            levelBuffer: null,
            energy: 0,
            samples: 0,
            levelRms: null,
            silentWindows: 0,
            warnedSilent: false,
            droppedWindows: 0,
            failures: 0,
            analysisFailures: 0,
        };
    }

    function setBusy(delta) {
        inflight = Math.max(0, inflight + delta);
        if (livePill) livePill.classList.toggle('busy', inflight > 0);
    }

    function setPill(label) {
        if (livePill) livePill.innerHTML = '<span class="live-dot"></span>' + label;
    }

    function setSourceStatus(key, label) {
        var el = document.getElementById('source-' + key + '-status');
        if (!el) return;

        el.textContent = label;
        // A decorated label ("● Live · 2 quiet windows skipped") is still live;
        // only a genuinely stopped source reads as off.
        var isLive = label.indexOf('● Live') === 0;

        el.classList.toggle('is-live', isLive);
        el.classList.toggle('is-off', !isLive);
    }

    /* Windows lost on the floor — to the silence gate, to a failed upload, or to
       a provider that refused them — are counted in the source label rather than
       hidden. The transcript would otherwise just have a hole in it, which reads
       as "the AI stopped working" instead of as a specific, fixable fault. */
    function refreshSourceStatus(source) {
        var label = '● Live';
        var lost = source.droppedWindows + source.failures;

        if (lost) {
            label += ' · ' + lost + ' window' + (lost === 1 ? '' : 's') + ' lost';
        }

        if (source.analysisFailures) {
            label += ' · AI unavailable';
        }

        setSourceStatus(source.key, label);
    }

    function delay(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

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
        addDot(el, meta.accent, card.id);
        trimShelf();
        updateFindings();

        if (!noPulse) {
            setTimeout(function () { el.classList.add('pulse'); }, 80);
        }
    }

    /* Drop the oldest cards once the shelf is full. Dots are appended in
       arrival order, so the oldest dot is the first child. */
    function trimShelf() {
        var cards = findings.querySelectorAll('.kb-card');
        var overflow = cards.length - shelfLimit;

        if (overflow <= 0) return;

        for (var i = 0; i < overflow; i++) {
            cards[i].remove();
            if (dotStrip && dotStrip.firstElementChild) dotStrip.removeChild(dotStrip.firstElementChild);
        }

        if (shelfNote) shelfNote.hidden = false;
    }

    function updateFindings() {
        if (!findingsCount) return;
        var n = findings.querySelectorAll('.kb-card').length;
        findingsCount.textContent = String(n).padStart(2, '0');
    }

    function addDot(el, accent, findingId) {
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
            if (findingId) {
                sendFeedback(findingId, 'unhelpful');
                el.classList.add('reviewed');
                return;
            }
            window.deallyToast && window.deallyToast('Not saved — this card has no id yet.', '👎');
        });
        dotStrip.appendChild(dot);
    }

    function sendFeedback(id, status) {
        if (!feedbackUrl) return;

        fetch(feedbackUrl.replace('__ID__', encodeURIComponent(id)), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ status: status }),
        })
            .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
            .then(function (json) {
                if (!json.ok) return;
                window.deallyToast && window.deallyToast('Marked unhelpful — fed to the corrections queue.', '👎');
            })
            .catch(function () {
                window.deallyToast && window.deallyToast('Could not save that feedback.', '⚠️');
            });
    }

    /* Restore findings already persisted for this call so a reload — or a
       second browser tab — does not lose the shelf. */
    function restoreFindings() {
        var el = document.getElementById('initial-findings');
        if (!el) return;

        var cards;
        try {
            cards = JSON.parse(el.textContent);
        } catch (err) {
            return;
        }

        if (!Array.isArray(cards)) return;

        cards.reverse().forEach(function (card) { appendFindingsCard(card, true); });
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

    /* ---- dual-stream capture ---------------------------------------------
       Each source owns its recorder, its upload queue and its sequence
       counter. Recording never waits on the network: `flushSource` hands the
       buffered blob to the queue and the recorder keeps filling the next
       window, so a slow transcription request cannot drop audio. */

    /* ---- voice activity gate ------------------------------------------------
       Each source meters the loudness of the window it just recorded. A window
       with no speech is dropped before it reaches the provider, which keeps
       Whisper from hallucinating over silence and saves transcription quota. */

    function attachLevelMeter(source, mediaStream) {
        var Context = window.AudioContext || window.webkitAudioContext;

        if (!Context || !mediaStream.getAudioTracks || !mediaStream.getAudioTracks().length) return;

        try {
            var context = new Context();
            var analyser = context.createAnalyser();

            analyser.fftSize = 1024;
            analyser.smoothingTimeConstant = 0;
            context.createMediaStreamSource(mediaStream).connect(analyser);

            /* An analyser that is not connected to a destination is not guaranteed
               to be pulled by the audio graph: Chrome is free to leave it reading
               zeros, which marks every window silent and drops the whole call
               while the page still looks live. A muted sink keeps the graph
               running without echoing audio anywhere. */
            var sink = context.createGain();

            sink.gain.value = 0;
            analyser.connect(sink);
            sink.connect(context.destination);

            source.levelContext = context;
            source.levelAnalyser = analyser;
            source.levelSink = sink;
            source.levelBuffer = new Uint8Array(analyser.fftSize);
            source.levelTimer = setInterval(sampleLevel, LEVEL_SAMPLE_MS, source);

            /* getUserMedia resolves outside the click that started capture, so the
               autoplay policy can hand back a suspended context. A suspended
               analyser reports zeros, every window looks silent, and the call is
               discarded while the page still shows "Live". */
            if (context.state === 'suspended') {
                context.resume().catch(function () { /* needs a gesture */ });
            }
        } catch (err) {
            // Metering is an optimisation: recording continues without it.
        }
    }

    function sampleLevel(source) {
        var buffer = source.levelBuffer;

        if (!buffer) return;

        source.levelAnalyser.getByteTimeDomainData(buffer);

        var sum = 0;

        for (var i = 0; i < buffer.length; i++) {
            var sample = (buffer[i] - 128) / 128;

            sum += sample * sample;
        }

        source.energy += sum / buffer.length;
        source.samples++;
    }

    /* Reads and resets the meter, returning the window's loudness. */
    function takeWindowLevel(source) {
        if (!source.samples) return null;

        var rms = Math.sqrt(source.energy / source.samples);

        source.energy = 0;
        source.samples = 0;
        source.levelRms = rms;

        return rms;
    }

    /* The only thing that discards audio: a window with no measurable sound in it
       at all. Quiet-but-real speech is uploaded, because a customer asked softly
       on a compressed meeting stream used to vanish here while the page still
       read "Live". Invented text for silence is filtered server-side, which is
       the accurate place for that decision. */
    function isDeadWindow(source) {
        var rms = takeWindowLevel(source);

        return rms !== null && rms < SILENCE_RMS;
    }

    /* Loudness is also tracked purely to tell the rep when a source is receiving
       something but not enough to transcribe — a muted share, or a microphone
       pointed away from the speaker. */
    function noteWindowLevel(source) {
        if (source.levelRms === null || source.levelRms === undefined) return;

        if (source.levelRms >= ALERT_RMS) {
            source.silentWindows = 0;
            source.warnedSilent = false;

            return;
        }

        source.silentWindows++;

        if (source.silentWindows === SILENT_WINDOWS_BEFORE_WARNING && !source.warnedSilent) {
            source.warnedSilent = true;
            warnIfAllSourcesSilent();
        }
    }

    /* One quiet source is normal — the agent pauses while the customer talks.
       Every source quiet at once is what a rep needs to be told about, because
       that is what a dead microphone or an ended screen share looks like. */
    function warnIfAllSourcesSilent() {
        var active = Object.keys(sources).filter(function (key) { return sources[key].active; });

        if (!active.length) return;

        var quiet = active.filter(function (key) {
            return sources[key].silentWindows >= SILENT_WINDOWS_BEFORE_WARNING;
        });

        if (quiet.length < active.length) return;

        var label = active.length === 1 ? SOURCE_LABELS[active[0]] : 'any source';

        window.deallyToast && window.deallyToast(
            'No speech is reaching ' + label + ' — check the microphone, or re-share the meeting audio.',
            '🎤'
        );
    }

    function releaseLevelMeter(source) {
        if (source.levelTimer) {
            clearInterval(source.levelTimer);
            source.levelTimer = null;
        }

        if (source.levelContext && source.levelContext.state !== 'closed') {
            try { source.levelContext.close(); } catch (err) { /* already closed */ }
        }

        source.levelContext = null;
        source.levelAnalyser = null;
        source.levelSink = null;
        source.levelBuffer = null;
        source.energy = 0;
        source.samples = 0;
        source.levelRms = null;
    }

    function pickMimeType() {
        if (!window.MediaRecorder || !MediaRecorder.isTypeSupported) return '';

        var candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];

        for (var i = 0; i < candidates.length; i++) {
            if (MediaRecorder.isTypeSupported(candidates[i])) return candidates[i];
        }

        return '';
    }

    function extensionFor(mimeType) {
        if (String(mimeType).indexOf('ogg') !== -1) return 'ogg';
        if (String(mimeType).indexOf('mp4') !== -1) return 'mp4';
        return 'webm';
    }

    function newChunkId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'chunk-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    /* A window is only skipped when the meter says the stream is truly dead —
       no audio at all, not merely quiet. Dropping a window discards it
       permanently, and a customer asked quietly on a compressed meeting stream
       used to disappear here while the page still read "Live". Speech that
       Whisper invents for silence is filtered server-side, which is the
       accurate place for that decision. */
    function flushSource(source, isFinal) {
        if (!source.parts.length) return;

        var dead = isDeadWindow(source);

        if (dead && !isFinal) {
            source.parts = [];
            source.windowStartedAt = Date.now();
            source.droppedWindows++;
            refreshSourceStatus(source);
            return;
        }

        noteWindowLevel(source);

        var mimeType = source.recorder ? source.recorder.mimeType : 'audio/webm';
        var now = Date.now();
        var blob = new Blob(source.parts, { type: mimeType });
        var item = {
            blob: blob,
            filename: extensionFor(mimeType),
            chunkId: newChunkId(),
            sequence: source.sequence++,
            startedAt: source.windowStartedAt,
            durationMs: Math.max(0, now - source.windowStartedAt),
            isFinal: !!isFinal,
        };

        source.parts = [];
        source.windowStartedAt = now;

        // Drop the oldest backlog rather than growing without bound on a
        // provider outage; the agent sees a toast instead of a frozen tab.
        if (source.queue.length >= MAX_QUEUED_CHUNKS) {
            source.queue.shift();
            window.deallyToast && window.deallyToast('Transcription is falling behind — some audio was dropped.', '⚠️');
        }

        source.queue.push(item);
        drainSource(source);
    }

    function drainSource(source) {
        if (source.uploading) return;
        source.uploading = true;

        function next() {
            var item = source.queue.shift();

            if (!item) {
                source.uploading = false;
                return;
            }

            setBusy(1);
            uploadChunk(source, item, 0)
                .catch(function () { return null; })
                .then(function (json) {
                    setBusy(-1);
                    if (json) handleChunkResult(source, json);
                    next();
                });
        }

        next();
    }

    function uploadChunk(source, item, attempt) {
        var fd = new FormData();
        fd.append('audio', item.blob, source.key + '-' + item.sequence + '.' + item.filename);
        fd.append('speaker', source.key);
        fd.append('chunk_id', item.chunkId);
        fd.append('client_sequence', String(item.sequence));
        fd.append('started_at_ms', String(item.startedAt));
        fd.append('duration_ms', String(item.durationMs));
        fd.append('is_final', item.isFinal ? '1' : '0');

        /* A request that never settles would hold this source's queue shut for
           good: later windows would pile up and be dropped as backlog, and the
           page would look live while transcribing nothing. */
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var timer = controller
            ? setTimeout(function () { controller.abort(); }, UPLOAD_TIMEOUT_MS)
            : null;

        var request = fetch(transcribeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            body: fd,
            signal: controller ? controller.signal : undefined,
        });

        return request
            .then(function (res) {
                return res.json()
                    .catch(function () { return { ok: false, error: 'unreadable_response' }; })
                    .then(function (json) { return { status: res.status, json: json }; });
            })
            .then(function (result) {
                if (result.json && result.json.ok) return result.json;

                /* `retryable` is the server's own verdict: it marks a failure the
                   same request can succeed on (a provider outage, a rate limit, an
                   exhausted response budget), which a bare status check cannot
                   distinguish from a genuine 400. Retrying a 400 that is not
                   retryable would just burn the window. */
                var retryable = result.json && result.json.retryable === true;

                if (!retryable && result.status >= 500) retryable = true;
                if (result.status === 429) retryable = true;

                if (retryable && attempt < 2) {
                    return delay(800).then(function () { return uploadChunk(source, item, attempt + 1); });
                }

                if (!result.json || result.json.ok !== true) {
                    reportUploadFailure(source, result);
                }

                return null;
            })
            .catch(function () { return null; })
            .then(function (json) {
                if (timer) clearTimeout(timer);

                if (json === null && controller && controller.signal.aborted) {
                    window.deallyToast && window.deallyToast(
                        'Transcription timed out — retrying on the next window.',
                        '⚠️'
                    );
                }

                return json;
            });
    }

    /* A dropped window is invisible: the transcript just has a hole in it and the
       shelf keeps whatever it last had, which reads as "the AI stopped working".
       Rate-limited and repeated failures are surfaced once per window instead. */
    function reportUploadFailure(source, result) {
        var reason = (result.json && result.json.error) || ('http_' + result.status);
        var message = reason === 'transcription_unavailable'
            ? 'Transcription is unavailable right now — the call is still being recorded, and the server has logged why.'
            : 'A recording window was dropped (' + reason + ').';

        source.failures++;

        // One notice per run of failures, not one per window: a provider outage
        // would otherwise bury the page in toasts.
        if (source.failures === 1 || source.failures % 10 === 0) {
            window.deallyToast && window.deallyToast(message, '⚠️');
        }

        refreshSourceStatus(source);
    }

    function handleChunkResult(source, json) {
        /* Nothing in here may be allowed to throw: this runs inside the queue
           drain, so a single bad card would otherwise stop the source uploading
           for the rest of the call. */
        try {
            if (json.transcript) {
                appendEphem('heard', SOURCE_LABELS[source.key] + ' heard', esc(summarise(json.transcript)));
            }

            (json.findings || []).forEach(function (card) { appendFindingsCard(card); });

            /* The upload itself worked but the AI half did not. This is the case
               that made the shelf look permanently broken while the transcript
               kept arriving, so it is called out separately from a dropped
               window. */
            if (json.analysis_failed) {
                source.analysisFailures++;

                if (source.analysisFailures === 1 || source.analysisFailures % 10 === 0) {
                    window.deallyToast && window.deallyToast(
                        'Transcription works, but the AI suggestions failed on the last window — the call has been logged.',
                        '⚠️'
                    );
                }

                return;
            }

            /* A successful window clears the failure run, so the next outage is
               announced rather than staying silent because of an old count. */
            if (source.failures) {
                source.failures = 0;
                refreshSourceStatus(source);
            }
        } catch (err) {
            // Card rendering is presentational; the transcript is already stored.
        }
    }

    function startSource(source, mediaStream) {
        var mimeType = pickMimeType();

        source.stream = mediaStream;
        source.parts = [];
        source.windowStartedAt = Date.now();
        source.energy = 0;
        source.samples = 0;
        source.silentWindows = 0;
        source.warnedSilent = false;
        source.droppedWindows = 0;
        source.failures = 0;
        source.analysisFailures = 0;
        source.levelRms = null;
        source.dataAt = Date.now();
        source.generation = (source.generation || 0) + 1;

        var generation = source.generation;

        attachLevelMeter(source, mediaStream);

        /* The display stream is kept whole, video track included: stopping that
           track ends the browser's capture session and the audio dies with it,
           which is why the customer stream used to deliver one window and then
           go quiet for the rest of the call. The video is kept out of the
           recording instead, by handing MediaRecorder a stream that holds only
           the audio tracks. */
        var recordStream = mediaStream;

        if (mediaStream.getVideoTracks && mediaStream.getVideoTracks().length) {
            var audioTracks = mediaStream.getAudioTracks();

            if (audioTracks.length) recordStream = new MediaStream(audioTracks);
        }

        source.recordStream = recordStream;
        source.mimeType = mimeType;

        // A stream can stop on its own — the browser's "Stop sharing" bar, a
        // revoked permission, a device disappearing. Nothing else notices, so the
        // watchdog below is what keeps the page honest.
        source.stream.getTracks().forEach(function (track) {
            track.onended = function () { markStopped(source, generation); };
        });

        source.active = true;
        source.stopped = false;
        refreshSourceStatus(source);

        armWindow(source);
    }

    /* Records exactly one window with a recorder built for that window alone,
       then stops it so the blob closes with a complete WebM header. Re-arming
       happens when the window closes, so the next window starts as soon as the
       previous one is finished rather than on a fixed grid. */
    function armWindow(source) {
        if (!source.active || source.stopped || !source.recordStream) return;

        var generation = source.generation;
        var mimeType = source.mimeType;
        var parts = [];
        var recorder;

        try {
            recorder = mimeType
                ? new MediaRecorder(source.recordStream, { mimeType: mimeType })
                : new MediaRecorder(source.recordStream);
        } catch (err) {
            // The stream cannot be encoded at all. Retrying on a timer would spin
            // silently, so the source is reported dead and the watchdog decides
            // whether re-acquiring the stream helps.
            markStopped(source, generation);
            return;
        }

        source.recorder = recorder;
        source.parts = parts;

        recorder.ondataavailable = function (e) {
            if (e.data && e.data.size) {
                parts.push(e.data);
                source.dataAt = Date.now();
            }
        };

        /* A recorder that stops before we asked it to is the real failure case
           (the share bar, a revoked permission). Our own stop() also lands here,
           so the window is only committed when we did not ask for it. */
        recorder.onstop = function () {
            if (source.generation !== generation) return;

            if (source.stopped || !source.active) {
                flushSource(source, true);
                return;
            }

            flushSource(source, false);
            armWindow(source);
        };

        recorder.onerror = function () { markStopped(source, generation); };

        try {
            recorder.start();
            source.timer = setTimeout(function () {
                if (source.generation !== generation) return;
                if (recorder.state === 'inactive') { flushSource(source, false); armWindow(source); return; }
                recorder.stop();
            }, CHUNK_MS);
        } catch (err) {
            markStopped(source, generation);
        }
    }

    /* A stopped recorder or an ended track marks the source dead, but only for
       the generation that installed the handler. A previous generation's events
       arrive after a restart and would otherwise kill the fresh source. */
    function markStopped(source, generation) {
        if (source.generation !== generation) return;

        if (source.stopped) return;

        source.stopped = true;

        // An ended track produces no further ondataavailable events, so without
        // this the source would sit dead until the watchdog's stale-data timeout
        // noticed, holding a window recorder open on a stream that is gone.
        clearTimeout(source.timer);
        source.timer = null;

        try {
            if (source.recorder && source.recorder.state !== 'inactive') source.recorder.stop();
        } catch (err) { /* already stopped */ }
    }

    /* The browser is free to stop delivering audio without telling us: the user
       ends the screen share from the browser's own bar, mutes a track, or a
       device vanishes. The source then sits there claiming "Live" while
       recording nothing, and the rep's only way out was to reload the page.
       A source that stops producing data is re-armed in place instead. */
    var WATCHDOG_MS = CHUNK_MS * 3;

    function watchdog() {
        if (!capturing) return;

        var now = Date.now();

        Object.keys(sources).forEach(function (key) {
            var source = sources[key];

            if (!source.active) return;

            var dead = source.stopped || (now - source.dataAt > WATCHDOG_MS);

            if (!dead) return;

            if (source.restarts >= 2) {
                // Persistent failure. Say so once, and let the rep decide, rather
                // than looping re-arms at them forever.
                if (!source.gaveUp) {
                    source.gaveUp = true;
                    setSourceStatus(source.key, '○ Needs attention');
                    window.deallyToast && window.deallyToast(
                        SOURCE_LABELS[source.key] + ' audio stopped. Press Stop, then Start to re-arm it.',
                        '🎤'
                    );
                }

                return;
            }

            source.restarts = (source.restarts || 0) + 1;
            setSourceStatus(source.key, '◌ Reconnecting');
            restartSource(source);
        });
    }

    function restartSource(source) {
        var previous = source.stream;
        var wasActive = source.active;

        clearTimeout(source.timer);
        clearInterval(source.timer);
        source.timer = null;
        source.active = false;
        source.stopped = false;
        releaseLevelMeter(source);

        // The window's own onstop is left installed but its generation check now
        // fails, so this stops the encoder without committing a partial window.
        if (source.recorder && source.recorder.state !== 'inactive') {
            try { source.recorder.stop(); } catch (err) { /* already stopped */ }
        }

        source.recorder = null;
        source.recordStream = null;

        if (previous) {
            previous.getTracks().forEach(function (track) { track.stop(); });
        }

        source.stream = null;

        if (!wasActive) return;

        reacquireStream(source.key).then(function (stream) {
            if (!stream) {
                setSourceStatus(source.key, '○ Needs attention');
                window.deallyToast && window.deallyToast(
                    'Could not re-open ' + SOURCE_LABELS[source.key] + ' audio — check browser permissions.',
                    '🚫'
                );

                return;
            }

            startSource(source, stream);
            window.deallyToast && window.deallyToast(
                SOURCE_LABELS[source.key] + ' audio reconnected — no need to reload.',
                '🎤'
            );
        });
    }

    /* Re-acquisition uses the same constraints as the first attempt; asking for a
       different processing mode mid-call would change the sound of the audio
       halfway through the recording. */
    function reacquireStream(key) {
        if (!navigator.mediaDevices) return Promise.resolve(null);

        var request = key === 'agent'
            ? navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            })
            : (typeof navigator.mediaDevices.getDisplayMedia === 'function'
                ? navigator.mediaDevices.getDisplayMedia({
                    video: true,
                    audio: { echoCancellation: false, noiseSuppression: false, autoGainControl: false },
                })
                : Promise.reject(new Error('unsupported')));

        return request.then(null, function () { return null; });
    }

    function stopSource(source) {
        return new Promise(function (resolve) {
            // Clearing the timer first means the in-flight window cannot re-arm.
            clearTimeout(source.timer);
            clearInterval(source.timer);
            source.timer = null;
            source.active = false;
            source.restarts = 0;
            source.gaveUp = false;

            function finish() {
                flushSource(source, true);
                releaseSource(source);
                resolve();
            }

            if (!source.recorder || source.recorder.state === 'inactive') {
                finish();
                return;
            }

            /* The window recorder's own onstop commits the partial window as the
               final chunk. It is left installed (rather than replaced by a
               once-listener) so that commit is the only one that happens. */
            source.recorder.addEventListener('stop', finish, { once: true });
            source.recorder.stop();
        });
    }

    function releaseSource(source) {
        if (source.stream) {
            source.stream.getTracks().forEach(function (track) { track.stop(); });
        }

        source.stream = null;
        source.recordStream = null;
        source.recorder = null;
        releaseLevelMeter(source);
        setSourceStatus(source.key, '○ Off');
    }

    function stopCapture() {
        if (!capturing) return Promise.resolve();

        capturing = false;
        clearInterval(watchdogTimer);
        watchdogTimer = null;
        toggle.disabled = false;
        toggle.innerHTML = '<i class="bi bi-mic-fill"></i> Start';
        toggle.classList.remove('recording');
        setPill('Paused');

        return Promise.all([stopSource(sources.agent), stopSource(sources.customer)])
            .then(function () {
                // Let the last queued chunks reach the server before the call
                // is closed, otherwise the tail of the call is lost.
                return Promise.all([drainWait(sources.agent), drainWait(sources.customer)]);
            });
    }

    function drainWait(source) {
        if (!source.uploading && !source.queue.length) return Promise.resolve();

        return new Promise(function (resolve) {
            var check = setInterval(function () {
                if (!source.uploading && !source.queue.length) {
                    clearInterval(check);
                    resolve();
                }
            }, 150);
        });
    }

    function startCapture() {
        if (starting || capturing) return;

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            window.deallyToast && window.deallyToast('Audio capture is not supported in this browser.', '🚫');
            return;
        }

        starting = true;
        toggle.disabled = true;
        stopScenario();

        // Held here so a failed start can still release the hardware: the
        // recorders are not wired up yet when openSession() can throw.
        var acquired = [];

        /* The microphone is treated as a phone line: echo cancellation, noise
           suppression, and automatic gain control are all wanted, because they
           are what keep a quiet rep audible over a meeting. */
        var micPromise = navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            },
        });

        /* Meeting audio is the customer stream, and the processing above is
           actively harmful to it: the meeting is already clean, and running
           noise suppression and automatic gain on a shared stream pumps the
           level and makes speech sound thin to the transcriber. If the user
           declines the share we keep going with the microphone alone rather
           than losing the call. */
        var screenPromise = typeof navigator.mediaDevices.getDisplayMedia === 'function'
            ? navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: {
                    echoCancellation: false,
                    noiseSuppression: false,
                    autoGainControl: false,
                },
            })
            : Promise.reject(new Error('unsupported'));

        return Promise.all([
            micPromise,
            screenPromise.then(
                function (s) {
                    /* A share with no audio track would record empty blobs for
                       the rest of the call, so it is treated as no meeting audio
                       rather than as a source that silently never speaks. */
                    if (s.getAudioTracks && !s.getAudioTracks().length) {
                        s.getTracks().forEach(function (track) { track.stop(); });

                        return null;
                    }

                    return s;
                },
                function () { return null; }
            ),
        ])
            .then(function (streams) {
                var mic = streams[0];
                var meeting = streams[1];

                acquired = meeting ? [mic, meeting] : [mic];

                if (!meeting) {
                    window.deallyToast && window.deallyToast('No meeting audio shared — only your microphone is being captured.', '🎧');
                }

                return openSession().then(function () {
                    startSource(sources.agent, mic);
                    if (meeting) startSource(sources.customer, meeting);

                    capturing = true;
                    starting = false;
                    toggle.disabled = false;
                    toggle.innerHTML = '<i class="bi bi-stop-circle-fill"></i> Stop';
                    toggle.classList.add('recording');
                    setPill(meeting ? 'Transcribing · 2 streams' : 'Transcribing · mic only');
                    clearInterval(watchdogTimer);
                    watchdogTimer = setInterval(watchdog, 5000);
                });
            })
            .catch(function () {
                acquired.forEach(function (mediaStream) {
                    mediaStream.getTracks().forEach(function (track) { track.stop(); });
                });
                releaseSource(sources.agent);
                releaseSource(sources.customer);
                starting = false;
                capturing = false;
                toggle.disabled = false;
                setPill('Ready');
                window.deallyToast && window.deallyToast('Capture could not start — check microphone and screen permissions.', '🚫');
            });
    }

    function openSession() {
        if (!startUrl) return Promise.resolve();

        return fetch(startUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        })
            .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
            .then(function (json) {
                if (!json.ok) throw new Error(json.error || 'start_failed');
                return json;
            });
    }

    function toggleListening() {
        if (capturing) {
            stopCapture();
            return;
        }

        startCapture();
    }

    toggle.addEventListener('click', toggleListening);

    window.addEventListener('beforeunload', function () {
        if (capturing) {
            releaseSource(sources.agent);
            releaseSource(sources.customer);
        }
    });

    /* ---- transcription scenario -------------------------------------------
       Scripted demo feed. Off unless the server renders data-demo-mode="true",
       so a configured provider never has fabricated AI shown alongside real
       transcription. */

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

    if (demoMode) runTranscriptionScenario();
    else restoreFindings();

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
    var ending = false;

    function endCall() {
        if (ending) return;

        var durationEl = document.getElementById('end-duration');
        var notesEl = document.getElementById('end-notes');
        var notesInput = document.getElementById('notes-input');
        var timerEl = document.getElementById('call-timer');
        if (notesEl && notesInput) notesEl.value = notesInput.value;
        if (durationEl && timerEl) durationEl.value = timerEl.textContent;
        if (!window.confirm('End this call and generate the post-call summary?')) return;

        ending = true;
        var form = document.getElementById('end-call-form');

        if (!form) return;

        // Drain the capture first so the final chunks are not lost when the
        // page navigates to the summary.
        stopCapture().then(function () {
            form.submit();
        });
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
