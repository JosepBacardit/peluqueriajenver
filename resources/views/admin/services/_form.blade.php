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

{{-- The service's times as steps, in the order they happen (user's
     request, 2026-10-07: simple enough for hairdressers who do not use
     these tools much). Every other step is a wait, when the hairdresser is
     free for someone else and the customer stays. The total is their sum,
     never typed; ServiceRequest turns them into duration_minutes and
     waits. Internal only, never shown to the customer. One step per row,
     with big fields, for the phone. --}}
@php
    $stepFields = \App\Http\Requests\Admin\ServiceRequest::STEP_FIELDS;
    $storedSteps = $service->duration_minutes === null ? [] : \App\Booking\TimeProfile::fromServices([$service])->steps();
    $stepValues = [];
    foreach ($stepFields as $index => $field) {
        $stepValues[$field] = old($field, $storedSteps[$index] ?? '');
    }
    $stepsTotal = array_sum(array_map(fn ($value) => is_numeric($value) ? (int) $value : 0, $stepValues));
    $stepLabels = [
        'work_1' => 'Trabajo 1',
        'wait_1' => 'Espera 1 (si la hay)',
        'work_2' => 'Trabajo 2',
        'wait_2' => 'Espera 2 (si la hay)',
        'work_3' => 'Trabajo 3',
    ];
@endphp
<fieldset class="space-y-3">
    <legend class="block text-white font-semibold mb-1">Tiempos del servicio</legend>
    <p class="text-sm text-gray-300">Escribe los minutos de cada paso, en el orden en que se hacen. Si el servicio no tiene esperas, rellena solo «Trabajo 1».</p>
    <p class="text-sm text-gray-300">Ejemplo, un tinte: Trabajo 30 · Espera 45 · Trabajo 45.</p>
    <p class="text-sm text-gray-400">Durante la espera, la peluquera queda libre para atender a otra clienta. Pon la espera más corta que suela tener.</p>

    @foreach ($stepFields as $index => $field)
        @php($isWait = $index % 2 === 1)
        <div class="{{ $isWait ? 'pl-3 border-l-2 border-dashed border-gold/40' : '' }}">
            <div class="flex items-center gap-3">
                <label for="{{ $field }}" class="flex-1">
                    <span class="block text-white">{{ $stepLabels[$field] }}</span>
                    @if ($isWait)
                        <span class="block text-sm text-gray-400">La peluquera queda libre</span>
                    @endif
                </label>
                <input id="{{ $field }}" name="{{ $field }}" type="number" inputmode="numeric" min="5" max="600" step="5" value="{{ $stepValues[$field] }}" class="step-minutes w-24 min-h-11 text-lg text-center bg-black border border-[#2A2A2A] px-2 focus:border-gold focus:outline-none" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                <span class="text-gray-300 w-8">min</span>
            </div>
            @error($field) <p id="{{ $field }}-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    @endforeach

    <p class="text-white" aria-live="polite">Duración total: <span id="steps-total">{{ $stepsTotal > 0 ? \App\Models\Service::formatDuration($stepsTotal) : '—' }}</span></p>
    @error('duration_minutes') <p id="duration_minutes-error" class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
</fieldset>

{{-- Live total (progressive enhancement): without JavaScript, the total
     above is the one worked out when the page was served. --}}
<script>
    (function () {
        var fields = document.querySelectorAll('.step-minutes');
        var total = document.getElementById('steps-total');

        if (!total) return;

        function formatMinutes(minutes) {
            var hours = Math.floor(minutes / 60), rest = minutes % 60;
            if (hours === 0) return rest + ' min';
            if (rest === 0) return hours + ' h';
            return hours + ' h ' + rest + ' min';
        }

        function update() {
            var minutes = 0;
            fields.forEach(function (field) {
                var value = parseInt(field.value, 10);
                if (value > 0) minutes += value;
            });
            total.textContent = minutes > 0 ? formatMinutes(minutes) : '—';
        }

        fields.forEach(function (field) { field.addEventListener('input', update); });
    })();
</script>

<div class="grid sm:grid-cols-2 gap-4">
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
