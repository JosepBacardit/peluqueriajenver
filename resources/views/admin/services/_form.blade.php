@csrf

@php
    $inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-2 focus:border-gold focus:outline-none';
    $price = old('price', $service->price_cents === null ? '' : number_format($service->price_cents / 100, 2, '.', ''));
@endphp

<div>
    <label for="name" class="block text-sm mb-1">Nombre</label>
    <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $service->name) }}" class="{{ $inputClass }}">
    @error('name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
</div>

<div class="grid sm:grid-cols-3 gap-4">
    <div>
        <label for="duration_minutes" class="block text-sm mb-1">Duración (minutos, múltiplo de 5)</label>
        <input id="duration_minutes" name="duration_minutes" type="number" min="5" max="600" step="5" required value="{{ old('duration_minutes', $service->duration_minutes) }}" class="{{ $inputClass }}">
        @error('duration_minutes') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="price" class="block text-sm mb-1">Precio interno (€, opcional)</label>
        <input id="price" name="price" type="number" min="0" max="9999.99" step="0.01" value="{{ $price }}" class="{{ $inputClass }}">
        @error('price') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="sort_order" class="block text-sm mb-1">Orden</label>
        <input id="sort_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $service->sort_order ?? 0) }}" class="{{ $inputClass }}">
        @error('sort_order') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="space-y-2">
    <label class="flex items-center gap-2">
        <input type="hidden" name="is_bookable_online" value="0">
        <input type="checkbox" name="is_bookable_online" value="1" @checked(old('is_bookable_online', $service->is_bookable_online))>
        Reservable online
    </label>
    <label class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active))>
        Activo
    </label>
</div>

<div class="flex gap-4 items-center">
    <button type="submit" class="btn-gold">Guardar</button>
    <a href="{{ route('admin.services.index') }}" class="text-gray-300 hover:text-gold">Cancelar</a>
</div>
