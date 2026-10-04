<!-- Cookie Banner -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-[#1a1a1a] border-t border-gold/30 shadow-lg z-50 hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1">
                <p class="text-white text-sm leading-relaxed">
                    <span class="font-semibold text-gold">Aviso de cookies:</span>
                    Utilizamos cookies técnicas, necesarias para que la web funcione. Las cookies de análisis y publicidad solo se instalan si pulsas «Aceptar».
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

        // Check if user has already made a choice
        if (!localStorage.getItem(cookieConsent)) {
            cookieBanner.classList.remove('hidden');
        }

        // Delete the Google Analytics cookies set while consent was accepted.
        function deleteGoogleAnalyticsCookies() {
            document.cookie.split(';').forEach(function (entry) {
                const name = entry.split('=')[0].trim();
                if (name.indexOf('_ga') !== 0) {
                    return;
                }
                const expired = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                document.cookie = expired;
                document.cookie = expired + '; domain=' + location.hostname;
                document.cookie = expired + '; domain=.' + location.hostname;
            });
        }

        // Accept cookies
        acceptBtn.addEventListener('click', function() {
            localStorage.setItem(cookieConsent, 'accepted');
            cookieBanner.classList.add('hidden');
            (window.__analyticsConsentLoaders || []).forEach(function (load) {
                load();
            });
        });

        // Reject cookies
        rejectBtn.addEventListener('click', function() {
            const hadAccepted = localStorage.getItem(cookieConsent) === 'accepted';
            localStorage.setItem(cookieConsent, 'rejected');
            cookieBanner.classList.add('hidden');
            if (hadAccepted) {
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
