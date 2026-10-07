{{-- Vista Día's timeline grid (PRF-108, PRF-116, PRF-117). Expects
     $timeline, $gridStart, $gridEnd, $nowLineTop (int px offset, or null
     when today is not shown or "ahora" falls outside the grid range).
     Review finding N4: the grid used to scroll inside its own
     "overflow-y-auto; max-height: 70vh" box, nested inside the page's own
     scroll — two scrollbars, one inside the other, awkward on a phone.
     The grid now takes its full natural height and the inline script
     below scrolls the *page* to "ahora" on load instead, or leaves it
     alone (the page's own top) when there is no "ahora" line to show. --}}
<a href="#citas-del-dia" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-gold focus:text-black focus:px-3 focus:py-2">Saltar a las citas</a>

<div id="timeline-scroll" class="border border-[#2A2A2A] mb-6" role="region" aria-label="Disponibilidad del día">
    {{-- A discrete "Plaza 1 · Plaza 2" header (review finding N6): without
         it, nothing on screen says which lane is which — the aria-label on
         each cita/hueco already says it for anyone not seeing the grid, so
         this header is purely visual (aria-hidden) to avoid announcing it
         twice. --}}
    <div class="flex text-[10px] text-gray-500 border-b border-[#2A2A2A]" aria-hidden="true">
        <div class="w-11 shrink-0"></div>
        <div class="flex-1 flex">
            @for ($lane = 0; $lane < $timeline['lanes']; $lane++)
                <div class="flex-1 min-w-0 text-center py-0.5 truncate">Plaza {{ $lane + 1 }}</div>
            @endfor
        </div>
    </div>
    <div class="flex">
        @include('admin.agenda._timeline-hour-axis')
        @include('admin.agenda._timeline-column', ['compact' => false])
    </div>
</div>

@if ($nowLineTop !== null)
    <script>
        (function () {
            var container = document.getElementById('timeline-scroll');
            if (container) {
                var target = container.getBoundingClientRect().top + window.scrollY + {{ $nowLineTop }} - 100;
                window.scrollTo(0, Math.max(0, target));
            }
        })();
    </script>
@endif
