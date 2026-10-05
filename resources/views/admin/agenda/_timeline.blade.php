{{-- Vista Día's timeline grid (PRF-108, PRF-116, PRF-117). Expects
     $timeline, $gridStart, $gridEnd, $nowLineTop (int px offset, or null
     when today is not shown or "ahora" falls outside the grid range).
     The grid is internally scrollable (max-height) so a long day does not
     take over the whole page; the inline script below scrolls it to
     "ahora" on load, or leaves it at the top (the opening time) when
     there is no "ahora" line to show. --}}
<div id="timeline-scroll" class="border border-[#2A2A2A] mb-6 overflow-y-auto" style="max-height: 70vh">
    <div class="flex relative">
        @include('admin.agenda._timeline-hour-axis')
        @include('admin.agenda._timeline-column', ['compact' => false])
        @if ($nowLineTop !== null)
            <div class="absolute left-11 right-0 z-10 pointer-events-none" style="top: {{ $nowLineTop }}px" aria-hidden="true">
                <div class="h-px bg-red-500"></div>
                <span class="absolute -top-2.5 left-1 text-[9px] leading-none text-red-400 bg-black px-1">Ahora</span>
            </div>
        @endif
    </div>
</div>

@if ($nowLineTop !== null)
    <script>
        (function () {
            var container = document.getElementById('timeline-scroll');
            if (container) {
                container.scrollTop = Math.max(0, {{ $nowLineTop }} - 100);
            }
        })();
    </script>
@endif
