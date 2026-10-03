@php
    $inputClass = 'w-full bg-black border border-[#2A2A2A] px-3 py-2 text-white focus:border-gold focus:outline-none';
    $dayTitle = $day->locale('es')->translatedFormat('l j \d\e F');
@endphp
<div id="horas">
    <h2 class="font-serif text-2xl text-white mb-2">{{ __('reservas.steps.time') }}</h2>
    <p class="text-gold-light mb-6 first-letter:uppercase">{{ $dayTitle }}</p>

    <form method="POST" action="{{ route('reservas.store') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="service_id" value="{{ $service->id }}">
        <input type="hidden" name="date" value="{{ $day->toDateString() }}">

        <fieldset>
            <legend class="text-sm mb-2">{{ __('reservas.form.times_legend') }}</legend>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                @foreach ($times as $time)
                    @php($value = $time->format('H:i'))
                    <label class="cursor-pointer">
                        <input type="radio" name="time" value="{{ $value }}" class="peer sr-only" required @checked(old('time') === $value)>
                        <span class="block text-center py-2 border border-[#2A2A2A] peer-checked:bg-gold peer-checked:text-black peer-checked:border-gold peer-focus-visible:ring-2 peer-focus-visible:ring-gold hover:border-gold">{{ $value }}</span>
                    </label>
                @endforeach
            </div>
            @error('time') <p class="text-red-400 text-sm mt-2">{{ $message }}</p> @enderror
        </fieldset>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="customer_name" class="block text-sm mb-1">{{ __('reservas.form.name') }}</label>
                <input id="customer_name" name="customer_name" type="text" maxlength="100" required autocomplete="name" value="{{ old('customer_name') }}" class="{{ $inputClass }}">
                @error('customer_name') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="customer_phone" class="block text-sm mb-1">{{ __('reservas.form.phone') }}</label>
                <input id="customer_phone" name="customer_phone" type="tel" maxlength="20" required autocomplete="tel" value="{{ old('customer_phone') }}" class="{{ $inputClass }}">
                @error('customer_phone') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="customer_email" class="block text-sm mb-1">{{ __('reservas.form.email') }}</label>
            <input id="customer_email" name="customer_email" type="email" maxlength="150" required autocomplete="email" value="{{ old('customer_email') }}" class="{{ $inputClass }}" aria-describedby="customer_email_help">
            <p id="customer_email_help" class="text-xs text-gray-400 mt-1">{{ __('reservas.form.email_help') }}</p>
            @error('customer_email') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="notes" class="block text-sm mb-1">{{ __('reservas.form.notes') }}</label>
            <textarea id="notes" name="notes" maxlength="500" rows="3" class="{{ $inputClass }}">{{ old('notes') }}</textarea>
            @error('notes') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Honeypot: hidden from people, bots tend to fill it. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">{{ __('reservas.form.honeypot') }}</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
        </div>

        @include('pages.partials.reservas-privacy-layer')

        <div>
            <label class="flex items-start gap-2 text-sm text-gray-200">
                <input type="checkbox" name="privacy" value="1" required class="mt-1" @checked(old('privacy'))>
                <span>{{ __('reservas.form.privacy') }}</span>
            </label>
            @error('privacy') <p class="text-red-400 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-gold">{{ __('reservas.form.submit') }}</button>
    </form>
</div>
