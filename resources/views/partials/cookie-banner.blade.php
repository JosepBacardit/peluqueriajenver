<!-- Cookie Banner -->
<div id="cookie-banner" class="fixed bottom-0 left-0 right-0 bg-[#1a1a1a] border-t border-gold/30 shadow-lg z-50 hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex-1">
                <p class="text-white text-sm leading-relaxed">
                    <span class="font-semibold text-gold">Aviso de cookies:</span>
                    Utilizamos cookies para mejorar tu experiencia. Al continuar navegando, aceptas nuestro uso de cookies.
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

        // Accept cookies
        acceptBtn.addEventListener('click', function() {
            localStorage.setItem(cookieConsent, 'accepted');
            cookieBanner.classList.add('hidden');
            // Enable analytics and tracking scripts here if needed
        });

        // Reject cookies
        rejectBtn.addEventListener('click', function() {
            localStorage.setItem(cookieConsent, 'rejected');
            cookieBanner.classList.add('hidden');
            // Disable non-essential cookies here if needed
        });
    });
</script>
