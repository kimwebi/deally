/* DeAlly — post-call replay.

   Capture stores one small file per window rather than a single container, so
   playback sequences those windows through one audio element. The gaps that
   approach would otherwise introduce are hidden by prefetching the next few
   windows as blobs: without that, every four seconds of audio costs a round
   trip and the call sounds like it keeps stuttering. */

var PREFETCH_AHEAD = 3;

export function initCallReplay() {
    var root = document.getElementById('call-replay');

    if (!root) return;

    var indexNode = document.getElementById('replay-index');

    if (!indexNode) return;

    var all = readJson(indexNode) || [];

    if (!all.length) return;

    var byId = {};
    all.forEach(function (window) { byId[window.id] = window; });

    var toggle = document.getElementById('replay-toggle');
    var track = document.getElementById('replay-track');
    var elapsedNode = document.getElementById('replay-elapsed');
    var note = document.getElementById('replay-note');
    var sourceButtons = slice(root.querySelectorAll('.replay-source'));

    var audio = new Audio();
    audio.preload = 'auto';

    var list = all;
    var offsets = [];  // Start time of each window within the filtered list.
    var totalMs = 0;
    var position = 0; // Index of the window currently loaded.
    var playing = false;
    var cached = {};   // Window id -> Promise<object URL>.
    var wanted = new Set();

    function slice(nodeList) {
        return Array.prototype.slice.call(nodeList || []);
    }

    function readJson(node) {
        try {
            return JSON.parse(node.textContent);
        } catch (err) {
            return [];
        }
    }

    function buildList(source) {
        list = source === 'all'
            ? all.slice()
            : all.filter(function (window) { return window.source === source; });

        offsets = [];
        totalMs = 0;

        list.forEach(function (window, i) {
            offsets.push(totalMs);
            totalMs += window.ms || 0;
        });

        position = 0;
        playing = false;
        audio.pause();
        setSourceButtons(source);
        render(0);
    }

    function setSourceButtons(source) {
        sourceButtons.forEach(function (button) {
            var selected = button.dataset.source === source;
            button.classList.toggle('selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    }

    function objectUrl(id) {
        if (cached[id]) return cached[id];

        var window_ = byId[id];

        if (!window_) return Promise.resolve(null);

        /* Nothing is cached on failure, so a later attempt tries again rather
           than replaying a permanent failure for the rest of the session. */
        cached[id] = fetch(window_.url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('http_' + response.status);

                return response.blob();
            })
            .then(function (blob) {
                return URL.createObjectURL(blob);
            })
            .catch(function () {
                delete cached[id];

                return null;
            });

        return cached[id];
    }

    function release(id) {
        var pending = cached[id];

        if (!pending) return;

        pending.then(function (url) {
            if (url) URL.revokeObjectURL(url);
        });

        delete cached[id];
    }

    /* Keep the next few windows ready so the hand-off between them is instant.
       Anything further ahead would only hold memory for nothing. */
    function prefetch() {
        var currentId = list[position].id;
        var i;

        for (i = position + 1; i <= position + PREFETCH_AHEAD && i < list.length; i++) {
            var id = list[i].id;

            if (wanted.has(id)) continue;

            wanted.add(id);
            objectUrl(id);
        }

        // Windows already played are of no further use; keeping their blobs
        // alive would hold the whole call in memory by the end of it.
        Array.from(wanted).forEach(function (id) {
            if (id >= currentId) return;

            wanted.delete(id);
            release(id);
        });
    }

    function load(index, autoplay) {
        if (index < 0 || index >= list.length) {
            playing = false;
            audio.pause();
            render(totalMs);

            return Promise.resolve(false);
        }

        position = index;
        prefetch();

        return objectUrl(list[index].id).then(function (url) {
            if (!url) {
                setNote('This browser could not decode the recording. The transcript below is unaffected.');

                return false;
            }

            audio.src = url;

            if (!autoplay) {
                render();

                return true;
            }

            return audio.play().then(function () {
                playing = true;
                render();

                return true;
            }).catch(function () {
                // Only ever requested from a click, so a rejection here means
                // the browser refused rather than that we asked without a
                // gesture.
                setNote('Playback was blocked. Press play to start.');

                return false;
            });
        });
    }

    function render(forceElapsed) {
        var elapsed = forceElapsed !== undefined
            ? forceElapsed
            : (offsets[position] || 0) + (audio.currentTime || 0) * 1000;

        if (elapsed > totalMs) elapsed = totalMs;

        var percent = totalMs > 0 ? (elapsed / totalMs) * 100 : 0;

        track.style.setProperty('--played', percent.toFixed(2) + '%');
        elapsedNode.textContent = clock(elapsed) + ' / ' + clock(totalMs);
        toggle.innerHTML = playing ? '❚❚' : '▶';
        toggle.setAttribute('aria-label', playing ? 'Pause the call recording' : 'Play the call recording');
    }

    function clock(ms) {
        var total = Math.max(0, Math.round(ms / 1000));
        var minutes = Math.floor(total / 60);
        var seconds = total % 60;

        return (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }

    function setNote(message) {
        if (note) note.textContent = message;
    }

    function nearestWindow(ms) {
        var best = 0;

        for (var i = 0; i < offsets.length; i++) {
            if (offsets[i] <= ms) best = i;
            else break;
        }

        return best;
    }

    function indexOfWindow(id) {
        for (var i = 0; i < list.length; i++) {
            if (list[i].id === id) return i;
        }

        return -1;
    }

    function highlight(line) {
        slice(document.querySelectorAll('.transcript-line.is-playing'))
            .forEach(function (el) { el.classList.remove('is-playing'); });

        if (line) line.classList.add('is-playing');
    }

    audio.addEventListener('ended', function () {
        if (position + 1 < list.length) {
            load(position + 1, true);
        } else {
            playing = false;
            render(totalMs);
        }
    });

    audio.addEventListener('timeupdate', function () { render(); });

    audio.addEventListener('error', function () {
        if (!audio.src) return;

        playing = false;
        setNote('This recording could not be played in this browser. The transcript below is unaffected.');
        render();
    });

    toggle.addEventListener('click', function () {
        if (playing) {
            playing = false;
            audio.pause();
            render();

            return;
        }

        // Replaying from the end should start over rather than sit silent.
        if (position >= list.length - 1) position = 0;

        load(position, true);
    });

    track.addEventListener('click', function (event) {
        var bounds = track.getBoundingClientRect();
        var ratio = bounds.width > 0 ? (event.clientX - bounds.left) / bounds.width : 0;

        highlight(null);
        load(nearestWindow(ratio * totalMs), true);
    });

    sourceButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            buildList(button.dataset.source);
        });
    });

    /* Every transcript line carries the window it was transcribed from, so a
       rep can jump to the moment a line was said instead of hunting for it. */
    slice(document.querySelectorAll('[data-replay-window]')).forEach(function (line) {
        var button = line.querySelector('.transcript-play');
        var id = Number(line.dataset.replayWindow);

        if (!button) return;

        button.addEventListener('click', function (event) {
            event.stopPropagation();

            // The line may belong to the source the active filter is hiding, so
            // widening the filter beats refusing to play the line at all.
            if (indexOfWindow(id) < 0) buildList('all');

            var index = indexOfWindow(id);

            if (index < 0) return;

            highlight(line);
            load(index, true);
        });
    });

    render(0);

    window.deallyCallReplay = {
        seekTo: function (ms) {
            highlight(null);

            return load(nearestWindow(ms), true);
        },
        stop: function () {
            playing = false;
            audio.pause();
            highlight(null);
            render();
        },
    };
}
