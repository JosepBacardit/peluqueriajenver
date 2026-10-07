{{-- Shared hour axis (PRF-108): one 44px row per half hour, a label and a
     stronger border on the hour, a fainter border on the half hour.
     Expects $gridStart, $gridEnd (minutes since midnight).
     Review finding N5: the hour label used to be shifted up 5px (a
     "relative top-[-5px]" trick) to visually hug the line above it — which
     clipped the very first label against the container's own top edge,
     and was small/low-contrast (10px, gray-500) besides. The label now
     sits naturally inside its own row, at a legible size and AA-ish
     contrast against the black background. --}}
<div class="w-11 shrink-0">
    @for ($minute = $gridStart; $minute < $gridEnd; $minute += 30)
        @php($isHour = $minute % 60 === 0)
        <div class="text-right pr-1 text-xs leading-none text-gray-300 {{ $isHour ? 'border-t border-[#2A2A2A]' : 'border-t border-[#1A1A1A]' }}" style="height: {{ \App\Booking\DayTimeline::pxFromMinutes(30) }}px">
            @if ($isHour)
                <span>{{ sprintf('%02d:00', intdiv($minute, 60)) }}</span>
            @endif
        </div>
    @endfor
</div>
