@extends('layouts.app')

@section('title', __('reservas.meta.title'))
@section('meta_description', __('reservas.meta.description'))
@section('og_title', __('reservas.meta.title'))
@section('og_description', __('reservas.meta.description'))
@section('canonical', route('reservas'))

@section('content')
<section class="bg-[#111111] py-12 md:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl font-bold text-white mb-4">{{ __('reservas.hero.title') }}</h1>
        <p class="text-gray-400 text-lg">{{ __('reservas.hero.subtitle') }}</p>
    </div>
</section>

<section class="bg-[#0A0A0A] py-12 md:py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        @if (session('status'))
            <p role="status" class="border border-gold/40 bg-gold/10 text-gold-light px-4 py-3">{{ session('status') }}</p>
        @endif

        @error('booking')
            <p role="alert" class="border border-red-500/50 bg-red-500/10 text-red-200 px-4 py-3">{{ $message }}</p>
        @enderror

        {{-- $services is never empty here: BookingController::index()
             already sent reservas-disabled instead whenever there is no
             service reservable online (PRF-147). --}}
        @if ($selectedServices->isEmpty())
            {{-- Step 1: 1 to MAX_SERVICES services with checkboxes
                 (PRF-127), works with no JavaScript at all: the whole
                 choice travels in the URL ("servicio[]") via a plain GET
                 form. --}}
            <div>
                <h2 class="font-serif text-2xl text-white mb-2">{{ __('reservas.steps.service') }}</h2>
                <p class="text-sm text-gray-400 mb-6">{{ __('reservas.max_services', ['max' => \App\Models\Appointment::MAX_SERVICES]) }}</p>

                @php
                    // Also a list of services refused when the booking was
                    // sent (StoreBookingRequest::failedValidation()).
                    $servicesInvalid = $invalidSelection || $errors->has('service_ids') || $errors->has('service_ids.*');
                @endphp
                @if ($servicesInvalid)
                    <p id="services-error" role="alert" class="border border-red-500/50 bg-red-500/10 text-red-200 px-4 py-3 mb-6">{{ __('reservas.messages.invalid_services') }}</p>
                @endif

                <form method="GET" action="{{ route('reservas') }}">
                    <fieldset @if ($servicesInvalid) aria-describedby="services-error" @endif>
                    <legend class="sr-only">{{ __('reservas.steps.service') }}</legend>
                    <ul class="grid sm:grid-cols-2 gap-4 mb-6">
                        @foreach ($services as $item)
                            <li>
                                {{-- The whole row is the label (at least
                                     44px tall) so the checkbox itself does
                                     not have to be the tap target. --}}
                                <label class="flex items-center justify-between gap-4 min-h-11 border border-[#2A2A2A] hover:border-gold p-5 transition-colors cursor-pointer">
                                    <span>
                                        <span class="block text-white font-semibold">{{ $item->name }}</span>
                                    </span>
                                    {{-- No "data-minutes" here on purpose (PRF-149): the
                                         duration is an internal number for the salon to
                                         work out free slots, never something the
                                         customer should read, not even from the page
                                         source. --}}
                                    <input type="checkbox" name="servicio[]" value="{{ $item->id }}" class="service-checkbox w-5 h-5 shrink-0 accent-gold" @checked(in_array($item->id, $checkedIds, true)) @if ($servicesInvalid) aria-invalid="true" @endif>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    </fieldset>

                    <button type="submit" class="btn-gold">{{ __('reservas.view_days') }}</button>
                </form>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-4 border border-[#2A2A2A] p-4">
                <p class="text-white">
                    {{-- PRF-149: the duration (and its total) stays an
                         internal number for the salon; only the chosen
                         services' names are shown here. --}}
                    <span class="text-gold">{{ \App\Booking\ServiceList::label($selectedServices->pluck('name')) }}</span>
                </p>
                {{-- Back to step 1 with this choice still checked (review
                     finding L6). --}}
                <a href="{{ route('reservas', [...$servicioQuery, 'cambiar' => 1]) }}" class="text-sm text-gray-300 hover:text-gold underline">{{ __('reservas.change_service') }}</a>
            </div>
            @if ($selectionAdjusted)
                <p role="status" class="text-sm text-gray-400">{{ __('reservas.messages.services_adjusted') }}</p>
            @endif

            {{-- Step 2: day --}}
            @include('pages.partials.reservas-calendar')

            {{-- Step 3: time and details --}}
            @if ($day !== null)
                @include('pages.partials.reservas-form')
            @elseif ($requestedDay !== null)
                <p class="text-gray-300">{{ __('reservas.calendar.day_full') }}</p>
                @error('time') <p class="text-red-400">{{ $message }}</p> @enderror
            @endif
        @endif
    </div>
</section>
@endsection
