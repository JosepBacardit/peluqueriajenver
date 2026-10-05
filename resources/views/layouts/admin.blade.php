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

                <form method="POST" action="{{ route('logout') }}" class="ml-auto flex items-center gap-3 text-sm">
                    @csrf
                    <span class="text-gray-400 hidden sm:inline">{{ auth()->user()->name }}</span>
                    <button type="submit" class="btn-outline text-xs px-3">Cerrar sesión</button>
                </form>
            </div>
        </header>

        {{-- Bottom tab bar, phones and small tablets only (<768px): the
             panel is mostly used one-handed while the salon is working, so
             the five modules stay within thumb's reach instead of at the
             top of the screen. The top nav above keeps serving >=768px. --}}
        <nav aria-label="Módulos del panel" class="md:hidden fixed inset-x-0 bottom-0 z-50 flex bg-[#111111] border-t border-[#2A2A2A]" style="padding-bottom: env(safe-area-inset-bottom)">
            @foreach ($modules as $module)
                @if (Route::has($module['route']))
                    @php($isActive = request()->routeIs($module['active']))
                    <a href="{{ route($module['route']) }}"
                       class="flex-1 flex flex-col items-center justify-center gap-0.5 min-h-14 py-1.5 text-[11px] {{ $isActive ? 'text-gold' : 'text-gray-400 hover:text-gold' }}"
                       @if ($isActive) aria-current="page" @endif>
                        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
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
                        <span>{{ $module['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    @endauth

    <main class="max-w-6xl mx-auto px-4 pt-8 pb-[calc(4.5rem+env(safe-area-inset-bottom))] md:pb-8">
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
