<!-- Announcement Bar -->
<div id="announcement" class="fixed top-0 left-0 right-0 z-40 bg-black border-b border-gold/30 py-2 px-4 text-center text-xs md:text-sm text-gray-300">
    <span class="inline-block">{{ __('navigation.announcement.location') }}</span>
    <span class="mx-2 text-gray-500">·</span>
    <a href="tel:+34633912050" class="hover:text-gold transition-colors">{{ __('navigation.announcement.phone_label') }} 633 912 050</a>
</div>

<!-- Main Navbar -->
<nav id="navbar" class="fixed top-8 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-[#2A2A2A] transition-all duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-24">
            <!-- Logo -->
            <a href="{{ url('/') }}" class="flex-shrink-0 flex items-center hover:opacity-80 transition-opacity">
                <img src="{{ asset('images/logo-jenver.png') }}" alt="Peluquería Jenver Logo" class="h-20 w-auto">
            </a>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="{{ route('home') }}" class="text-gray-300 hover:text-gold transition-colors @if(request()->routeIs('home')) text-gold @endif" @if(request()->routeIs('home')) aria-current="page" @endif>{{ __('navigation.navbar.menu.home') }}</a>

                <!-- Services Dropdown -->
                <div class="group relative">
                    <button class="text-gray-300 hover:text-gold transition-colors flex items-center gap-1">
                        {{ __('navigation.navbar.menu.services') }}
                        <svg class="w-4 h-4 group-hover:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                        </svg>
                    </button>
                    <div class="absolute left-0 mt-0 w-56 bg-black/95 border border-[#2A2A2A] rounded-sm shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-40">
                        <a href="{{ route('mechas') }}" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-[#1A1A1A] text-sm">{{ __('navigation.footer.services.items.balayage') }}</a>
                        <a href="{{ route('color') }}" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-[#1A1A1A] text-sm border-t border-[#2A2A2A]">{{ __('navigation.footer.services.items.coloring') }}</a>
                        <a href="{{ route('alisados') }}" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-[#1A1A1A] text-sm border-t border-[#2A2A2A]">{{ __('navigation.footer.services.items.alisados') }}</a>
                        <a href="{{ route('peinados') }}" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-[#1A1A1A] text-sm border-t border-[#2A2A2A]">{{ __('navigation.footer.services.items.peinados') }}</a>
                        <a href="{{ route('corte') }}" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-[#1A1A1A] text-sm border-t border-[#2A2A2A]">{{ __('navigation.footer.services.items.cut') }}</a>
                        <a href="{{ route('afro') }}" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-[#1A1A1A] text-sm border-t border-[#2A2A2A]">{{ __('navigation.footer.services.items.afro') }}</a>
                        <a href="{{ route('complementarios') }}" class="block px-4 py-3 text-gray-300 hover:text-gold hover:bg-[#1A1A1A] text-sm border-t border-[#2A2A2A]">{{ __('navigation.footer.services.items.complementarios') }}</a>
                    </div>
                </div>

                <a href="#nosotros" class="text-gray-300 hover:text-gold transition-colors">{{ __('navigation.navbar.menu.about') }}</a>
                <a href="#contacto" class="text-gray-300 hover:text-gold transition-colors">{{ __('navigation.navbar.menu.contact') }}</a>
            </div>

            <!-- CTA Button (Desktop) -->
            <div class="hidden md:flex items-center">
                <a href="tel:+34633912050" class="btn-gold font-semibold text-sm">
                    {{ __('navigation.navbar.cta') }}
                </a>
            </div>

            <!-- Mobile Menu Button -->
            <div class="md:hidden">
                <button id="mobile-menu-btn" class="text-gray-300 hover:text-gold transition-colors" aria-label="{{ __('navigation.navbar.mobile_menu') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden pb-4 pt-2 border-t border-[#2A2A2A] bg-black">
            <a href="{{ route('home') }}" class="block px-3 py-2 text-gray-300 hover:text-gold transition-colors">{{ __('navigation.navbar.menu.home') }}</a>

            <!-- Mobile Services Section -->
            <button id="mobile-services-toggle" class="w-full text-left px-3 py-2 text-gray-300 hover:text-gold transition-colors flex items-center justify-between">
                <span>{{ __('navigation.navbar.menu.services') }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </button>
            <div id="mobile-services-menu" class="hidden bg-[#1A1A1A] border-t border-[#2A2A2A]">
                <a href="{{ route('mechas') }}" class="block px-6 py-2 text-gray-400 hover:text-gold transition-colors text-sm">{{ __('navigation.footer.services.items.balayage') }}</a>
                <a href="{{ route('color') }}" class="block px-6 py-2 text-gray-400 hover:text-gold transition-colors text-sm">{{ __('navigation.footer.services.items.coloring') }}</a>
                <a href="{{ route('alisados') }}" class="block px-6 py-2 text-gray-400 hover:text-gold transition-colors text-sm">{{ __('navigation.footer.services.items.alisados') }}</a>
                <a href="{{ route('peinados') }}" class="block px-6 py-2 text-gray-400 hover:text-gold transition-colors text-sm">{{ __('navigation.footer.services.items.peinados') }}</a>
                <a href="{{ route('corte') }}" class="block px-6 py-2 text-gray-400 hover:text-gold transition-colors text-sm">{{ __('navigation.footer.services.items.cut') }}</a>
                <a href="{{ route('afro') }}" class="block px-6 py-2 text-gray-400 hover:text-gold transition-colors text-sm">{{ __('navigation.footer.services.items.afro') }}</a>
                <a href="{{ route('complementarios') }}" class="block px-6 py-2 text-gray-400 hover:text-gold transition-colors text-sm">{{ __('navigation.footer.services.items.complementarios') }}</a>
            </div>

            <a href="#nosotros" class="block px-3 py-2 text-gray-300 hover:text-gold transition-colors">{{ __('navigation.navbar.menu.about') }}</a>
            <a href="#contacto" class="block px-3 py-2 text-gray-300 hover:text-gold transition-colors">{{ __('navigation.navbar.menu.contact') }}</a>
            <div class="px-3 py-3 mt-2 border-t border-[#2A2A2A]">
                <a href="tel:+34633912050" class="btn-gold block text-center w-full text-sm font-semibold">
                    {{ __('navigation.navbar.cta') }}
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    mobileMenuBtn?.addEventListener('click', () => {
        mobileMenu?.classList.toggle('hidden');
    });

    // Mobile services submenu toggle
    const mobileServicesToggle = document.getElementById('mobile-services-toggle');
    const mobileServicesMenu = document.getElementById('mobile-services-menu');

    mobileServicesToggle?.addEventListener('click', () => {
        mobileServicesMenu?.classList.toggle('hidden');
    });

    // Close menu when clicking a link
    mobileMenu?.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.add('hidden');
            mobileServicesMenu?.classList.add('hidden');
        });
    });

    // Navbar styling on scroll
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            navbar?.classList.add('shadow-lg', 'shadow-gold/20', 'bg-black/95');
            navbar?.classList.remove('bg-black/90');
        } else {
            navbar?.classList.add('bg-black/90');
            navbar?.classList.remove('shadow-lg', 'shadow-gold/20', 'bg-black/95');
        }
    });
</script>
