/* ---------- Shared API helper ----------
 *
 * Every JSON POST in the app needs the CSRF token and the same failure
 * handling, and getting that subtly different per module is how a rep ends up
 * believing a correction saved when it did not. A non-2xx throws with the
 * server's own message where it gave one, so callers can say what actually
 * failed rather than "something went wrong".
 */

function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * @throws {Error} on any non-2xx response, carrying the server's message.
 */
export function apiPost(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify(body || {}),
    }).then(function (response) {
        return response
            .json()
            .catch(function () { return {}; })
            .then(function (json) {
                if (!response.ok) {
                    var error = new Error(
                        json.message || json.error || 'The server responded ' + response.status
                    );
                    error.status = response.status;
                    error.payload = json;
                    throw error;
                }
                return json;
            });
    });
}

export { csrfToken };
