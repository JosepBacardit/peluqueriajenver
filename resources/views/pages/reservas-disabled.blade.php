@extends('layouts.app')

@section('title', __('reservas.disabled.title').' | Peluquería Jenver')
@section('meta_description', __('reservas.disabled.message'))
@section('canonical', route('reservas'))

@section('content')
<section class="bg-[#111111] py-16 md:py-24">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="font-serif text-4xl md:text-5xl font-bold text-white mb-6">{{ __('reservas.disabled.title') }}</h1>

        <p role="status" class="text-gray-300 text-lg mb-10">{{ __('reservas.disabled.message') }}</p>

        {{-- Big, obvious call/WhatsApp buttons (>=44px): the only two
             ways left to book while online booking is off. --}}
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-10">
            <a href="tel:+34633912050" class="btn-gold text-base font-semibold px-8 py-4 min-h-11">
                📞 {{ __('reservas.disabled.call_cta') }}
            </a>
            <a href="https://wa.me/34633912050?text=Hola!%20Quería%20pedir%20cita%20en%20Peluquería%20Jenver" target="_blank" rel="noopener noreferrer" class="btn-outline text-base font-semibold px-8 py-4 min-h-11">
                💬 {{ __('reservas.disabled.whatsapp_cta') }}
            </a>
        </div>

        <div class="text-gray-300 space-y-2 mb-8">
            <p class="text-gold font-semibold">{{ __('reservas.disabled.address_label') }}</p>
            <p>{{ __('home.contacto.address_street') }}, {{ __('home.contacto.address_postal') }}</p>

            <p class="text-gold font-semibold mt-6">{{ __('reservas.disabled.hours_label') }}</p>
            <p>{{ \App\Booking\OpeningHoursSummary::text() }}</p>
        </div>

        {{-- Review finding N1 (coordinator): measured 24px tall before;
             min-h-11 + inline-flex brings it to the same 44px touch
             target as the call/WhatsApp buttons above. --}}
        <a href="{{ route('contacto') }}" class="inline-flex items-center justify-center min-h-11 text-gold hover:text-gold-light transition-colors font-semibold">
            {{ __('reservas.disabled.contact_link') }}
        </a>
    </div>
</section>
@endsection
