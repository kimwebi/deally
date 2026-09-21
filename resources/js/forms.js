/* ---------- Forms ---------- */

export function initForms() {
    // Quick add forms (task, kb, proposal) submit normally.

    // "End call" buttons that confirm before navigating
    // (skipped on the live page initLiveAssistant drives #end-call-form)
    if (!document.getElementById('end-call-form')) {
        document.querySelectorAll('[data-end-call]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var href = btn.getAttribute('href') || btn.getAttribute('data-end-call');
                if (window.confirm('End this call and generate the post-call summary?')) {
                    window.location.href = href;
                }
            });
        });
    }

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
                        btn.innerHTML = 'âœ“ Added to Tasks';
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
        flag.textContent = flag.classList.contains('flagged') ? 'ðŸš©' : 'ðŸ³ï¸';
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
                chip.textContent = 'âœ“ Task added';
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
        '<div class="reason-meta"><span>You Â· Query</span></div>' +
        '</div>' +
        '<div class="reason-text">' + text + '</div>';
    stream.appendChild(q);

    // Append an AI response after the synthesized delay.
    var r = document.createElement('div');
    r.className = 'reason-item ai-response';
    r.style.display = 'none';
    r.innerHTML =
        '<div class="reason-header">' +
        '<div class="reason-meta"><span class="meta-icon">âœ¦</span><span>AI Â· Response</span></div>' +
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
