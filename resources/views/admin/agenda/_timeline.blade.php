{{-- Vista Día's timeline grid (PRF-108). Expects $timeline, $gridStart,
     $gridEnd. --}}
<div class="border border-[#2A2A2A] mb-6 overflow-hidden">
    <div class="flex">
        @include('admin.agenda._timeline-hour-axis')
        @include('admin.agenda._timeline-column', ['compact' => false])
    </div>
</div>
