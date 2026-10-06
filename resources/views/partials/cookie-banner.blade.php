<!-- Cookie Banner -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-[#1a1a1a] border-t border-gold/30 shadow-lg z-50 hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1">
                <p class="text-white text-sm leading-relaxed">
                    <span class="font-semibold text-gold">Aviso de cookies:</span>
                    Utilizamos cookies técnicas, necesarias para que la web funcione. El resto —Google Analytics y Ahrefs Analytics— solo se activa si pulsas «Aceptar».
                    <a href="{{ route('cookies') }}" class="text-gold hover:text-gold-light underline ml-1">
                        Más información
                    </a>
                </p>
            </div>
            <div class="flex gap-2 w-full sm:w-auto">
                <button id="cookie-reject" class="btn-outline flex-1 sm:flex-none text-xs sm:text-sm">
                    Rechazar
                </button>
                <button id="cookie-accept" class="btn-gold flex-1 sm:flex-none text-xs sm:text-sm">
                    Aceptar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Cookie Banner Logic
    document.addEventListener('DOMContentLoaded', function() {
        const cookieBanner = document.getElementById('cookie-banner');
        const acceptBtn = document.getElementById('cookie-accept');
        const rejectBtn = document.getElementById('cookie-reject');
        const cookieConsent = 'cookieConsent';

        // localStorage can throw (some browsers block it entirely when the
        // user disables all site data). Treat a throw as "no confirmed
        // consent": never stuck, never tracking.
        function getConsent() {
            try {
                return localStorage.getItem(cookieConsent);
            } catch (e) {
                return null;
            }
        }

        function setConsent(value) {
            try {
                localStorage.setItem(cookieConsent, value);
                return true;
            } catch (e) {
                return false;
            }
        }

        // Check if user has already made a choice
        if (!getConsent()) {
            cookieBanner.classList.remove('hidden');
        }

        // Delete the Google Analytics cookies set while consent was accepted.
        // gtag.js defaults to cookie_domain: 'auto', which sets _ga/_ga_<id>
        // on the root domain (without "www."), not on location.hostname.
        function deleteGoogleAnalyticsCookies() {
            const host = location.hostname;
            const rootHost = host.replace(/^www\./, '');
            const domains = [null, host, '.' + host];
            if (rootHost !== host) {
                domains.push(rootHost, '.' + rootHost);
            }

            document.cookie.split(';').forEach(function (entry) {
                const name = entry.split('=')[0].trim();
                if (name.indexOf('_ga') !== 0) {
                    return;
                }
                domains.forEach(function (domain) {
                    const expired = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                    document.cookie = domain ? expired + '; domain=' + domain : expired;
                });
            });
        }

        // Accept cookies
        acceptBtn.addEventListener('click', function() {
            const confirmed = setConsent('accepted');
            cookieBanner.classList.add('hidden');
            if (confirmed) {
                (window.__analyticsConsentLoaders || []).forEach(function (load) {
                    load();
                });
            }
        });

        // Reject cookies
        rejectBtn.addEventListener('click', function() {
            const hadAccepted = getConsent() === 'accepted';
            const confirmed = setConsent('rejected');
            cookieBanner.classList.add('hidden');
            if (confirmed && hadAccepted) {
                // Analytics is already running in this page: the only reliable
                // way to stop it and clear its cookies is to reload.
                deleteGoogleAnalyticsCookies();
                window.location.reload();
            }
        });

        // Reopen the banner from the footer link or the cookies policy page,
        // so the choice can be changed after the first visit.
        document.querySelectorAll('[data-cookie-settings]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                cookieBanner.classList.remove('hidden');
            });
        });
    });
</script>
