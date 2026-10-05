@php
    /*
     * Admin modules shown in the navigation. Adding a module to the panel
     * means adding one entry here (and its routes); modules whose routes
     * do not exist yet are skipped.
     */
    $modules = [
        ['label' => 'Agenda', 'route' => 'admin.agenda', 'active' => 'admin.agenda*', 'icon' => 'agenda'],
        ['label' => 'Servicios', 'route' => 'admin.services.index', 'active' => 'admin.services.*', 'icon' => 'services'],
        ['label' => 'Horario', 'route' => 'admin.opening-hours.edit', 'active' => 'admin.opening-hours.*', 'icon' => 'hours'],
        ['label' => 'Cierres', 'route' => 'admin.blocks.index', 'active' => 'admin.blocks.*', 'icon' => 'blocks'],
        ['label' => 'Ajustes', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'icon' => 'settings'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Panel') · Peluquería Jenver</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#0A0A0A] text-gray-100 font-sans antialiased">
    @auth
        <header class="border-b border-[#2A2A2A] bg-[#111111]">
            <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-3">
                <a href="{{ route('admin.home') }}" class="font-serif text-xl text-gold">Jenver · Panel</a>

                <nav aria-label="Módulos del panel" class="hidden md:flex flex-wrap gap-1 text-sm" data-admin-modules>
                    @foreach ($modules as $module)
                        @if (Route::has($module['route']))
                            <a href="{{ route($module['route']) }}"
                               class="px-3 py-1.5 rounded {{ request()->routeIs($module['active']) ? 'bg-gold text-black font-semibold' : 'text-gray-300 hover:text-gold' }}"
                               @if (request()->routeIs($module['active'])) aria-current="page" @endif>{{ $module['label'] }}</a>
                        @endif
                    @endforeach
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="hidden md:flex ml-auto items-center gap-3 text-sm">
                    @csrf
                    <span class="text-gray-400">{{ auth()->user()->name }}</span>
                    <button type="submit" class="btn-outline text-xs px-3">Cerrar sesión</button>
                </form>

                {{-- Hamburger, phones and small tablets only (<768px). The
                     panel will grow past 5 modules, so a scrollable dropdown
                     menu (below) fits more apartados than a bottom tab bar
                     ever could (decision of 2026-10-05, replaces the bottom
                     bar from PRF-090's first version). --}}
                <button type="button" id="admin-menu-btn" class="md:hidden ml-auto w-11 h-11 flex items-center justify-center text-gray-300 hover:text-gold"
                        aria-expanded="true" aria-controls="admin-menu" aria-label="Cerrar menú">
                    <svg id="admin-menu-icon-open" class="hidden w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line>
                    </svg>
                    <svg id="admin-menu-icon-close" class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line>
                    </svg>
                </button>
            </div>

            {{-- Dropdown menu, phones and small tablets only. Visible by
                 default (no `hidden` class) and the button starts
                 "expanded": without JavaScript the menu is simply always
                 shown, so every module and "Cerrar sesión" stay reachable
                 (PRF-090) with no button that depends on JS to reveal them.
                 The script right after this nav collapses it and wires the
                 toggle once JS does run. max-h + overflow-y-auto so a panel
                 that grows to 10-12 apartados still scrolls inside the menu
                 instead of running off the screen. --}}
            <nav id="admin-menu" aria-label="Módulos del panel" class="md:hidden border-t border-[#2A2A2A] max-h-[80vh] overflow-y-auto">
                <div class="max-w-6xl mx-auto px-4 py-2">
                    @foreach ($modules as $module)
                        @if (Route::has($module['route']))
                            @php($isActive = request()->routeIs($module['active']))
                            <a href="{{ route($module['route']) }}"
                               class="flex items-center gap-3 min-h-11 px-2 {{ $isActive ? 'text-gold font-semibold' : 'text-gray-300 hover:text-gold' }}"
                               @if ($isActive) aria-current="page" @endif>
                                <svg class="w-5 h-5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    @switch($module['icon'])
                                        @case('agenda')
                                            <rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>
                                            @break
                                        @case('services')
                                            <circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                                            @break
                                        @case('hours')
                                            <circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15.5 14"></polyline>
                                            @break
                                        @case('blocks')
                                            <rect x="4" y="11" width="16" height="9" rx="2"></rect><path d="M7.5 11V7.5a4.5 4.5 0 0 1 9 0V11"></path>
                                            @break
                                        @case('settings')
                                            <line x1="5" y1="21" x2="5" y2="14"></line><line x1="5" y1="10" x2="5" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="19" y1="21" x2="19" y2="16"></line><line x1="19" y1="12" x2="19" y2="3"></line><line x1="2" y1="14" x2="8" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="16" y1="16" x2="22" y2="16"></line>
                                            @break
                                    @endswitch
                                </svg>
                                {{ $module['label'] }}
                            </a>
                        @endif
                    @endforeach

                    <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-[#2A2A2A] pt-2">
                        @csrf
                        <button type="submit" class="flex items-center min-h-11 px-2 w-full text-left text-gray-300 hover:text-gold">Cerrar sesión ({{ auth()->user()->name }})</button>
                    </form>
                </div>
            </nav>
        </header>

        <script>
            (function () {
                var btn = document.getElementById('admin-menu-btn');
                var menu = document.getElementById('admin-menu');
                if (!btn || !menu) { return; }

                var iconOpen = document.getElementById('admin-menu-icon-open');
                var iconClose = document.getElementById('admin-menu-icon-close');

                function setOpen(open) {
                    menu.classList.toggle('hidden', !open);
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    btn.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
                    if (iconOpen) { iconOpen.classList.toggle('hidden', open); }
                    if (iconClose) { iconClose.classList.toggle('hidden', !open); }
                }

                // JS is available: start collapsed. Without this script the
                // menu stays visible (see the comment above <nav id="admin-menu">).
                setOpen(false);

                btn.addEventListener('click', function () {
                    setOpen(menu.classList.contains('hidden'));
                });

                menu.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () { setOpen(false); });
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
                        setOpen(false);
                        btn.focus();
                    }
                });
            })();
        </script>
    @endauth

    <main class="max-w-6xl mx-auto px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 border border-gold/40 bg-gold/10 text-gold-light px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if (session('warning'))
            <div role="alert" class="mb-6 border border-amber-500/50 bg-amber-500/10 text-amber-200 px-4 py-3">
                {{ session('warning') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
