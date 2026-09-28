/* ---------- Call setup controls ----------
 *
 * The two things a rep does on the live screen before the call starts: read the
 * invitation they are about to send, and try to admit the transcription bot.
 *
 * Both report the platform's real state. "Admit bot" on a platform with no
 * credentials returns an explanation, not a fake success — a bot that never
 * joined must never look like one that did, because the rep will tell the
 * customer it is recording when it is not.
 */

import { apiPost } from './api.js';

function wireInvitationPreview() {
    var button = document.getElementById('invite-preview-btn');
    if (!button) return;

    button.addEventListener('click', function () {
        var url = button.getAttribute('data-preview-url');
        button.disabled = true;

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                button.disabled = false;
                if (!json.ok || !json.copy) {
                    window.deallyToast && window.deallyToast('Could not build the invitation.', '⚠️');
                    return;
                }
                showInvitationCopy(json.copy);
            })
            .catch(function () {
                button.disabled = false;
                window.deallyToast && window.deallyToast('Could not build the invitation.', '⚠️');
            });
    });
}

function showInvitationCopy(copy) {
    var existing = document.getElementById('invitation-preview');
    if (existing) existing.remove();

    var panel = document.createElement('div');
    panel.id = 'invitation-preview';
    panel.className = 'invitation-preview';
    panel.innerHTML =
        '<div class="invitation-preview-head">' +
        '<strong>Invitation to the customer</strong>' +
        '<button type="button" class="invitation-preview-close" aria-label="Close">✕</button>' +
        '</div>' +
        '<div class="invitation-preview-subject">' + escapeHtml(copy.subject) + '</div>' +
        '<pre class="invitation-preview-body">' + escapeHtml(copy.body) + '</pre>' +
        '<p class="invitation-preview-note">' +
        'This exact wording is stored on the call, so what was sent can be checked later.' +
        '</p>';

    var anchor = document.querySelector('.call-note') || document.body;
    anchor.insertAdjacentElement('afterend', panel);

    panel.querySelector('.invitation-preview-close').addEventListener('click', function () {
        panel.remove();
    });
}

function wireBotJoin() {
    var button = document.getElementById('bot-join-btn');
    if (!button) return;

    button.addEventListener('click', function () {
        var url = button.getAttribute('data-join-url');
        var label = button.textContent;

        button.disabled = true;
        button.textContent = 'Admitting…';

        apiPost(url, {})
            .then(function (json) {
                button.disabled = false;
                button.textContent = label;
                window.deallyToast && window.deallyToast('Bot admitted to the meeting.', '🎥');
                setPlatformState(json.bot_join_status, json.note);
            })
            .catch(function (error) {
                button.disabled = false;
                button.textContent = label;

                /* The unavailable case is the expected one, so it is stated
                   plainly rather than as a failure. The note is the platform's
                   own reason and is the whole point of showing this control. */
                var message = (error && error.payload && error.payload.note) ||
                    (error && error.message) || 'unknown reason';
                window.deallyToast && window.deallyToast('Bot not admitted: ' + message, '⚠️');
                setPlatformState('unavailable', message);
            });
    });
}

function setPlatformState(status, note) {
    var chip = document.querySelector('.call-note-platform');
    if (!chip) return;

    var text = chip.textContent;
    var tail = text.replace(/^🎥\s*/, '').replace(/\s*·\s*bot [a-z ]+$/i, '');

    chip.textContent = '🎥 ' + tail + ' · ' + botLabel(status);
    chip.classList.toggle('is-joined', status === 'joined');
    chip.classList.toggle('is-unconfirmed', status !== 'joined');

    if (note) chip.title = note;
}

function botLabel(status) {
    switch (status) {
        case 'joined':
            return 'bot joined';
        case 'requested':
            return 'bot joining';
        case 'failed':
            return 'bot failed to join';
        case 'unavailable':
            return 'bot not available';
        default:
            return 'bot not admitted';
    }
}

function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
}

export function initCallSetup() {
    wireInvitationPreview();
    wireBotJoin();
}
