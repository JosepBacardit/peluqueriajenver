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

<div class="flex flex-wrap gap-3">
    <button type="submit" class="btn-gold">Guardar</button>
    <a href="{{ route('admin.hairdressers.index') }}" class="btn-outline">Volver</a>
</div>
