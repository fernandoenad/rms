/* RMS Applicant Portal: explicit session recovery, never automatic resubmission.
 * No stored credentials, drafts, or assessment answers are read or transmitted. */
(function () {
    'use strict';

    var source = document.currentScript;
    var signInUrl = source && source.getAttribute('data-sign-in-url');
    var expired = false;

    function show() {
        if (!signInUrl) return;
        var banner = document.getElementById('rms-applicant-session-expired');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'rms-applicant-session-expired';
            banner.setAttribute('role', 'alert');
            banner.setAttribute('aria-live', 'assertive');
            banner.className = 'alert alert-warning shadow-sm';
            banner.style.cssText = 'position:fixed;top:12px;left:12px;right:12px;z-index:11000;max-width:620px;margin:0 auto;border:2px solid #bb861b;background:#fff8e7;';
            var text = document.createElement('p');
            text.className = 'mb-2 font-weight-bold';
            text.textContent = 'Your session has expired. Your last action may not have been saved.';
            var detail = document.createElement('p');
            detail.className = 'mb-3 small';
            detail.textContent = 'Sign in again to continue. RMS will not automatically retry a submission or assessment response.';
            var link = document.createElement('a');
            link.className = 'btn btn-primary btn-sm';
            link.href = signInUrl;
            link.textContent = 'Sign in again';
            banner.appendChild(text);
            banner.appendChild(detail);
            banner.appendChild(link);
            document.body.appendChild(banner);
        }
        banner.hidden = false;
    }

    window.RMSApplicantSession = {
        get expired() { return expired; },
        isExpiredResponse: function (response) {
            return !!response && (response.status === 401 || response.status === 419);
        },
        check: function (response) {
            if (this.isExpiredResponse(response)) {
                expired = true;
                show();
                return true;
            }
            return false;
        },
        show: function () {
            expired = true;
            show();
        }
    };
})();
