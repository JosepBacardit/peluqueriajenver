{{-- "Ahora" line overlay for one day column (PRF-116): absolute within its
     column, not the whole week, since lane widths are flexible (%) and a
     week-wide line would need a pixel width this layout does not compute.
     Expects $nowLineTop (int px from the grid's top). Decorative — the
     "Ahora" text label is the one that matters for anyone not seeing the
     red line, so this is aria-hidden rather than announced twice. --}}
<div class="absolute left-0 right-0 z-10 pointer-events-none" style="top: {{ $nowLineTop }}px" aria-hidden="true">
    <div class="h-px bg-red-500"></div>
    <span class="absolute -top-2.5 left-1 text-[9px] leading-none text-red-400 bg-black px-1">Ahora</span>
</div>
