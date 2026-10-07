@extends('layouts.app')

@section('title', __('reservas.appointment.meta_title'))
@section('robots', 'noindex, nofollow')
@section('canonical', route('reservas'))
@section('og_url', route('reservas'))
{{-- The URL carries the appointment's secret token: no third-party analytics here. --}}
@section('without_analytics', '1')

@section('content')
<section class="bg-[#111111] py-12 md:py-16">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <h1 class="font-serif text-4xl font-bold text-white text-center">{{ __('reservas.appointment.title') }}</h1>

        @if (session('status'))
            <p role="status" class="border border-gold/40 bg-gold/10 text-gold-light px-4 py-3 text-center">{{ session('status') }}</p>
        @endif

        @error('booking')
            <p role="alert" class="border border-red-500/50 bg-red-500/10 text-red-200 px-4 py-3 text-center">{{ $message }}</p>
        @enderror

        @if (! $appointment->isConfirmed() && ! session('status'))
            <p role="status" class="border border-red-500/40 bg-red-500/10 text-red-200 px-4 py-3 text-center">{{ __('reservas.messages.already_cancelled') }}</p>
        @endif

        <dl class="border border-[#2A2A2A] divide-y divide-[#2A2A2A]">
            <div class="grid grid-cols-3 gap-4 p-4">
                <dt class="text-gray-400">{{ __('reservas.appointment.service') }}</dt>
                {{-- PRF-130/PRF-149: every service by name — never its
                     duration or the total, an internal number for the
                     salon. --}}
                <dd class="col-span-2 text-white">
                    <ul>
                        @foreach ($appointment->items as $item)
                            <li>{{ $item->service_name }}</li>
                        @endforeach
                    </ul>
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 p-4">
                <dt class="text-gray-400">{{ __('reservas.appointment.when') }}</dt>
                <dd class="col-span-2 text-white first-letter:uppercase">{{ $appointment->dayLabel() }}, {{ $appointment->starts_at->format('H:i') }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 p-4">
                <dt class="text-gray-400">{{ __('reservas.appointment.name') }}</dt>
                <dd class="col-span-2 text-white">{{ $appointment->customer_name }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 p-4">
                <dt class="text-gray-400">{{ __('reservas.appointment.address') }}</dt>
                <dd class="col-span-2 text-white">{{ __('reservas.appointment.address_value') }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 p-4">
                <dt class="text-gray-400">{{ __('reservas.appointment.status') }}</dt>
                <dd class="col-span-2 {{ $appointment->isConfirmed() ? 'text-green-300' : 'text-red-300' }}">{{ $appointment->status->label() }}</dd>
            </div>
        </dl>

        @if ($appointment->isConfirmed())
            <div class="border border-[#2A2A2A] p-4 space-y-3">
                <h2 class="font-serif text-xl text-white">{{ __('reservas.appointment.cancel_title') }}</h2>
                @if ($canCancel)
                    <p class="text-sm text-gray-300">{{ __('reservas.appointment.cancel_help', ['hours' => $cancellationLimitHours]) }}</p>
                    <form method="POST" action="{{ route('cita.cancel', $appointment->token) }}" class="space-y-3">
                        @csrf
                        {{-- Whole row tappable (min-h-11) with a bigger checkbox,
                             not just the 20px checkbox itself (review finding N4). --}}
                        <label class="flex items-center gap-3 min-h-11 py-2 text-sm text-gray-200 cursor-pointer">
                            <input type="checkbox" name="confirm" value="1" required class="w-5 h-5 shrink-0 accent-gold">
                            {{ __('reservas.appointment.cancel_confirm') }}
                        </label>
                        @error('confirm') <p class="text-red-400 text-sm">{{ $message }}</p> @enderror
                        <button type="submit" class="btn-outline w-full">{{ __('reservas.appointment.cancel_button') }}</button>
                    </form>
                @else
                    <p class="text-gray-300">{{ __('reservas.messages.too_late_to_cancel') }}</p>
                @endif
            </div>
        @else
            <p class="text-center"><a href="{{ route('reservas') }}" class="btn-gold">{{ __('reservas.appointment.book_again') }}</a></p>
        @endif
    </div>
</section>
@endsection
