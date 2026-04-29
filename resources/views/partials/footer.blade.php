<footer class="bg-[#0D0D0D] border-t border-gold/30">
    <!-- Main Footer Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- Column 1: Logo + Description + Social -->
            <div>
                <img src="{{ asset('images/logo-jenver.png') }}" alt="Peluquería Jenver Logo" class="h-20 w-auto mb-6">
                <p class="text-gray-400 text-sm mb-6">
                    {{ __('navigation.footer.description') }}
                </p>
                <div class="flex gap-4">
                    <a href="https://www.instagram.com/peluqueriajenver/" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-gold transition-colors" title="Instagram">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.057-1.645.069-4.849.069-3.204 0-3.584-.012-4.849-.069-3.259-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.322a1.44 1.44 0 110-2.88 1.44 1.44 0 010 2.88z"/>
                        </svg>
                    </a>
                    <a href="https://www.tiktok.com/@peluqueriajenver" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-gold transition-colors" title="TikTok">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19.498 3h-3.6v12.597c0 1.639-.945 3.056-2.31 3.056-1.368 0-2.314-.947-2.314-3.056V3h-3.596v12.597C7.178 19.052 8.992 21 11.593 21c2.601 0 4.305-1.948 4.305-5.403V3z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Column 2: Services Links -->
            <div>
                <h3 class="text-gold font-serif text-lg font-semibold mb-4">{{ __('navigation.footer.services.title') }}</h3>
                <ul class="space-y-3 text-gray-400 text-sm">
                    <li><a href="{{ route('color-mechas') }}" class="hover:text-gold transition-colors">{{ __('navigation.footer.services.items.balayage') }}</a></li>
                    <li><a href="{{ route('corte-tratamientos') }}" class="hover:text-gold transition-colors">{{ __('navigation.footer.services.items.cut') }}</a></li>
                    <li><a href="{{ route('peinados-eventos') }}" class="hover:text-gold transition-colors">{{ __('navigation.footer.services.items.peinados') }}</a></li>
                    <li><a href="{{ route('belleza-estetica') }}" class="hover:text-gold transition-colors">{{ __('navigation.footer.services.items.complementarios') }}</a></li>
                </ul>
            </div>

            <!-- Column 3: Contact Info (SEO Critical - Plain Text) -->
            <div>
                <h3 class="text-gold font-serif text-lg font-semibold mb-4">{{ __('navigation.footer.contact.title') }}</h3>
                <div class="space-y-4 text-gray-400 text-sm">
                    <div>
                        <div class="font-semibold text-gray-300">{{ __('navigation.footer.contact.address_label') }}</div>
                        <p>
                            {{ __('navigation.footer.contact.address.street') }}<br>
                            {{ __('navigation.footer.contact.address.postal') }}<br>
                            {{ __('navigation.footer.contact.address.city') }}
                        </p>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-300">{{ __('navigation.footer.contact.phone_label') }}</div>
                        <a href="tel:+34633912050" class="hover:text-gold transition-colors">{{ __('navigation.footer.contact.phone') }}</a>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-300">{{ __('navigation.footer.contact.hours_label') }}</div>
                        <p>{{ __('navigation.footer.contact.hours') }}</p>
                    </div>
                    <a href="https://maps.google.com/?q=Peluquería+Jenver+Carrer+Lleida+21+Montcada+i+Reixac" target="_blank" rel="noopener noreferrer" class="inline-block text-gold hover:text-gold-light transition-colors font-semibold">
                        {{ __('navigation.footer.contact.maps') }}
                    </a>
                </div>
            </div>

            <!-- Column 4: Service Area Text (SEO) -->
            <div>
                <h3 class="text-gold font-serif text-lg font-semibold mb-4">{{ __('navigation.footer.service_area.title') }}</h3>
                <p class="text-gray-400 text-sm mb-6">
                    {{ __('navigation.footer.service_area.text') }}
                </p>
                <!-- Google Maps Embed -->
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2990.320685050766!2d2.1876543!3d41.48972100000001!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x12a4a74d5d5d5d5d%3A0x5d5d5d5d5d5d5d5d!2sCarrer%20Lleida%2C%2021%2C%2008110%20Montcada%20i%20Reixac!5e0!3m2!1ses!2ses!4v1700000000000"
                    width="100%"
                    height="200"
                    style="border:0; filter: grayscale(100%) invert(10%);"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    class="rounded">
                </iframe>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="border-t border-gold/30 bg-black/50 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-center sm:text-left text-gray-500 text-xs">
            <div>{{ __('navigation.footer.bottom.copyright') }}</div>
            <div class="mt-4 sm:mt-0 space-x-4">
                <a href="{{ route('privacidad') }}" class="hover:text-gold transition-colors">{{ __('navigation.footer.bottom.privacy') }}</a>
                <span>·</span>
                <a href="{{ route('avisos-legales') }}" class="hover:text-gold transition-colors">{{ __('navigation.footer.bottom.legal') }}</a>
                <span>·</span>
                <a href="{{ route('cookies') }}" class="hover:text-gold transition-colors">{{ __('navigation.footer.bottom.cookies') }}</a>
            </div>
        </div>
    </div>
</footer>
