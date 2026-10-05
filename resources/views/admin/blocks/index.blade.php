@extends('layouts.admin')

@section('title', 'Cierres')

@section('content')
<h1 class="font-serif text-3xl text-white mb-2">Cierres y bloqueos</h1>
<p class="text-sm text-gray-400 mb-6">Un cierre total no deja reservar en ese periodo. Una reducción resta citas a la vez a la capacidad (por ejemplo, 1 cuando una peluquera está de vacaciones). Las citas ya confirmadas no se cancelan solas.</p>

@php($inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-2 focus:border-gold focus:outline-none')

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
    <div>
        <label for="capacity_reduction" class="block text-sm mb-1">Tipo</label>
        <select id="capacity_reduction" name="capacity_reduction" class="{{ $inputClass }}">
            <option value="">Cierre total</option>
            @foreach (range(1, 10) as $reduction)
                <option value="{{ $reduction }}" @selected((string) old('capacity_reduction') === (string) $reduction)>Reducir {{ $reduction }} {{ $reduction === 1 ? 'cita' : 'citas' }} a la vez</option>
            @endforeach
        </select>
        @error('capacity_reduction') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
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
                        {{ $block->isFullClosure() ? 'Cierre total' : 'Reduce '.$block->capacity_reduction.' '.($block->capacity_reduction === 1 ? 'cita' : 'citas').' a la vez' }}@if ($block->reason) · {{ $block->reason }}@endif
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
