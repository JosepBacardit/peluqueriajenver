@php
    /*
     * Admin modules shown in the navigation. Adding a module to the panel
     * means adding one entry here (and its routes); modules whose routes
     * do not exist yet are skipped.
     */
    $modules = [
        ['label' => 'Agenda', 'route' => 'admin.agenda', 'active' => 'admin.agenda*'],
        ['label' => 'Servicios', 'route' => 'admin.services.index', 'active' => 'admin.services.*'],
        ['label' => 'Horario', 'route' => 'admin.opening-hours.edit', 'active' => 'admin.opening-hours.*'],
        ['label' => 'Cierres', 'route' => 'admin.blocks.index', 'active' => 'admin.blocks.*'],
        ['label' => 'Ajustes', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*'],
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

                <nav aria-label="Módulos del panel" class="flex flex-wrap gap-1 text-sm" data-admin-modules>
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
