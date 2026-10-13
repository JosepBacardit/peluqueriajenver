@csrf

@php($inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-3 focus:border-gold focus:outline-none')

<div>
    <label for="name" class="block text-sm mb-1">Nombre</label>
    <input id="name" name="name" type="text" maxlength="{{ \App\Models\Hairdresser::MAX_NAME_LENGTH }}" required value="{{ old('name', $hairdresser->name) }}" class="{{ $inputClass }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
    <p class="text-xs text-gray-400 mt-1">Es el nombre que verán las clientas al reservar.</p>
    @error('name') <p id="name-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
</div>

<div>
    <label class="flex items-center gap-3 min-h-11 cursor-pointer">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="w-5 h-5 accent-gold" @checked(old('is_active', $hairdresser->is_active))>
        Activa
    </label>
    @error('is_active') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
</div>

{{-- PRF-173: only shown to customers ("Destaca en: …") and suggested in
     the panel; it never stops her from doing the rest. --}}
@php($checkedStandout = array_map('intval', (array) old('service_ids', $standoutIds)))
<fieldset>
    <legend class="block text-sm mb-1">Destaca en (opcional)</legend>
    <p class="text-xs text-gray-400 mb-2">Las clientas lo verán al elegir peluquera. Puede seguir haciendo todos los servicios.</p>
    <div class="space-y-1">
        @foreach ($services as $service)
            <label class="flex items-center gap-3 min-h-11 cursor-pointer">
                <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="w-5 h-5 shrink-0 accent-gold" @checked(in_array($service->id, $checkedStandout, true))>
                {{ $service->name }}
            </label>
        @endforeach
    </div>
    @error('service_ids.*') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
</fieldset>

<div class="flex flex-wrap gap-3">
    <button type="submit" class="btn-gold">Guardar</button>
    <a href="{{ route('admin.hairdressers.index') }}" class="btn-outline">Volver</a>
</div>
