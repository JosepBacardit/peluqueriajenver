@extends('layouts.admin')

@section('title', 'Cierres')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Cierres y bloqueos</h1>
<p class="text-sm text-gray-400 mb-6">Un cierre total no deja reservar en ese periodo. Si solo falta una peluquera (un día libre, el médico, unas vacaciones o una baja), marca «No está» con su nombre: no se le podrán reservar citas y las demás siguen atendiendo. Las citas ya confirmadas no se cancelan solas.</p>

{{-- py-3 (not py-2): every field, including the datetime-local pickers,
     meets the 44px touch target (review finding N3). --}}
@php($inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-3 focus:border-gold focus:outline-none')

<form method="POST" action="{{ route('admin.blocks.store') }}" class="grid sm:grid-cols-2 gap-4 max-w-3xl border border-[#2A2A2A] p-4 mb-10">
    @csrf
    <div>
        <label for="starts_at" class="block text-sm mb-1">Inicio</label>
        <input id="starts_at" name="starts_at" type="datetime-local" step="300" required value="{{ old('starts_at') }}" class="{{ $inputClass }}">
        @error('starts_at') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="ends_at" class="block text-sm mb-1">Fin</label>
        <input id="ends_at" name="ends_at" type="datetime-local" step="300" required value="{{ old('ends_at') }}" class="{{ $inputClass }}">
        @error('ends_at') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    {{-- PRF-170: the whole salon, or one or more hairdressers (one
         closure is saved for each). --}}
    @php($scope = old('scope', 'total'))
    <fieldset class="sm:col-span-2 space-y-2">
        <legend class="block text-sm mb-1">¿Qué se cierra?</legend>
        <label class="flex items-center gap-3 min-h-11 cursor-pointer">
            <input type="radio" name="scope" value="total" class="w-5 h-5 accent-gold" @checked($scope === 'total')>
            Cierre total (todo el salón)
        </label>
        <label class="flex items-center gap-3 min-h-11 cursor-pointer">
            <input type="radio" name="scope" value="hairdressers" class="w-5 h-5 accent-gold" @checked($scope === 'hairdressers')>
            Solo las peluqueras marcadas:
        </label>
        <ul class="pl-8 space-y-1">
            @foreach ($hairdressers as $hairdresser)
                <li>
                    <label class="flex items-center gap-3 min-h-11 cursor-pointer">
                        <input type="checkbox" name="hairdresser_ids[]" value="{{ $hairdresser->id }}" class="w-5 h-5 accent-gold" @checked(in_array((string) $hairdresser->id, array_map('strval', (array) old('hairdresser_ids', [])), true))>
                        No está {{ $hairdresser->name }}
                    </label>
                </li>
            @endforeach
        </ul>
        @error('scope') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        @error('hairdresser_ids') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        @error('hairdresser_ids.*') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </fieldset>
    <div>
        <label for="reason" class="block text-sm mb-1">Motivo (opcional)</label>
        <input id="reason" name="reason" type="text" maxlength="150" value="{{ old('reason') }}" class="{{ $inputClass }}">
        @error('reason') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <button type="submit" class="btn-gold">Añadir cierre</button>
    </div>
</form>

<h2 class="font-serif text-2xl text-white mb-4">Próximos cierres</h2>

@if ($blocks->isEmpty())
    <p class="text-gray-300">No hay cierres previstos.</p>
@else
    <ul class="divide-y divide-[#1A1A1A] max-w-3xl">
        @foreach ($blocks as $block)
            <li class="py-3 flex flex-wrap items-center gap-4 justify-between">
                <div>
                    <p>{{ $block->starts_at->format('d/m/Y H:i') }} → {{ $block->ends_at->format('d/m/Y H:i') }}</p>
                    <p class="text-sm text-gray-400">
                        {{ $block->label() }}@if ($block->reason) · {{ $block->reason }}@endif
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.blocks.destroy', $block) }}" onsubmit="return confirm('¿Eliminar este cierre? Sus horas volverán a estar disponibles.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger-outline text-sm">Eliminar</button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
@endsection
