{{-- Shared hour axis (PRF-108): one 44px row per half hour, a label and a
     stronger border on the hour, a fainter border on the half hour.
     Expects $gridStart, $gridEnd (minutes since midnight). --}}
<div class="w-11 shrink-0">
    @for ($minute = $gridStart; $minute < $gridEnd; $minute += 30)
        @php($isHour = $minute % 60 === 0)
        <div class="text-right pr-1 text-[10px] leading-none text-gray-500 {{ $isHour ? 'border-t border-[#2A2A2A]' : 'border-t border-[#1A1A1A]' }}" style="height: {{ \App\Booking\DayTimeline::pxFromMinutes(30) }}px">
            @if ($isHour)
                <span class="relative top-[-5px]">{{ sprintf('%02d:00', intdiv($minute, 60)) }}</span>
            @endif
        </div>
    @endfor
</div>
