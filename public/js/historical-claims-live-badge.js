/* Live Historical Claims badge. The server remains the source of truth. */
(function () {
    'use strict';

    // Ten seconds is frequent enough for HR triage without a per-second
    // database query from every open staff browser.
    const POLL_INTERVAL_MS = 10000;

    function findClaimMenuLink() {
        return Array.from(document.querySelectorAll('.main-sidebar a[href]')).find(function (link) {
            try {
                const url = new URL(link.href, window.location.href);
                return url.origin === window.location.origin
                    && /\/admin\/applicant-claims\/?$/.test(url.pathname);
            } catch (_) {
                return false;
            }
        });
    }

    function start() {
        // The Gate-filtered sidebar does not display this link to other users.
        // Do not poll for users who are not shown the menu item.
        const link = findClaimMenuLink();
        if (!link) return;

        const endpoint = new URL(link.href, window.location.href);
        endpoint.pathname = endpoint.pathname.replace(/\/+$/, '') + '/pending-count';
        endpoint.search = '';
        endpoint.hash = '';

        let inFlight = false;
        let stopped = false;

        function updateBadge(count) {
            let badge = link.querySelector('.right.badge');

            if (count === 0) {
                if (badge) badge.remove();
                return;
            }

            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'right badge badge-warning';
                (link.querySelector('p') || link).appendChild(badge);
            }

            badge.textContent = String(count);
            badge.setAttribute('aria-label', count + ' historical claims awaiting HR review');
            badge.title = count + ' pending or clarification requests';
        }

        async function checkCount() {
            if (stopped || inFlight || document.hidden) return;
            inFlight = true;

            try {
                const response = await fetch(endpoint.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if ([401, 403, 419].includes(response.status)) {
                    // Session has expired or the user's role changed.
                    stopped = true;
                    window.clearInterval(timer);
                    return;
                }

                if (!response.ok) return;

                const data = await response.json();
                if (Number.isSafeInteger(data.count) && data.count >= 0) {
                    updateBadge(data.count);
                }
            } catch (_) {
                // Temporary network error: preserve the last known count.
            } finally {
                inFlight = false;
            }
        }

        const timer = window.setInterval(checkCount, POLL_INTERVAL_MS);

        // No polling while the tab is hidden; check immediately upon return.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) void checkCount();
        });
        window.addEventListener('focus', function () {
            void checkCount();
        });
        void checkCount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
