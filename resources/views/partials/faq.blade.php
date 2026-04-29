<div class="space-y-4">
    @foreach ($preguntas as $item)
        <details class="group bg-[#1A1A1A] border border-[#2A2A2A] rounded p-4 cursor-pointer hover:border-gold/50 transition-colors">
            <summary class="flex justify-between items-center font-semibold text-white group-open:text-gold transition-colors">
                {{ $item['q'] }}
                <svg class="w-5 h-5 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </summary>
            <p class="text-gray-400 text-sm mt-4 leading-relaxed">
                {{ $item['a'] }}
            </p>
        </details>
    @endforeach
</div>
