/* ---------- Review task interactions ----------
 *
 * The review task is where a rep corrects the AI, adds an objection it missed,
 * and resolves a deal-status flag. Every one of those is a POST that has to
 * behave honestly on failure: these are corrections feeding a dataset, so a
 * silent failure is worse than a slow one — the rep believes the record changed
 * when it did not.
 */

import { apiPost } from './api.js';

function wireCorrectionChips(root) {
    root.querySelectorAll('[data-correct-group]').forEach(function (group) {
        var field = group.getAttribute('data-correct-group');
        var url = group.getAttribute('data-correct-url');

        group.querySelectorAll('[data-correct-value]').forEach(function (chip) {
            function apply() {
                var value = chip.getAttribute('data-correct-value');
                if (chip.classList.contains('selected')) return;

                group.querySelectorAll('[data-correct-value]').forEach(function (other) {
                    other.classList.remove('selected', 'positive', 'neutral', 'negative', 'hot', 'warm', 'cold');
                });
                chip.classList.add('selected', value);

                var note = group.parentElement ? group.parentElement.querySelector('.ai-read-note') : null;
                if (note) {
                    note.classList.add('is-saving');
                    note.textContent = 'Saving…';
                }

                apiPost(url, { field: field, value: value })
                    .then(function () {
                        if (note) {
                            note.classList.remove('is-saving');
                            note.innerHTML =
                                'AI read: ' + chip.textContent.trim() +
                                ' · <span class="ai-read-corrected">corrected by you</span>';
                        }
                    })
                    .catch(function (error) {
                        /* Put the selection back. A chip that stays lit while
                           the server rejected it is a correction that never
                           happened. */
                        chip.classList.remove('selected', value);
                        group.querySelectorAll('[data-correct-value]').forEach(function (other) {
                            if (other !== chip) other.classList.add('selected', other.getAttribute('data-correct-value'));
                        });
                        if (note) {
                            note.classList.remove('is-saving');
                            note.textContent = 'Not saved: ' + (error && error.message ? error.message : 'the server rejected it') + '.';
                        }
                    });
            }

            chip.addEventListener('click', apply);
            chip.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    apply();
                }
            });
        });
    });

    root.querySelectorAll('select[data-correct-field]').forEach(function (select) {
        select.addEventListener('change', function () {
            var previous = select.dataset.previousValue || select.value;
            apiPost(select.getAttribute('data-correct-url'), {
                field: select.getAttribute('data-correct-field'),
                value: select.value,
            })
                .then(function () {
                    select.dataset.previousValue = select.value;
                })
                .catch(function (error) {
                    select.value = previous;
                    alert('Not saved: ' + (error && error.message ? error.message : 'the server rejected it'));
                });
        });
    });
}

function wireFlagResolution(root) {
    root.querySelectorAll('[data-resolve-flag]').forEach(function (button) {
        button.addEventListener('click', function () {
            var url = button.getAttribute('data-flag-url');
            var status = button.getAttribute('data-resolve-flag');
            var flag = button.closest('[data-flag-id]');

            button.disabled = true;

            apiPost(url, { status: status })
                .then(function () {
                    if (flag) {
                        flag.remove();
                    }
                    /* The review task may be closable now, but this page has
                       no way to know that without asking. Say it, rather than
                       leaving the rep to find out when the close bounces. */
                    var banner = document.getElementById('review-flag-cleared');
                    if (banner) banner.classList.remove('is-hidden');
                })
                .catch(function (error) {
                    button.disabled = false;
                    alert('Not saved: ' + (error && error.message ? error.message : 'the server rejected it'));
                });
        });
    });

    var addButton = root.querySelector('#review-flag-add, #review-task-flag-add');
    if (addButton) {
        addButton.addEventListener('click', function () {
            var isTaskModal = addButton.id === 'review-task-flag-add';
            var input = document.getElementById(isTaskModal ? 'review-task-flag-input' : 'review-flag-input');
            var why = document.getElementById(isTaskModal ? 'review-task-flag-why' : 'review-flag-why');
            var url = addButton.getAttribute('data-flag-url');

            if (!input || !input.value.trim() || !url) return;

            addButton.disabled = true;

            apiPost(url, { headline: input.value.trim(), rationale: why ? why.value.trim() : '' })
                .then(function () { window.location.reload(); })
                .catch(function (error) {
                    addButton.disabled = false;
                    alert('Not saved: ' + (error && error.message ? error.message : 'the server rejected it'));
                });
        });
    }
}

function wireMissedObjection(root) {
    var addButton = root.querySelector('#review-objection-add, #review-task-objection-add');
    if (!addButton) return;

    addButton.addEventListener('click', function () {
        var isTaskModal = addButton.id === 'review-task-objection-add';
        var input = document.getElementById(isTaskModal ? 'review-task-objection-input' : 'review-objection-input');
        var url = addButton.getAttribute('data-objection-url');

        if (!input || !input.value.trim() || !url) return;

        addButton.disabled = true;

        apiPost(url, { text: input.value.trim() })
            .then(function () { window.location.reload(); })
            .catch(function (error) {
                addButton.disabled = false;
                alert('Not saved: ' + (error && error.message ? error.message : 'the server rejected it'));
            });
    });
}

function wireProposalCreation(root) {
    var button = root.querySelector('#create-proposal-btn, #review-task-proposal-create');
    if (!button) return;

    button.addEventListener('click', function () {
        var url = button.getAttribute('data-proposal-url');
        if (!url) return;

        button.disabled = true;
        var original = button.textContent;
        button.textContent = 'Creating…';

        apiPost(url, {})
            .then(function (result) {
                button.textContent = 'Task created';
                if (result && result.url) {
                    button.closest('.proposal-intent, .review-task-proposal') &&
                        window.setTimeout(function () { window.location.href = result.url; }, 600);
                }
            })
            .catch(function (error) {
                button.disabled = false;
                button.textContent = original;
                alert('Not created: ' + (error && error.message ? error.message : 'the server rejected it'));
            });
    });
}

export function initReviewTask() {
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-open-modal]');
        if (!trigger) return;
        if (!trigger.getAttribute('data-modal-url')) return;

        /* A review task's controls are bound when it is fetched, so the DOM it
           arrives into is not scanned once at page load. */
        window.setTimeout(function () {
            var modal = document.getElementById(trigger.getAttribute('data-open-modal'));
            var host = modal && modal.querySelector('[data-modal-host]');
            if (host) bindReviewTask(host);
        }, 0);
    });

    var page = document.querySelector('.review-shell');
    if (page) bindReviewTask(page);
}

function bindReviewTask(root) {
    if (root.dataset.reviewTaskBound === 'true') return;
    root.dataset.reviewTaskBound = 'true';

    wireCorrectionChips(root);
    wireFlagResolution(root);
    wireMissedObjection(root);
    wireProposalCreation(root);
}
