{{-- PRF-168: the hairdresser of the appointment, or "Cualquiera" (any
     free one; the salon can assign her later). Expects $hairdressers,
     $selectedHairdresserId (int|null), $inputClass and $fieldAria, and
     $standoutHairdresserIds: those who stand out in its services, marked
     "(destaca)" as a suggestion (PRF-173). --}}
@php($selectedHairdresser = (string) old('hairdresser_id', $selectedHairdresserId ?? ''))
<div>
    <label for="hairdresser_id" class="block text-sm mb-1">Peluquera</label>
    <select id="hairdresser_id" name="hairdresser_id" class="{{ $inputClass }}" {!! $fieldAria('hairdresser_id', true) !!}>
        <option value="" @selected($selectedHairdresser === '')>Cualquiera</option>
        @foreach ($hairdressers as $hairdresserOption)
            <option value="{{ $hairdresserOption->id }}" @selected($selectedHairdresser === (string) $hairdresserOption->id)>{{ $hairdresserOption->name }}{{ in_array($hairdresserOption->id, $standoutHairdresserIds ?? [], true) ? ' (destaca)' : '' }}{{ $hairdresserOption->is_active ? '' : ' · inactiva' }}</option>
        @endforeach
    </select>
    @if (($standoutHairdresserIds ?? []) !== [])
        <p class="text-xs text-gray-400 mt-1">«(destaca)»: destaca en alguno de estos servicios.</p>
    @endif
    @error('hairdresser_id') <p id="hairdresser_id-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
</div>
