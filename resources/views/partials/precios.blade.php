<div class="mb-8 p-6 bg-[#1A1A1A] border border-[#2A2A2A] rounded">
    <p class="text-gray-400 text-sm mb-4 italic">💡 Precios orientativos. El precio final se confirma en el diagnóstico gratuito previo.</p>
    <div class="space-y-2">
        @foreach ($precios as $precio)
            <div class="flex justify-between items-center py-2 border-b border-[#2A2A2A] last:border-b-0">
                <span class="text-gray-300">{{ $precio[0] }}</span>
                <span class="text-gold font-semibold">{{ $precio[1] }}</span>
            </div>
        @endforeach
    </div>
</div>
